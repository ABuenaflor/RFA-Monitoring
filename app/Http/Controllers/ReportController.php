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

        $historicalStageOne =
            $this->emptyHistoricalSummary();

        $historicalStageTwo =
            $this->emptyHistoricalSummary();

        $processingDays = collect();

        foreach ($allRfas as $rfa) {
            $evaluation =
                $pctService->evaluate($rfa);

            $this->addActivePct(
                $activePct,
                $evaluation['stage_one']
            );

            $this->addActivePct(
                $activePct,
                $evaluation['stage_two']
            );

            $this->addHistoricalPct(
                $historicalStageOne,
                $evaluation['stage_one']
            );

            $this->addHistoricalPct(
                $historicalStageTwo,
                $evaluation['stage_two']
            );

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

        $historicalStageOne =
            $this->finalizeHistoricalSummary(
                $historicalStageOne
            );

        $historicalStageTwo =
            $this->finalizeHistoricalSummary(
                $historicalStageTwo
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

            'activePct' =>
                $activePct,

            'historicalStageOne' =>
                $historicalStageOne,

            'historicalStageTwo' =>
                $historicalStageTwo,

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

                        'Disposition Status',
                        'Disposition Mode',
                        'Date Disposed',

                        'Workers Involved',
                        'Workers Benefited',
                        'Monetary Benefit',

                        'PCT Stage 1 Status',
                        'PCT Stage 1 Days',

                        'PCT Stage 2 Status',
                        'PCT Stage 2 Days',

                        'Total Processing Days',
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

                    fputcsv(
                        $handle,
                        [
                            $rfa->reference_no,
                            $rfa->docket_no,
                            $rfa->office,

                            $rfa->requesting_party,
                            $rfa->responding_party,

                            $rfa->date_filed
                                ?->format(
                                    'Y-m-d'
                                ),

                            $rfa->source_case_status,
                            $rfa->status,
                            $rfa->monitoring_bucket,

                            $rfa->mode_of_filing,

                            $rfa->interviewer_name,

                            $rfa
                                ->date_assigned_interviewer
                                ?->format(
                                    'Y-m-d'
                                ),

                            $rfa->date_interview
                                ?->format(
                                    'Y-m-d'
                                ),

                            $rfa->seado_name,

                            $rfa
                                ->date_assigned_seado
                                ?->format(
                                    'Y-m-d'
                                ),

                            $rfa
                                ->date_initial_conference
                                ?->format(
                                    'Y-m-d'
                                ),

                            $rfa->disposition_status,
                            $rfa->disposition_mode,

                            $rfa->date_disposed
                                ?->format(
                                    'Y-m-d'
                                ),

                            $rfa->workers_involved,
                            $rfa->workers_benefited,
                            $rfa->monetary_benefit,

                            $this->pctLabel(
                                $evaluation[
                                    'stage_one'
                                ]
                            ),

                            $evaluation[
                                'stage_one'
                            ]['days'],

                            $this->pctLabel(
                                $evaluation[
                                    'stage_two'
                                ]
                            ),

                            $evaluation[
                                'stage_two'
                            ]['days'],

                            $evaluation[
                                'total_processing'
                            ]['days'],
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

    private function pctLabel(
        array $checkpoint
    ): string {
        if (
            in_array(
                $checkpoint['state'],
                [
                    'active',
                    'completed',
                ],
                true
            )
        ) {
            return (string) (
                $checkpoint[
                    'classification_label'
                ]
                ?? ''
            );
        }

        return match (
            $checkpoint['state']
        ) {
            'missing_start' =>
                'Start Date Missing',

            'missing_end' =>
                'Completion Date Missing',

            'invalid' =>
                'Invalid Dates',

            default =>
                'Unavailable',
        };
    }
}
