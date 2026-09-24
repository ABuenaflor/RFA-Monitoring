<?php

namespace App\Http\Controllers;

use App\Models\Rfa;
use App\Services\PctService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(
        Request $request,
        PctService $pctService
    ): View {
        $this->validateFilters($request);

        /*
        |--------------------------------------------------------------------------
        | Complete filtered dataset
        |--------------------------------------------------------------------------
        */

        $allRfas = $this
            ->filteredQuery($request)
            ->orderBy('date_filed')
            ->orderBy('id')
            ->get();



        /*
        |--------------------------------------------------------------------------
        | General report summary
        |--------------------------------------------------------------------------
        */

        $summary = [
            'total' =>
                $allRfas->count(),

            'pending' =>
                $allRfas
                    ->where(
                        'monitoring_bucket',
                        'pending'
                    )
                    ->count(),

            'ongoing' =>
                $allRfas
                    ->where(
                        'monitoring_bucket',
                        'ongoing'
                    )
                    ->count(),

            'disposed' =>
                $allRfas
                    ->where(
                        'monitoring_bucket',
                        'disposed'
                    )
                    ->count(),

            'monetary_benefit' =>
                $allRfas->sum(
                    fn (Rfa $rfa) =>
                        (float) (
                            $rfa->monetary_benefit
                            ?? 0
                        )
                ),

            'workers_involved' =>
                $allRfas->sum(
                    fn (Rfa $rfa) =>
                        (int) (
                            $rfa->workers_involved
                            ?? 0
                        )
                ),

            'workers_benefited' =>
                $allRfas->sum(
                    fn (Rfa $rfa) =>
                        (int) (
                            $rfa->workers_benefited
                            ?? 0
                        )
                ),
        ];

        /*
        |--------------------------------------------------------------------------
        | PCT report calculations
        |--------------------------------------------------------------------------
        */

        $activePct = $this->emptyPctSummary();

        $historicalPct = array_fill_keys(
            PctService::keys(),
            $this->emptyHistoricalSummary()
        );

        $processingDays = collect();

        foreach ($allRfas as $rfa) {
            $evaluation =
                $pctService->evaluate($rfa);

            foreach ($evaluation['checkpoints'] as $key => $checkpoint) {
                $this->addActivePct(
                    $activePct,
                    $checkpoint
                );

                $this->addHistoricalPct(
                    $historicalPct[$key],
                    $checkpoint
                );
            }

            if (
                $evaluation[
                    'total_processing'
                ]['state'] === 'completed'
            ) {
                $processingDays->push(
                    $evaluation[
                        'total_processing'
                    ]['days']
                );
            }
        }

        $historicalPct = array_map(
            fn (array $summary) => $this->finalizeHistoricalSummary($summary),
            $historicalPct
        );

        $processingSummary = [
            'count' =>
                $processingDays->count(),

            'average' =>
                $processingDays->isNotEmpty()
                    ? round(
                        $processingDays->avg(),
                        1
                    )
                    : null,

            'minimum' =>
                $processingDays->isNotEmpty()
                    ? $processingDays->min()
                    : null,

            'maximum' =>
                $processingDays->isNotEmpty()
                    ? $processingDays->max()
                    : null,
        ];
                /*
        |--------------------------------------------------------------------------
        | Selected SEADO workload summary
        |--------------------------------------------------------------------------
        |
        | $allRfas already contains all active report filters:
        |
        | - Date range
        | - Office
        | - SEADO
        | - Monitoring
        | - Workflow status
        | - Source status
        | - Disposition mode
        | - Search
        |
        */

        $selectedSeado = trim(
            (string) $request->query(
                'seado_name',
                ''
            )
        );

        $seadoSummary = null;

        if ($selectedSeado !== '') {
            $totalHandled =
                $allRfas->count();

            $disposed =
                $allRfas
                    ->where(
                        'monitoring_bucket',
                        'disposed'
                    )
                    ->count();

            $seadoSummary = [
                'name' =>
                    $selectedSeado,

                'total' =>
                    $totalHandled,

                'pending' =>
                    $allRfas
                        ->where(
                            'monitoring_bucket',
                            'pending'
                        )
                        ->count(),

                'ongoing' =>
                    $allRfas
                        ->where(
                            'monitoring_bucket',
                            'ongoing'
                        )
                        ->count(),

                'disposed' =>
                    $disposed,

                'disposition_rate' =>
                    $totalHandled > 0
                        ? round(
                            (
                                $disposed
                                /
                                $totalHandled
                            ) * 100,
                            1
                        )
                        : 0,

                'workers_involved' =>
                    $allRfas->sum(
                        fn (Rfa $rfa) =>
                            (int) (
                                $rfa->workers_involved
                                ?? 0
                            )
                    ),

                'workers_benefited' =>
                    $allRfas->sum(
                        fn (Rfa $rfa) =>
                            (int) (
                                $rfa->workers_benefited
                                ?? 0
                            )
                    ),

                'monetary_benefit' =>
                    $allRfas->sum(
                        fn (Rfa $rfa) =>
                            (float) (
                                $rfa->monetary_benefit
                                ?? 0
                            )
                    ),

                        'processing_count' =>
                            $processingSummary[
                                'count'
                            ],

                        'average_processing_days' =>
                            $processingSummary[
                                'average'
                            ],

                        'minimum_processing_days' =>
                            $processingSummary[
                                'minimum'
                            ],

                        'maximum_processing_days' =>
                            $processingSummary[
                                'maximum'
                            ],
                    ];
                }


        /*
|--------------------------------------------------------------------------
| Selected SEADO PCT performance
|--------------------------------------------------------------------------
*/

$seadoPctSummary = null;

if ($selectedSeado !== '') {
    $seadoPctSummary = array_fill_keys(
        PctService::keys(),
        $this->emptySeadoCheckpointSummary()
    );

    foreach ($allRfas as $rfa) {
        $evaluation =
            $pctService->evaluate($rfa);

        foreach ($evaluation['checkpoints'] as $key => $checkpoint) {
            $this->addSeadoCheckpointResult(
                $seadoPctSummary[$key],
                $checkpoint
            );
        }
    }

    $seadoPctSummary = array_map(
        fn (array $summary) => $this->finalizeSeadoCheckpointSummary($summary),
        $seadoPctSummary
    );
}
        /*
        |--------------------------------------------------------------------------
        | Management breakdowns
        |--------------------------------------------------------------------------
        */

        $officeBreakdown = $allRfas
            ->groupBy(
                fn (Rfa $rfa) =>
                    $rfa->office
                    ?: 'Unspecified'
            )
            ->map->count()
            ->sortDesc();

        $sourceStatusBreakdown = $allRfas
            ->groupBy(
                fn (Rfa $rfa) =>
                    $rfa->source_case_status
                    ?: 'Unspecified'
            )
            ->map->count()
            ->sortDesc();

      /*
|--------------------------------------------------------------------------
| Disposition reporting
|--------------------------------------------------------------------------
|
| disposition_status:
|     Official system final disposition.
|
| disposition_mode:
|     Raw/source disposition mode imported from the CSV.
|
| These must remain separate until an authoritative source-mode mapping
| is provided.
|
*/

$disposedRfas = $allRfas
    ->where(
        'monitoring_bucket',
        'disposed'
    );


$officialDispositionBreakdown =
    $disposedRfas
        ->filter(
            fn (Rfa $rfa) =>
                filled(
                    $rfa->disposition_status
                )
        )
        ->groupBy(
            fn (Rfa $rfa) =>
                $rfa->disposition_status
        )
        ->map->count()
        ->sortDesc();


$dispositionModeBreakdown =
    $disposedRfas
        ->filter(
            fn (Rfa $rfa) =>
                filled(
                    $rfa->disposition_mode
                )
        )
        ->groupBy(
            fn (Rfa $rfa) =>
                $rfa->disposition_mode
        )
        ->map->count()
        ->sortDesc();


$dispositionSummary = [
    'total_disposed' =>
        $disposedRfas->count(),

    'official_recorded' =>
        $officialDispositionBreakdown
            ->sum(),

    'official_missing' =>
        $disposedRfas
            ->filter(
                fn (Rfa $rfa) =>
                    blank(
                        $rfa->disposition_status
                    )
            )
            ->count(),

    'source_mode_recorded' =>
        $dispositionModeBreakdown
            ->sum(),
];

            /*
            |--------------------------------------------------------------------------
            | Conference analytics
            |--------------------------------------------------------------------------
            */

            $noConferenceCount =
                $allRfas
                    ->filter(
                        fn (Rfa $rfa) =>
                            $rfa->date_initial_conference === null
                            &&
                            $rfa->date_second_conference === null
                    )
                    ->count();


            $firstConferenceCount =
                $allRfas
                    ->filter(
                        fn (Rfa $rfa) =>
                            $rfa->date_initial_conference !== null
                    )
                    ->count();


            $firstOnlyConferenceCount =
                $allRfas
                    ->filter(
                        fn (Rfa $rfa) =>
                            $rfa->date_initial_conference !== null
                            &&
                            $rfa->date_second_conference === null
                    )
                    ->count();


            $secondConferenceCount =
                $allRfas
                    ->filter(
                        fn (Rfa $rfa) =>
                            $rfa->date_second_conference !== null
                    )
                    ->count();


            $disposedAfterFirstConference =
                $allRfas
                    ->filter(
                        function (Rfa $rfa): bool {
                            if (
                                $rfa->date_disposed === null
                                ||
                                $rfa->date_initial_conference === null
                            ) {
                                return false;
                            }

                            /*
                            * "After first conference" here means the
                            * RFA reached at least the first conference
                            * before or on the date it was disposed.
                            */
                            return $rfa
                                ->date_disposed
                                ->greaterThanOrEqualTo(
                                    $rfa->date_initial_conference
                                );
                        }
                    )
                    ->count();


            $disposedAfterSecondConference =
                $allRfas
                    ->filter(
                        function (Rfa $rfa): bool {
                            if (
                                $rfa->date_disposed === null
                                ||
                                $rfa->date_second_conference === null
                            ) {
                                return false;
                            }

                            return $rfa
                                ->date_disposed
                                ->greaterThanOrEqualTo(
                                    $rfa->date_second_conference
                                );
                        }
                    )
                    ->count();


            $conferenceIssues = $allRfas
                ->filter(
                    function (Rfa $rfa): bool {
                        /*
                        * Second conference exists but first conference
                        * is missing.
                        */
                        if (
                            $rfa->date_second_conference !== null
                            &&
                            $rfa->date_initial_conference === null
                        ) {
                            return true;
                        }

                        /*
                        * Second conference occurs before first.
                        */
                        if (
                            $rfa->date_initial_conference !== null
                            &&
                            $rfa->date_second_conference !== null
                            &&
                            $rfa
                                ->date_second_conference
                                ->lt(
                                    $rfa->date_initial_conference
                                )
                        ) {
                            return true;
                        }

                        /*
                        * First conference occurs after disposition.
                        */
                        if (
                            $rfa->date_initial_conference !== null
                            &&
                            $rfa->date_disposed !== null
                            &&
                            $rfa
                                ->date_initial_conference
                                ->gt(
                                    $rfa->date_disposed
                                )
                        ) {
                            return true;
                        }

                        /*
                        * Second conference occurs after disposition.
                        */
                        if (
                            $rfa->date_second_conference !== null
                            &&
                            $rfa->date_disposed !== null
                            &&
                            $rfa
                                ->date_second_conference
                                ->gt(
                                    $rfa->date_disposed
                                )
                        ) {
                            return true;
                        }

                        return false;
                    }
                )
                ->count();


            $conferenceSummary = [
                'no_conference' =>
                    $noConferenceCount,

                'first_conference' =>
                    $firstConferenceCount,

                'first_only' =>
                    $firstOnlyConferenceCount,

                'second_conference' =>
                    $secondConferenceCount,

                'disposed_after_first' =>
                    $disposedAfterFirstConference,

                'disposed_after_second' =>
                    $disposedAfterSecondConference,

                'data_issues' =>
                    $conferenceIssues,
            ];

        /*
        |--------------------------------------------------------------------------
        | Paginated report records
        |--------------------------------------------------------------------------
        */

        $records = $this
            ->filteredQuery($request)
            ->orderByDesc('date_filed')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | PCT values for records on current page
        |--------------------------------------------------------------------------
        */

        $recordPct = $records
            ->getCollection()
            ->mapWithKeys(
                fn (Rfa $rfa) => [
                    $rfa->id =>
                        $pctService->evaluate(
                            $rfa
                        ),
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | Filter options
        |--------------------------------------------------------------------------
        */

        $offices = Rfa::query()
            ->whereNotNull('office')
            ->where('office', '!=', '')
            ->distinct()
            ->orderBy('office')
            ->pluck('office');

                    /*
        |--------------------------------------------------------------------------
        | SEADO filter options
        |--------------------------------------------------------------------------
        */

        $seados = Rfa::query()
            ->whereNotNull('seado_name')
            ->where('seado_name', '!=', '')
            ->distinct()
            ->orderBy('seado_name')
            ->pluck('seado_name');

        $workflowStatuses = Rfa::query()
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $sourceStatuses = Rfa::query()
            ->whereNotNull(
                'source_case_status'
            )
            ->where(
                'source_case_status',
                '!=',
                ''
            )
            ->distinct()
            ->orderBy(
                'source_case_status'
            )
            ->pluck(
                'source_case_status'
            );

            $dispositionStatuses = Rfa::query()
                ->whereNotNull(
                    'disposition_status'
            )
            ->where(
                'disposition_status',
                '!=',
                ''
            )
            ->distinct()
            ->orderBy(
                'disposition_status'
            )
            ->pluck(
                'disposition_status'
            );

        $dispositionModes = Rfa::query()
            ->whereNotNull(
                'disposition_mode'
            )
            ->where(
                'disposition_mode',
                '!=',
                ''
            )
            ->distinct()
            ->orderBy(
                'disposition_mode'
            )
            ->pluck(
                'disposition_mode'
            );

        return view('reports.index', [
            'seados' =>
                $seados,

            'selectedSeado' =>
                $selectedSeado,

            'seadoSummary' =>
                $seadoSummary,
            'summary' =>
                $summary,

            'seadoPctSummary' =>
            $seadoPctSummary,

            'activePct' =>
                $activePct,

            'historicalPct' =>
                $historicalPct,

            'checkpointDefinitions' =>
                PctService::definitions(),

            'processingSummary' =>
                $processingSummary,

            'officeBreakdown' =>
                $officeBreakdown,

            'sourceStatusBreakdown' =>
                $sourceStatusBreakdown,

            'officialDispositionBreakdown' =>
                $officialDispositionBreakdown,

            'dispositionSummary' =>
                $dispositionSummary,

            'conferenceSummary' =>
            $conferenceSummary,

            'dispositionStatuses' =>
                $dispositionStatuses,

            'dispositionModeBreakdown' =>
                $dispositionModeBreakdown,

            'records' =>
                $records,

            'recordPct' =>
                $recordPct,

            'offices' =>
                $offices,

            'workflowStatuses' =>
                $workflowStatuses,

            'sourceStatuses' =>
                $sourceStatuses,

            'dispositionModes' =>
                $dispositionModes,
        ]);
    }

public function print(
    Request $request,
    PctService $pctService
) {
    $this->validateFilters(
        $request
    );

    /*
    |--------------------------------------------------------------------------
    | Apply exactly the same report filters
    |--------------------------------------------------------------------------
    */

    $query =
        $this->filteredQuery(
            $request
        );

    $rfas =
        $query
            ->orderBy(
                'date_filed'
            )
            ->orderBy('id')
            ->get();


            /*
            |--------------------------------------------------------------------------
            | PCT evaluations for every printed record
            |--------------------------------------------------------------------------
            */

            $recordPct =
                $rfas->mapWithKeys(
                    function ($rfa) use (
                        $pctService
                    ) {
                        return [
                            $rfa->id =>
                                $pctService
                                    ->evaluate(
                                        $rfa
                                    ),
                        ];
                    }
                );


            /*
            |--------------------------------------------------------------------------
            | Basic report summary
            |--------------------------------------------------------------------------
            */

            $summary = [
                'total' =>
                    $rfas->count(),

                'pending' =>
                    $rfas
                        ->where(
                            'monitoring_bucket',
                            'pending'
                        )
                        ->count(),

                'ongoing' =>
                    $rfas
                        ->where(
                            'monitoring_bucket',
                            'ongoing'
                        )
                        ->count(),

                'disposed' =>
                    $rfas
                        ->where(
                            'monitoring_bucket',
                            'disposed'
                        )
                        ->count(),

                'workers_involved' =>
                    (int) $rfas->sum(
                        'workers_involved'
                    ),

                'workers_benefited' =>
                    (int) $rfas->sum(
                        'workers_benefited'
                    ),

                'monetary_benefit' =>
                    (float) $rfas->sum(
                        'monetary_benefit'
                    ),
            ];


            /*
            |--------------------------------------------------------------------------
            | 1st Conference - Date Disposed (30 days)
            |--------------------------------------------------------------------------
            */

            $dispositionCheckpoint = $this->emptySeadoCheckpointSummary();

            foreach ($recordPct as $pct) {
                $this->addSeadoCheckpointResult(
                    $dispositionCheckpoint,
                    $pct['checkpoints'][PctService::CONFERENCE_DISPOSED]
                );
            }

            $dispositionCheckpoint = $this->finalizeSeadoCheckpointSummary(
                $dispositionCheckpoint
            );

            $dispositionPctSummary = [
                'disposed_within' =>
                    $dispositionCheckpoint['compliant'],

                'disposed_beyond' =>
                    $dispositionCheckpoint['beyond'],

                'compliance_rate' =>
                    $dispositionCheckpoint['compliance_rate'],
            ];


            /*
            |--------------------------------------------------------------------------
            | Conference monitoring
            |--------------------------------------------------------------------------
            */

            $conferenceIssues =
                $rfas->filter(
                    function ($rfa) {
                        if (
                            $rfa
                                ->date_second_conference
                            &&
                            ! $rfa
                                ->date_initial_conference
                        ) {
                            return true;
                        }

                        if (
                            $rfa
                                ->date_second_conference
                            &&
                            $rfa
                                ->date_initial_conference
                            &&
                            $rfa
                                ->date_second_conference
                                ->lt(
                                    $rfa
                                        ->date_initial_conference
                                )
                        ) {
                            return true;
                        }

                        if (
                            $rfa
                                ->date_initial_conference
                            &&
                            $rfa
                                ->date_disposed
                            &&
                            $rfa
                                ->date_initial_conference
                                ->gt(
                                    $rfa
                                        ->date_disposed
                                )
                        ) {
                            return true;
                        }

                        if (
                            $rfa
                                ->date_second_conference
                            &&
                            $rfa
                                ->date_disposed
                            &&
                            $rfa
                                ->date_second_conference
                                ->gt(
                                    $rfa
                                        ->date_disposed
                                )
                        ) {
                            return true;
                        }

                        return false;
                    }
                )
                ->count();


            $conferenceSummary = [
                'no_conference' =>
                    $rfas->filter(
                        fn ($rfa) =>
                            ! $rfa
                                ->date_initial_conference
                            &&
                            ! $rfa
                                ->date_second_conference
                    )->count(),

                'first_conference' =>
                    $rfas
                        ->whereNotNull(
                            'date_initial_conference'
                        )
                        ->count(),

                'first_only' =>
                    $rfas->filter(
                        fn ($rfa) =>
                            $rfa
                                ->date_initial_conference
                            &&
                            ! $rfa
                                ->date_second_conference
                    )->count(),

                'second_conference' =>
                    $rfas
                        ->whereNotNull(
                            'date_second_conference'
                        )
                        ->count(),

                'disposed_after_first' =>
                    $rfas->filter(
                        fn ($rfa) =>
                            $rfa
                                ->date_initial_conference
                            &&
                            $rfa
                                ->date_disposed
                            &&
                            $rfa
                                ->date_disposed
                                ->gte(
                                    $rfa
                                        ->date_initial_conference
                                )
                    )->count(),

                'disposed_after_second' =>
                    $rfas->filter(
                        fn ($rfa) =>
                            $rfa
                                ->date_second_conference
                            &&
                            $rfa
                                ->date_disposed
                            &&
                            $rfa
                                ->date_disposed
                                ->gte(
                                    $rfa
                                        ->date_second_conference
                                )
                    )->count(),

                'data_issues' =>
                    $conferenceIssues,
            ];


            /*
            |--------------------------------------------------------------------------
            | Selected SEADO
            |--------------------------------------------------------------------------
            */

            $selectedSeado =
                trim(
                    (string)
                    $request->query(
                        'seado_name',
                        ''
                    )
                );


            return view(
                'reports.print',
                [
                    'rfas' =>
                        $rfas,

                    'recordPct' =>
                        $recordPct,

                    'summary' =>
                        $summary,

                    'dispositionPctSummary' =>
                        $dispositionPctSummary,

                    'conferenceSummary' =>
                        $conferenceSummary,

                    'selectedSeado' =>
                        $selectedSeado,
                ]
            );
        }


    /**
     * Export the currently filtered report.
     */
    public function exportCsv(
        Request $request,
        PctService $pctService
    ): StreamedResponse {
        $this->validateFilters($request);

        $query = $this
            ->filteredQuery($request)
            ->orderBy('date_filed')
            ->orderBy('id');

        $filename =
            'rfa-report-'
            .now()->format(
                'Ymd-His'
            )
            .'.csv';

        return response()->streamDownload(
            function () use (
                $query,
                $pctService
            ) {
                $handle = fopen(
                    'php://output',
                    'w'
                );

                /*
                |--------------------------------------------------------------------------
                | UTF-8 BOM for Excel compatibility
                |--------------------------------------------------------------------------
                */

                fwrite(
                    $handle,
                    "\xEF\xBB\xBF"
                );

                $pctHeaders = [];

                foreach (PctService::definitions() as $definition) {
                    foreach (['Status', 'Days', 'Deadline', 'Overdue Days'] as $part) {
                        $pctHeaders[] = 'PCT ' . $definition['label'] . ' ' . $part;
                    }
                }

                fputcsv(
                    $handle,
                    [
                        'System Reference No.',
                        'Docket No.',
                        'Office',

                        'Requesting Party',
                        'Responding Party',

                        'Date Filed',

                        'Source Case Status',
                        'Workflow Status',
                        'Monitoring Bucket',

                        'Mode of Filing',

                        'Interviewer',
                        'Date Assigned to Interviewer',
                        'Date of Interview',

                        'SEADO',
                        'Date Assigned to SEADO',

                        'Initial Conference',
                        'Second Conference',

                        'Disposition Status',
                        'Source Disposition Mode',
                        'Date Disposed',

                        ...$pctHeaders,

                        'Total Processing Days',

                        'Workers Involved',
                        'Workers Benefited',
                        'Monetary Benefit',
                    ]
                );

                foreach (
                    $query->cursor()
                    as $rfa
                ) {
                    $evaluation =
                        $pctService->evaluate(
                            $rfa
                        );

                    $pctValues = [];

                    foreach ($evaluation['checkpoints'] as $checkpoint) {
                        $pctValues[] = PctService::statusLabel($checkpoint);
                        $pctValues[] = $checkpoint['days'];
                        $pctValues[] = $checkpoint['deadline']?->format('Y-m-d');
                        $pctValues[] = $checkpoint['overdue_days'];
                    }

                    fputcsv(
                        $handle,
                        [
                            $rfa->reference_no,
                            $rfa->docket_no,
                            $rfa->office,

                            $rfa->requesting_party,
                            $rfa->responding_party,

                            $rfa->date_filed
                                ?->format('Y-m-d'),

                            $rfa->source_case_status,
                            $rfa->status,
                            $rfa->monitoring_bucket,

                            $rfa->mode_of_filing,

                            $rfa->interviewer_name,

                            $rfa
                                ->date_assigned_interviewer
                                ?->format('Y-m-d'),

                            $rfa
                                ->date_interview
                                ?->format('Y-m-d'),

                            $rfa->seado_name,

                            $rfa
                                ->date_assigned_seado
                                ?->format('Y-m-d'),

                            $rfa
                                ->date_initial_conference
                                ?->format('Y-m-d'),

                            $rfa
                                ->date_second_conference
                                ?->format('Y-m-d'),

                            $rfa->disposition_status,
                            $rfa->disposition_mode,

                            $rfa
                                ->date_disposed
                                ?->format('Y-m-d'),

                            ...$pctValues,

                            $evaluation['total_processing']['days'],

                            $rfa->workers_involved,
                            $rfa->workers_benefited,
                            $rfa->monetary_benefit,
                        ]
                    );
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' =>
                    'text/csv; charset=UTF-8',
            ]
        );
    }

    /**
     * Build filtered RFA query.
     */
    private function filteredQuery(
        Request $request
    ): Builder {
        $query = Rfa::query();

        $search = trim(
            (string) $request->query(
                'search',
                ''
            )
        );

        if ($search !== '') {
            $query->where(
                function (
                    Builder $query
                ) use ($search) {
                    $query
                        ->where(
                            'reference_no',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'docket_no',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'requesting_party',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'responding_party',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'interviewer_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'seado_name',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (
            $request->filled(
                'date_from'
            )
        ) {
            $query->whereDate(
                'date_filed',
                '>=',
                $request->query(
                    'date_from'
                )
            );
        }

        if (
            $request->filled(
                'date_to'
            )
        ) {
            $query->whereDate(
                'date_filed',
                '<=',
                $request->query(
                    'date_to'
                )
            );
        }

        if (
            $request->filled(
                'office'
            )
        ) {
            $query->where(
                'office',
                $request->query(
                    'office'
                )
            );
        }
                /*
        |--------------------------------------------------------------------------
        | SEADO filter
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'seado_name'
            )
        ) {
            $query->where(
                'seado_name',
                $request->query(
                    'seado_name'
                )
            );
        }

        if (
            $request->filled(
                'monitoring_bucket'
            )
        ) {
            $query->where(
                'monitoring_bucket',
                $request->query(
                    'monitoring_bucket'
                )
            );
        }

        if (
            $request->filled(
                'status'
            )
        ) {
            $query->where(
                'status',
                $request->query(
                    'status'
                )
            );
        }

        if (
            $request->filled(
                'source_case_status'
            )
        ) {
            $query->where(
                'source_case_status',
                $request->query(
                    'source_case_status'
                )
            );
        }

                /*
        |--------------------------------------------------------------------------
        | Conference filter
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'conference_level'
            )
        ) {
            match (
                $request->query(
                    'conference_level'
                )
            ) {
                'none' =>
                    $query
                        ->whereNull(
                            'date_initial_conference'
                        )
                        ->whereNull(
                            'date_second_conference'
                        ),

                'first_only' =>
                    $query
                        ->whereNotNull(
                            'date_initial_conference'
                        )
                        ->whereNull(
                            'date_second_conference'
                        ),

                'second' =>
                    $query
                        ->whereNotNull(
                            'date_second_conference'
                        ),

                default => null,
            };
        }
                /*
        |--------------------------------------------------------------------------
        | Official final disposition
        |--------------------------------------------------------------------------
        */

        if (
            $request->filled(
                'disposition_status'
            )
        ) {
            $query->where(
                'disposition_status',
                $request->query(
                    'disposition_status'
                )
            );
        }

        if (
            $request->filled(
                'disposition_mode'
            )
        ) {
            $query->where(
                'disposition_mode',
                $request->query(
                    'disposition_mode'
                )
            );
        }

        return $query;
    }

    private function validateFilters(
        Request $request
    ): void {
        $request->validate([
            'search' =>
                ['nullable', 'string', 'max:255'],

            'date_from' =>
                ['nullable', 'date'],

            'date_to' =>
                [
                    'nullable',
                    'date',
                    'after_or_equal:date_from',
                ],

            'office' =>
                ['nullable', 'string', 'max:100'],

                'seado_name' =>
                ['nullable', 'string', 'max:150'],

            'conference_level' =>
                [
                    'nullable',
                    'in:none,first_only,second',
                ],

            'monitoring_bucket' =>
                [
                    'nullable',
                    'in:pending,ongoing,disposed',
                ],

            'status' =>
                ['nullable', 'string', 'max:100'],

            'source_case_status' =>
                ['nullable', 'string', 'max:100'],

            'disposition_status' =>
            ['nullable', 'string', 'max:100'],

            'disposition_mode' =>
                ['nullable', 'string', 'max:100'],
        ]);
    }

    private function emptyPctSummary(): array
    {
        return [
            'total' => 0,
            'within' => 0,
            'nearing' => 0,
            'on' => 0,
            'beyond' => 0,
        ];
    }

    private function emptyHistoricalSummary(): array
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
    private function emptySeadoCheckpointSummary(): array
{
    return [
        /*
        |--------------------------------------------------------------------------
        | Active checkpoint states
        |--------------------------------------------------------------------------
        */

        'active' => 0,

        'active_within' => 0,
        'active_nearing' => 0,
        'active_on' => 0,
        'active_beyond' => 0,


        /*
        |--------------------------------------------------------------------------
        | Historical completed states
        |--------------------------------------------------------------------------
        */

        'completed' => 0,

        'within' => 0,
        'nearing' => 0,
        'on' => 0,
        'beyond' => 0,

        'compliant' => 0,

        'indeterminate' => 0,

        'compliance_rate' => null,
    ];
}

private function addSeadoCheckpointResult(
    array &$summary,
    array $checkpoint
): void {
    $state =
        $checkpoint['state']
        ?? null;


    /*
    |--------------------------------------------------------------------------
    | Active checkpoint
    |--------------------------------------------------------------------------
    */

    if ($state === 'active') {
        $summary['active']++;

        $classification =
            $checkpoint[
                'classification_key'
            ]
            ?? null;

        $activeKey =
            $classification
                ? 'active_'
                    .$classification
                : null;

        if (
            $activeKey
            &&
            array_key_exists(
                $activeKey,
                $summary
            )
        ) {
            $summary[$activeKey]++;
        }

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Historical completed checkpoint
    |--------------------------------------------------------------------------
    */

    if ($state === 'completed') {
        $summary['completed']++;

        $classification =
            $checkpoint[
                'classification_key'
            ]
            ?? null;

        if (
            $classification
            &&
            array_key_exists(
                $classification,
                $summary
            )
        ) {
            $summary[
                $classification
            ]++;
        }

        /*
         * Within, Nearing and On PCT are
         * all compliant because they are
         * completed within the checkpoint limit.
         */

        if (
            in_array(
                $classification,
                [
                    'within',
                    'nearing',
                    'on',
                ],
                true
            )
        ) {
            $summary['compliant']++;
        }

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Incomplete / invalid historical information
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $state,
            [
                'missing_start',
                'missing_end',
                'invalid',
            ],
            true
        )
    ) {
        $summary[
            'indeterminate'
        ]++;
    }
}

private function finalizeSeadoCheckpointSummary(
    array $summary
): array {
    if (
        $summary['completed']
        > 0
    ) {
        $summary[
            'compliance_rate'
        ] = round(
            (
                $summary[
                    'compliant'
                ]
                /
                $summary[
                    'completed'
                ]
            ) * 100,
            1
        );
    }

    return $summary;
}

    private function addActivePct(
        array &$summary,
        array $checkpoint
    ): void {
        if (
            $checkpoint['state']
            !== 'active'
        ) {
            return;
        }

        $key =
            $checkpoint[
                'classification_key'
            ];

        $summary['total']++;

        if (
            isset(
                $summary[$key]
            )
        ) {
            $summary[$key]++;
        }
    }

    private function addHistoricalPct(
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

        if ($key !== 'beyond') {
            $summary['compliant']++;
        }
    }

    private function finalizeHistoricalSummary(
        array $summary
    ): array {
        if ($summary['completed'] > 0) {
            $summary[
                'compliance_rate'
            ] = round(
                (
                    $summary['compliant']
                    /
                    $summary['completed']
                ) * 100,
                1
            );
        }

        return $summary;
    }
}
