<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\Rfa;
use App\Services\DataQualityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GovernanceController extends Controller
{
    public function __construct(
        private readonly DataQualityService $dataQuality
    ) {
    }

    public function index(): View
    {
        return view('admin.governance.index', [
            'findings' => $this->dataQuality->findings(),

            'summary' => $this->dataQuality->summary(),

            'batches' => $this->importHistory(),
        ]);
    }

    /**
     * Findings as a CSV, so they can be worked through offline.
     */
    public function export(): StreamedResponse
    {
        $findings = $this->dataQuality->findings();

        $filename = 'rfa-data-quality-'
            . now()->format('Y-m-d')
            . '.csv';

        return response()->streamDownload(
            function () use ($findings): void {
                $handle = fopen('php://output', 'w');

                fputcsv($handle, [
                    'Check',
                    'Severity',
                    'Affected Records',
                    'Description',
                    'Impact',
                    'Sample References',
                ]);

                foreach ($findings as $finding) {
                    fputcsv($handle, [
                        $finding['label'],

                        $finding['severity'],

                        $finding['count'],

                        $finding['description'],

                        $finding['impact'],

                        implode(
                            ' | ',
                            array_map(
                                fn ($sample) => $sample->reference_no,
                                $finding['samples']
                            )
                        ),
                    ]);
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' => 'text/csv',
            ]
        );
    }

    /**
     * Import history assembled from the records themselves, so batches that
     * predate the import_batches table still appear.
     *
     * @return array<int, array<string, mixed>>
     */
    private function importHistory(): array
    {
        $grouped = Rfa::query()
            ->select([
                'import_batch_uuid',

                DB::raw('COUNT(*) as record_count'),

                DB::raw('MIN(created_at) as first_seen'),

                DB::raw('MAX(updated_at) as last_touched'),
            ])
            ->whereNotNull('import_batch_uuid')
            ->groupBy('import_batch_uuid')
            ->orderByRaw('MIN(created_at) DESC')
            ->limit(25)
            ->get();

        $metadata = ImportBatch::query()
            ->with('user')
            ->whereIn(
                'uuid',
                $grouped->pluck('import_batch_uuid')
            )
            ->get()
            ->keyBy('uuid');

        return $grouped
            ->map(function ($row) use ($metadata) {
                $batch = $metadata->get($row->import_batch_uuid);

                return [
                    'uuid' => $row->import_batch_uuid,

                    'record_count' => (int) $row->record_count,

                    'first_seen' => $row->first_seen,

                    'last_touched' => $row->last_touched,

                    'file_name' => $batch?->file_name,

                    'user_name' => $batch?->user?->name,

                    'rows_processed' => $batch?->rows_processed,

                    'duplicates_skipped' => $batch?->duplicates_skipped,
                ];
            })
            ->all();
    }
}
