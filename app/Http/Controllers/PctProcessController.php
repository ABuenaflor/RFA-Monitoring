<?php

namespace App\Http\Controllers;

use App\Models\Rfa;
use App\Services\PctService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PctProcessController extends Controller
{
    public function index(
        Request $request,
        PctService $pctService
    ): View {
        $asOf = now()
            ->startOfDay();

        /*
        |--------------------------------------------------------------------------
        | Office filter
        |--------------------------------------------------------------------------
        |
        | Page-wide: every card, table and statistic below reflects only the
        | selected office. Values are the office codes stored on the RFAs
        | (config/offices.php); anything else is ignored.
        |
        */

        $offices = config('offices.list', []);

        $officeFilter = (string) $request->query(
            'office',
            ''
        );

        if (! in_array($officeFilter, $offices, true)) {
            $officeFilter = '';
        }

        $rfas = Rfa::query()
            ->when(
                $officeFilter !== '',
                fn ($query) => $query->where(
                    'office',
                    $officeFilter
                )
            )
            ->orderByDesc('date_filed')
            ->orderByDesc('id')
            ->get();

        $activeTimers = collect();
        $historyRows = collect();
        $dataIssues = collect();
        $processingDurations = collect();

        $historicalSummary = array_fill_keys(
            PctService::keys(),
            $this->emptyClassificationSummary()
        );

        /*
        |--------------------------------------------------------------------------
        | Evaluate every RFA
        |--------------------------------------------------------------------------
        */

        foreach ($rfas as $rfa) {
            $evaluation =
                $pctService->evaluate(
                    $rfa,
                    $asOf
                );

            $checkpoints = $evaluation['checkpoints'];

            $hasCompleted = false;

            foreach ($checkpoints as $key => $checkpoint) {
                /*
                | Active timers
                */

                if ($checkpoint['state'] === 'active') {
                    $activeTimers->push([
                        'rfa' => $rfa,
                        'checkpoint' => $checkpoint,
                    ]);
                }

                /*
                | Historical completed results
                */

                $this->addHistoricalResult(
                    $historicalSummary[$key],
                    $checkpoint
                );

                $hasCompleted = $hasCompleted
                    || $checkpoint['state'] === 'completed';

                /*
                | Data-quality issues
                */

                if (
                    in_array(
                        $checkpoint['state'],
                        [
                            'missing_start',
                            'missing_end',
                            'missing_mode',
                            'invalid',
                        ],
                        true
                    )
                ) {
                    $dataIssues->push([
                        'rfa' => $rfa,
                        'checkpoint' => $checkpoint,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | History overview
            |--------------------------------------------------------------------------
            */

            if (
                $hasCompleted
                || $evaluation[
                    'total_processing'
                ]['state'] === 'completed'
            ) {
                $historyRows->push([
                    'rfa' => $rfa,
                    'checkpoints' => $checkpoints,
                    'total_processing' =>
                        $evaluation[
                            'total_processing'
                        ],
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Total disposed processing durations
            |--------------------------------------------------------------------------
            */

            if (
                $evaluation[
                    'total_processing'
                ]['state'] === 'completed'
            ) {
                $processingDurations->push(
                    $evaluation[
                        'total_processing'
                    ]['days']
                );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Historical compliance rates
        |--------------------------------------------------------------------------
        */

        $historicalSummary = array_map(
            fn (array $summary) => $this->finalizeHistoricalSummary($summary),
            $historicalSummary
        );

        /*
        |--------------------------------------------------------------------------
        | Active timer summary
        |--------------------------------------------------------------------------
        */

        $activeSummary = [
            'total' =>
                $activeTimers->count(),

            'within' =>
                $activeTimers
                    ->where(
                        'checkpoint.classification_key',
                        'within'
                    )
                    ->count(),

            'nearing' =>
                $activeTimers
                    ->where(
                        'checkpoint.classification_key',
                        'nearing'
                    )
                    ->count(),

            'on' =>
                $activeTimers
                    ->where(
                        'checkpoint.classification_key',
                        'on'
                    )
                    ->count(),

            'beyond' =>
                $activeTimers
                    ->where(
                        'checkpoint.classification_key',
                        'beyond'
                    )
                    ->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Active table filtering
        |--------------------------------------------------------------------------
        */

        $search = trim(
            (string) $request->query(
                'search',
                ''
            )
        );

        $stageFilter =
            (string) $request->query(
                'stage',
                ''
            );

        $classificationFilter =
            (string) $request->query(
                'pct_status',
                ''
            );

        if ($search !== '') {
            $needle = mb_strtolower(
                $search
            );

            $activeTimers =
                $activeTimers->filter(
                    function ($item) use ($needle) {
                        $rfa =
                            $item['rfa'];

                        $haystack =
                            mb_strtolower(
                                implode(
                                    ' ',
                                    [
                                        $rfa->docket_no,
                                        $rfa->reference_no,
                                        $rfa->requesting_party,
                                        $rfa->responding_party,
                                    ]
                                )
                            );

                        return str_contains(
                            $haystack,
                            $needle
                        );
                    }
                );
        }

        if (
            in_array(
                $stageFilter,
                PctService::keys(),
                true
            )
        ) {
            $activeTimers =
                $activeTimers->filter(
                    fn ($item) =>
                        $item[
                            'checkpoint'
                        ]['stage_key']
                        === $stageFilter
                );
        }

        if (
            in_array(
                $classificationFilter,
                [
                    'within',
                    'nearing',
                    'on',
                    'beyond',
                ],
                true
            )
        ) {
            $activeTimers =
                $activeTimers->filter(
                    fn ($item) =>
                        $item[
                            'checkpoint'
                        ][
                            'classification_key'
                        ]
                        ===
                        $classificationFilter
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Sort active timers by urgency
        |--------------------------------------------------------------------------
        */

        $priority = [
            'beyond' => 1,
            'on' => 2,
            'nearing' => 3,
            'within' => 4,
        ];

        $activeTimers =
            $activeTimers
                ->sortBy(
                    function ($item) use (
                        $priority
                    ) {
                        $key =
                            $item[
                                'checkpoint'
                            ][
                                'classification_key'
                            ];

                        return [
                            $priority[$key]
                                ?? 99,

                            -(
                                $item[
                                    'checkpoint'
                                ]['days']
                                ?? 0
                            ),
                        ];
                    }
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Sort historical and issue rows
        |--------------------------------------------------------------------------
        */

        $historyRows =
            $historyRows
                ->sortByDesc(
                    fn ($item) =>
                        optional(
                            $item['rfa']
                                ->date_disposed
                        )->timestamp
                        ?? optional(
                            $item['rfa']
                                ->date_filed
                        )->timestamp
                        ?? 0
                )
                ->values();

        $dataIssues =
            $dataIssues
                ->sortByDesc(
                    fn ($item) =>
                        optional(
                            $item['rfa']
                                ->date_filed
                        )->timestamp
                        ?? 0
                )
                ->values();

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */

        $activePaginator =
            $this->paginateCollection(
                $activeTimers,
                20,
                'active_page',
                $request
            );

        $historyPaginator =
            $this->paginateCollection(
                $historyRows,
                20,
                'history_page',
                $request
            );

        $issuePaginator =
            $this->paginateCollection(
                $dataIssues,
                20,
                'issue_page',
                $request
            );

        /*
        |--------------------------------------------------------------------------
        | Total processing statistics
        |--------------------------------------------------------------------------
        */

        $processingSummary = [
            'count' =>
                $processingDurations->count(),

            'average' =>
                $processingDurations->isNotEmpty()
                    ? round(
                        $processingDurations
                            ->avg(),
                        1
                    )
                    : null,

            'minimum' =>
                $processingDurations->isNotEmpty()
                    ? $processingDurations
                        ->min()
                    : null,

            'maximum' =>
                $processingDurations->isNotEmpty()
                    ? $processingDurations
                        ->max()
                    : null,
        ];

        return view(
            'pct.index',
            [
                'asOf' => $asOf,

                'activeSummary' =>
                    $activeSummary,

                'historicalSummary' =>
                    $historicalSummary,

                'processingSummary' =>
                    $processingSummary,

                'dataIssueCount' =>
                    $dataIssues->count(),

                'activePaginator' =>
                    $activePaginator,

                'historyPaginator' =>
                    $historyPaginator,

                'issuePaginator' =>
                    $issuePaginator,

                'search' => $search,

                'stageFilter' =>
                    $stageFilter,

                'classificationFilter' =>
                    $classificationFilter,

                'checkpointDefinitions' =>
                    PctService::definitions(),

                'offices' => $offices,

                'officeFilter' => $officeFilter,

                'officeLabel' =>
                    array_search(
                        $officeFilter,
                        $offices,
                        true
                    ) ?: null,
            ]
        );
    }

    private function emptyClassificationSummary(): array
    {
        return [
            'completed' => 0,
            'within' => 0,
            'nearing' => 0,
            'on' => 0,
            'beyond' => 0,
            'compliant' => 0,
            'compliance_rate' => null,
        ];
    }

    private function addHistoricalResult(
        array &$summary,
        array $checkpoint
    ): void {
        if (
            $checkpoint['state']
            !== 'completed'
        ) {
            return;
        }

        $key =
            $checkpoint[
                'classification_key'
            ];

        $summary['completed']++;

        if (
            isset(
                $summary[$key]
            )
        ) {
            $summary[$key]++;
        }

        /*
        |--------------------------------------------------------------------------
        | Within the limit (including the deadline day) is compliant
        |--------------------------------------------------------------------------
        */

        if ($key !== 'beyond') {
            $summary['compliant']++;
        }
    }

    private function finalizeHistoricalSummary(
        array $summary
    ): array {
        if ($summary['completed'] > 0) {
            $summary['compliance_rate'] =
                round(
                    (
                        $summary['compliant']
                        /
                        $summary['completed']
                    )
                    * 100,
                    1
                );
        }

        return $summary;
    }

    private function paginateCollection(
        Collection $items,
        int $perPage,
        string $pageName,
        Request $request
    ): LengthAwarePaginator {
        $page =
            LengthAwarePaginator::
                resolveCurrentPage(
                    $pageName
                );

        $paginator =
            new LengthAwarePaginator(
                $items
                    ->forPage(
                        $page,
                        $perPage
                    )
                    ->values(),
                $items->count(),
                $perPage,
                $page,
                [
                    'path' =>
                        $request->url(),

                    'pageName' =>
                        $pageName,
                ]
            );

        return $paginator
            ->appends(
                $request->except(
                    $pageName
                )
            );
    }
}
