<?php

namespace App\Http\Controllers;

use App\Models\Rfa;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RfaImportController extends Controller
{
    /**
     * Display the CSV import page and optional imported batch preview.
     */
    public function index(Request $request): View
    {
        $batch = $request->query('batch');

        $perPage = (string) $request->query(
            'per_page',
            '10'
        );

        if (! in_array(
            $perPage,
            ['10', '20', 'all'],
            true
        )) {
            $perPage = '10';
        }

        $rows = collect();
        $paginator = null;
        $previewHeaders = [];
        $totalImported = 0;

        if ($batch) {
            $query = Rfa::query()
                ->where(
                    'import_batch_uuid',
                    $batch
                )
                ->orderBy('id');

            $totalImported = (clone $query)
                ->count();

            $firstRecord = (clone $query)
                ->first();

            if ($firstRecord) {
                $previewHeaders = array_keys(
                    $firstRecord->import_payload ?? []
                );
            }

            if ($perPage === 'all') {
                $rows = $query->get();
            } else {
                $paginator = $query
                    ->paginate((int) $perPage)
                    ->withQueryString();

                $rows = $paginator
                    ->getCollection();
            }
        }

        return view('imports.index', [
            'batch' => $batch,
            'rows' => $rows,
            'paginator' => $paginator,
            'previewHeaders' => $previewHeaders,
            'perPage' => $perPage,
            'totalImported' => $totalImported,
        ]);
    }

    /**
     * Import a CSV into the RFA table.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => [
                'required',
                'file',
                'max:20480',
            ],
        ]);

        $file = $request->file('csv_file');

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        if (! in_array(
            $extension,
            ['csv', 'txt'],
            true
        )) {
            throw ValidationException::withMessages([
                'csv_file' =>
                    'The uploaded file must be a CSV file.',
            ]);
        }

        $handle = fopen(
            $file->getRealPath(),
            'r'
        );

        if (! $handle) {
            throw ValidationException::withMessages([
                'csv_file' =>
                    'The CSV file could not be opened.',
            ]);
        }

        $headerRow = fgetcsv($handle);

        if ($headerRow === false) {
            fclose($handle);

            throw ValidationException::withMessages([
                'csv_file' =>
                    'The CSV file does not contain a header row.',
            ]);
        }

        $headers = array_map(
            fn ($header) => $this->cleanHeader($header),
            $headerRow
        );

        $normalizedHeaders = array_map(
            fn ($header) =>
                $this->normalizeHeader($header),
            $headers
        );

        /*
        |--------------------------------------------------------------------------
        | Prevent duplicate CSV column names
        |--------------------------------------------------------------------------
        */

        if (
            count($normalizedHeaders) !==
            count(array_unique($normalizedHeaders))
        ) {
            fclose($handle);

            throw ValidationException::withMessages([
                'csv_file' =>
                    'The CSV contains duplicate column names.',
            ]);
        }

        $batchUuid = (string) Str::uuid();

        $batchShort = strtoupper(
            substr(
                str_replace(
                    '-',
                    '',
                    $batchUuid
                ),
                0,
                8
            )
        );

        $imported = 0;
        $created = 0;
        $updated = 0;

        DB::beginTransaction();

        try {
            while (
                ($csvRow = fgetcsv($handle))
                !== false
            ) {
                if ($this->isEmptyRow($csvRow)) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | Make row length match header length
                |--------------------------------------------------------------------------
                */

                $csvRow = array_pad(
                    $csvRow,
                    count($headers),
                    null
                );

                $csvRow = array_slice(
                    $csvRow,
                    0,
                    count($headers)
                );

                /*
                |--------------------------------------------------------------------------
                | Preserve original CSV data
                |--------------------------------------------------------------------------
                */

                $payload = [];

                foreach (
                    $headers as $index => $header
                ) {
                    $payload[$header] =
                        $csvRow[$index] ?? null;
                }

                /*
                |--------------------------------------------------------------------------
                | Create normalized representation
                |--------------------------------------------------------------------------
                */

                $normalizedRow = [];

                foreach (
                    $normalizedHeaders
                    as $index => $header
                ) {
                    $normalizedRow[$header] =
                        trim(
                            (string) (
                                $csvRow[$index] ?? ''
                            )
                        );
                }

                $sequence = $imported + 1;

                $mapped = $this->mapRow(
                    $normalizedRow,
                    $payload,
                    $batchUuid,
                    $batchShort,
                    $sequence
                );

                $referenceNo =
                    $mapped['reference_no'];

                unset(
                    $mapped['reference_no']
                );

                $rfa = Rfa::updateOrCreate(
                    [
                        'reference_no' =>
                            $referenceNo,
                    ],
                    $mapped
                );

                if ($rfa->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }

                $imported++;
            }

            if ($imported === 0) {
                throw ValidationException::withMessages([
                    'csv_file' =>
                        'The CSV file contains no importable data rows.',
                ]);
            }

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            fclose($handle);

            if (
                $exception
                instanceof ValidationException
            ) {
                throw $exception;
            }

            report($exception);

            throw ValidationException::withMessages([
                'csv_file' =>
                    'The CSV import failed. No records were saved.',
            ]);
        }

        fclose($handle);

        return redirect()
            ->route(
                'imports.index',
                [
                    'batch' => $batchUuid,
                    'per_page' => 10,
                ]
            )
            ->with(
                'success',
                "{$imported} records processed. "
                ."{$created} created and "
                ."{$updated} updated."
            );
    }

    /**
     * Convert one CSV row to system fields.
     */
    private function mapRow(
        array $row,
        array $payload,
        string $batchUuid,
        string $batchShort,
        int $sequence
    ): array {
        $referenceNo = $this->firstValue(
            $row,
            [
                'reference_no',
                'reference_number',
                'rfa_no',
                'rfa_number',
                'rfa_reference_no',
                'rfa_reference_number',
            ]
        );

        if (! $referenceNo) {
            $referenceNo = sprintf(
                'RFA-IMP-%s-%05d',
                $batchShort,
                $sequence
            );
        }

        $requestingParty =
            $this->firstValue(
                $row,
                [
                    'requesting_party',
                    'requesting_party_name',
                    'complainant',
                    'complainant_name',
                ]
            );

        $respondingParty =
            $this->firstValue(
                $row,
                [
                    'responding_party',
                    'responding_party_name',
                    'employer',
                    'employer_name',
                    'company',
                    'company_name',
                ]
            );

        $rawStatus = $this->firstValue(
            $row,
            [
                'status',
                'rfa_status',
                'current_status',
            ]
        );

        $status = $this->normalizeWorkflowStatus(
            $rawStatus
        );

        $disposition = $this->firstValue(
            $row,
            [
                'disposition',
                'disposition_status',
                'final_disposition',
            ]
        );

        $disposition =
            $this->normalizeNullableToken(
                $disposition
            );

        $dateDisposed =
            $this->normalizeDate(
                $this->firstValue(
                    $row,
                    [
                        'date_disposed',
                        'disposed_date',
                    ]
                )
            );

        $explicitBucket =
            $this->firstValue(
                $row,
                [
                    'monitoring_bucket',
                    'monitoring_status',
                    'bucket',
                ]
            );

        $monitoringBucket =
            $this->determineMonitoringBucket(
                $explicitBucket,
                $status,
                $disposition,
                $dateDisposed
            );

        return [
            'reference_no' =>
                $referenceNo,

            'requesting_party' =>
                $requestingParty,

            'responding_party' =>
                $respondingParty,

            'status' =>
                $status,

            'monitoring_bucket' =>
                $monitoringBucket,

            'mode_of_filing' =>
                $this->normalizeNullableToken(
                    $this->firstValue(
                        $row,
                        [
                            'mode_of_filing',
                            'filing_mode',
                            'mode_filed',
                        ]
                    )
                ),

            'date_filed' =>
                $this->normalizeDate(
                    $this->firstValue(
                        $row,
                        [
                            'date_filed',
                            'filing_date',
                        ]
                    )
                ),

            'date_assigned_interviewer' =>
                $this->normalizeDate(
                    $this->firstValue(
                        $row,
                        [
                            'date_assigned_interviewer',
                            'interviewer_assignment_date',
                            'date_assigned_to_interviewer',
                        ]
                    )
                ),

            'date_interview' =>
                $this->normalizeDate(
                    $this->firstValue(
                        $row,
                        [
                            'date_interview',
                            'date_of_interview',
                            'interview_date',
                        ]
                    )
                ),

            'date_validated' =>
                $this->normalizeDate(
                    $this->firstValue(
                        $row,
                        [
                            'date_validated',
                            'validation_date',
                        ]
                    )
                ),

            'date_turned_over_lr' =>
                $this->normalizeDate(
                    $this->firstValue(
                        $row,
                        [
                            'date_turned_over_lr',
                            'date_turned_over_to_lr',
                            'lr_turnover_date',
                        ]
                    )
                ),

            'date_assigned_seado' =>
                $this->normalizeDate(
                    $this->firstValue(
                        $row,
                        [
                            'date_assigned_seado',
                            'seado_assignment_date',
                            'date_assigned_to_seado',
                        ]
                    )
                ),

            'disposition_status' =>
                $disposition,

            'date_disposed' =>
                $dateDisposed,

            'import_batch_uuid' =>
                $batchUuid,

            'import_payload' =>
                $payload,
        ];
    }

    /**
     * Determine dashboard monitoring category.
     */
    private function determineMonitoringBucket(
        ?string $explicitBucket,
        string $status,
        ?string $disposition,
        ?string $dateDisposed
    ): string {
        if ($explicitBucket) {
            $bucket = $this->normalizeToken(
                $explicitBucket
            );

            if (
                in_array(
                    $bucket,
                    [
                        'pending',
                        'ongoing',
                        'disposed',
                    ],
                    true
                )
            ) {
                return $bucket;
            }
        }

        if (
            $status === 'disposed'
            || $disposition
            || $dateDisposed
        ) {
            return 'disposed';
        }

        if (
            in_array(
                $status,
                [
                    'newly_filed',
                    'for_interviewer_assignment',
                ],
                true
            )
        ) {
            return 'pending';
        }

        return 'ongoing';
    }

    /**
     * Normalize workflow status.
     */
    private function normalizeWorkflowStatus(
        ?string $value
    ): string {
        if (! $value) {
            return 'newly_filed';
        }

        $status = $this->normalizeToken(
            $value
        );

        return match ($status) {
            'pending' =>
                'for_interviewer_assignment',

            'new',
            'newly_filed' =>
                'newly_filed',

            'for_interviewer_assignment' =>
                'for_interviewer_assignment',

            'for_validation' =>
                'for_validation',

            'validated' =>
                'validated',

            'for_turnover' =>
                'for_turnover',

            'for_seado_assignment' =>
                'for_seado_assignment',

            'assigned_to_seado' =>
                'assigned_to_seado',

            'for_notice_preparation' =>
                'for_notice_preparation',

            'for_conference' =>
                'for_conference',

            'ongoing' =>
                'ongoing',

            'for_disposition' =>
                'for_disposition',

            'disposed' =>
                'disposed',

            default =>
                $status,
        };
    }

    /**
     * Return the first populated value from alias fields.
     */
    private function firstValue(
        array $row,
        array $aliases
    ): ?string {
        foreach ($aliases as $alias) {
            if (
                isset($row[$alias])
                && trim($row[$alias]) !== ''
            ) {
                return trim(
                    $row[$alias]
                );
            }
        }

        return null;
    }

    /**
     * Normalize CSV column name.
     */
    private function normalizeHeader(
        ?string $header
    ): string {
        return $this->normalizeToken(
            $this->cleanHeader($header)
        );
    }

    /**
     * Remove BOM and surrounding whitespace.
     */
    private function cleanHeader(
        ?string $header
    ): string {
        $header = (string) $header;

        $header = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $header
        );

        return trim($header);
    }

    /**
     * Convert text into snake_case-like token.
     */
    private function normalizeToken(
        string $value
    ): string {
        $value = strtolower(
            trim($value)
        );

        $value = preg_replace(
            '/[^a-z0-9]+/',
            '_',
            $value
        );

        $value = preg_replace(
            '/_+/',
            '_',
            $value
        );

        return trim(
            $value,
            '_'
        );
    }

    private function normalizeNullableToken(
        ?string $value
    ): ?string {
        if (! $value) {
            return null;
        }

        return $this->normalizeToken(
            $value
        );
    }

    /**
     * Parse common CSV date formats.
     */
    private function normalizeDate(
        ?string $value
    ): ?string {
        if (! $value) {
            return null;
        }

        $value = trim($value);

        $formats = [
            'Y-m-d',
            'Y/m/d',
            'm/d/Y',
            'm/d/y',
            'm-d-Y',
            'm-d-y',
            'M j, Y',
            'F j, Y',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat(
                    $format,
                    $value
                )->toDateString();
            } catch (Throwable) {
                // Try next date format.
            }
        }

        try {
            return Carbon::parse(
                $value
            )->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Determine whether a CSV row contains no values.
     */
    private function isEmptyRow(
        array $row
    ): bool {
        foreach ($row as $value) {
            if (
                trim(
                    (string) $value
                ) !== ''
            ) {
                return false;
            }
        }

        return true;
    }
}
