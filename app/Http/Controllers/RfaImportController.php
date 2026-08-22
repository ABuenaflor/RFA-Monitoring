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
                    'The CSV does not contain a header row.',
            ]);
        }

        $headers = array_map(
            fn ($header) =>
                $this->cleanHeader($header),
            $headerRow
        );

        $normalizedHeaders = array_map(
            fn ($header) =>
                $this->normalizeHeader($header),
            $headers
        );

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

        $processed = 0;
        $imported = 0;
        $created = 0;
        $updated = 0;
        $duplicatesSkipped = 0;

        $seenSourceKeys = [];

        DB::beginTransaction();

        try {
            while (
                ($csvRow = fgetcsv($handle))
                !== false
            ) {
                if ($this->isEmptyRow($csvRow)) {
                    continue;
                }

                $processed++;

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
                | Preserve original CSV values
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
                | Normalized representation
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

                $mapped = $this->mapRow(
                    $normalizedRow,
                    $payload,
                    $batchUuid
                );

                $sourceKey =
                    $mapped['source_row_key'];

                /*
                |--------------------------------------------------------------------------
                | Skip exact duplicate source records in same file
                |--------------------------------------------------------------------------
                */

                if (
                    isset(
                        $seenSourceKeys[$sourceKey]
                    )
                ) {
                    $duplicatesSkipped++;

                    continue;
                }

                $seenSourceKeys[$sourceKey] = true;

                /*
                |--------------------------------------------------------------------------
                | Update existing source record when re-imported
                |--------------------------------------------------------------------------
                */

                $rfa = Rfa::query()
                    ->where(
                        'source_row_key',
                        $sourceKey
                    )
                    ->first();

                /*
                |--------------------------------------------------------------------------
                | Compatibility with earlier imports
                |--------------------------------------------------------------------------
                */

                if (! $rfa) {
                    $rfa = Rfa::query()
                        ->where(
                            'reference_no',
                            $mapped['reference_no']
                        )
                        ->first();
                }

                $wasCreated = false;

                if (! $rfa) {
                    $rfa = new Rfa();

                    $wasCreated = true;
                }

                $rfa->fill($mapped);

                $rfa->save();

                if ($wasCreated) {
                    $created++;
                } else {
                    $updated++;
                }

                $imported++;
            }

            if ($imported === 0) {
                throw ValidationException::withMessages([
                    'csv_file' =>
                        'The CSV contains no importable records.',
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

        $message =
            "{$processed} source rows processed. "
            ."{$imported} unique records imported. "
            ."{$created} created, "
            ."{$updated} updated";

        if ($duplicatesSkipped > 0) {
            $message .=
                ", {$duplicatesSkipped} duplicate "
                ."row(s) skipped";
        }

        $message .= '.';

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
                $message
            );
    }

    private function mapRow(
        array $row,
        array $payload,
        string $batchUuid
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Core parties / source identity
        |--------------------------------------------------------------------------
        */

        $office = $this->nullableText(
            $this->firstValue(
                $row,
                ['office']
            )
        );

        $docketNo = $this->nullableText(
            $this->firstValue(
                $row,
                [
                    'docket_no',
                    'docket_number',
                ]
            )
        );

        $requestingParty = $this->nullableText(
            $this->firstValue(
                $row,
                [
                    'requesting_party',
                    'requesting_party_name',
                    'complainant',
                    'complainant_name',
                ]
            )
        );

        $respondingParty = $this->nullableText(
            $this->firstValue(
                $row,
                [
                    'responding_party',
                    'responding_party_name',
                    'company',
                    'company_name',
                    'employer',
                    'employer_name',
                ]
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Dates
        |--------------------------------------------------------------------------
        */

        $dateFiled = $this->normalizeDate(
            $this->firstValue(
                $row,
                [
                    'date_filed',
                    'filing_date',
                ]
            )
        );

        $dateTaNores = $this->normalizeDate(
            $this->firstValue(
                $row,
                [
                    'date_ta_nores',
                    'ta_nores_date',
                ]
            )
        );

        $dateAssignedInterviewer =
            $this->normalizeDate(
                $this->firstValue(
                    $row,
                    [
                        'assigned_to_interviewer',
                        'date_assigned_interviewer',
                        'date_assigned_to_interviewer',
                        'interviewer_assignment_date',
                    ]
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Date of Interview is deliberately separate.
        |
        | DO NOT map Initial Conference into this field.
        |--------------------------------------------------------------------------
        */

        $dateInterview = $this->normalizeDate(
            $this->firstValue(
                $row,
                [
                    'date_interview',
                    'date_of_interview',
                    'interview_date',
                ]
            )
        );

        $dateValidated = $this->normalizeDate(
            $this->firstValue(
                $row,
                [
                    'date_validated',
                    'validation_date',
                ]
            )
        );

        $dateTurnedOverLr = $this->normalizeDate(
            $this->firstValue(
                $row,
                [
                    'date_turned_over_lr',
                    'date_turned_over_to_lr',
                    'lr_turnover_date',
                ]
            )
        );

        $dateAssignedSeado =
            $this->normalizeDate(
                $this->firstValue(
                    $row,
                    [
                        'assigned_to_seado',
                        'date_assigned_seado',
                        'date_assigned_to_seado',
                        'seado_assignment_date',
                    ]
                )
            );

        $dateInitialConference =
            $this->normalizeDate(
                $this->firstValue(
                    $row,
                    [
                        'initial_conference',
                        'date_initial_conference',
                    ]
                )
            );

        $dateBothPartiesAppeared =
            $this->normalizeDate(
                $this->firstValue(
                    $row,
                    [
                        'both_parties_appeared',
                        'date_both_parties_appeared',
                    ]
                )
            );

        $dateDisposed = $this->normalizeDate(
            $this->firstValue(
                $row,
                [
                    'date_disposed',
                    'disposed_date',
                ]
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Source status
        |--------------------------------------------------------------------------
        */

        $sourceCaseStatus =
            $this->nullableText(
                $this->firstValue(
                    $row,
                    [
                        'case_status',
                        'source_case_status',
                    ]
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Workflow status
        |--------------------------------------------------------------------------
        */

        $explicitSystemStatus =
            $this->firstValue(
                $row,
                [
                    'status',
                    'rfa_status',
                    'current_status',
                ]
            );

        if ($explicitSystemStatus) {
            $status =
                $this->normalizeWorkflowStatus(
                    $explicitSystemStatus
                );
        } else {
            $status =
                $this->deriveWorkflowStatus(
                    $sourceCaseStatus,
                    $dateAssignedInterviewer,
                    $dateInterview,
                    $dateValidated,
                    $dateTurnedOverLr,
                    $dateAssignedSeado,
                    $dateInitialConference,
                    $dateDisposed
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Filing mode
        |--------------------------------------------------------------------------
        |
        | CSV "Mode" is NOT used here.
        |--------------------------------------------------------------------------
        */

        $modeOfFiling =
            $this->deriveModeOfFiling(
                $row,
                $docketNo,
                $sourceCaseStatus
            );

        /*
        |--------------------------------------------------------------------------
        | Monitoring bucket
        |--------------------------------------------------------------------------
        */

        $monitoringBucket =
            $this->determineMonitoringBucket(
                $this->firstValue(
                    $row,
                    [
                        'monitoring_bucket',
                        'monitoring_status',
                        'bucket',
                    ]
                ),
                $sourceCaseStatus,
                $status,
                $dateAssignedInterviewer,
                $dateInterview,
                $dateAssignedSeado,
                $dateInitialConference,
                $dateDisposed
            );

        /*
        |--------------------------------------------------------------------------
        | Stable source key
        |--------------------------------------------------------------------------
        |
        | Docket alone cannot be used because the real CSV contains
        | duplicate docket numbers belonging to different cases.
        |--------------------------------------------------------------------------
        */

        $sourceRowKey =
            $this->makeSourceRowKey(
                $office,
                $docketNo,
                $dateFiled,
                $requestingParty,
                $respondingParty
            );

        /*
        |--------------------------------------------------------------------------
        | Internal system reference
        |--------------------------------------------------------------------------
        */

        $referenceNo =
            $this->firstValue(
                $row,
                [
                    'reference_no',
                    'reference_number',
                    'rfa_no',
                    'rfa_number',
                ]
            );

        if (! $referenceNo) {
            $referenceNo =
                'RFA-SRC-'
                .strtoupper(
                    substr(
                        $sourceRowKey,
                        0,
                        16
                    )
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Final disposition
        |--------------------------------------------------------------------------
        |
        | The real export has "Mode" codes but does not provide a
        | clear Settled / Withdrawn / Referred mapping.
        | Preserve the raw Mode separately.
        |--------------------------------------------------------------------------
        */

        $dispositionStatus =
            $this->normalizeNullableToken(
                $this->firstValue(
                    $row,
                    [
                        'disposition_status',
                        'final_disposition',
                        'disposition',
                    ]
                )
            );

        $dispositionMode =
            $this->nullableText(
                $this->firstValue(
                    $row,
                    [
                        'mode',
                        'disposition_mode',
                    ]
                )
            );

        return [
            'reference_no' =>
                $referenceNo,

            'office' =>
                $office,

            'docket_no' =>
                $docketNo,

            'requesting_party' =>
                $requestingParty,

            'responding_party' =>
                $respondingParty,

            'company_address' =>
                $this->nullableText(
                    $this->firstValue(
                        $row,
                        ['company_address']
                    )
                ),

            'contact_no' =>
                $this->nullableText(
                    $this->firstValue(
                        $row,
                        [
                            'contact_no',
                            'contact_number',
                        ]
                    )
                ),

            'total_employment' =>
                $this->normalizeInteger(
                    $this->firstValue(
                        $row,
                        ['total_employment']
                    )
                ),

            'industry' =>
                $this->nullableText(
                    $this->firstValue(
                        $row,
                        ['industry']
                    )
                ),

            'industry_code' =>
                $this->nullableText(
                    $this->firstValue(
                        $row,
                        ['industry_code']
                    )
                ),

            'size_of_enterprise' =>
                $this->nullableText(
                    $this->firstValue(
                        $row,
                        ['size_of_enterprise']
                    )
                ),

            'status' =>
                $status,

            'source_case_status' =>
                $sourceCaseStatus,

            'monitoring_bucket' =>
                $monitoringBucket,

            'mode_of_filing' =>
                $modeOfFiling,

            'date_filed' =>
                $dateFiled,

            'date_ta_nores' =>
                $dateTaNores,

            'date_assigned_interviewer' =>
                $dateAssignedInterviewer,

            'date_interview' =>
                $dateInterview,

            'date_validated' =>
                $dateValidated,

            'date_turned_over_lr' =>
                $dateTurnedOverLr,

            'date_assigned_seado' =>
                $dateAssignedSeado,

            'date_initial_conference' =>
                $dateInitialConference,

            'date_both_parties_appeared' =>
                $dateBothPartiesAppeared,

            'interviewer_name' =>
                $this->nullableText(
                    $this->firstValue(
                        $row,
                        ['interviewer']
                    )
                ),

            'seado_name' =>
                $this->nullableText(
                    $this->firstValue(
                        $row,
                        ['seado']
                    )
                ),

            'workers_involved' =>
                $this->normalizeInteger(
                    $this->firstValue(
                        $row,
                        ['workers_involved']
                    )
                ),

            'male_workers' =>
                $this->normalizeInteger(
                    $this->firstValue(
                        $row,
                        ['male']
                    )
                ),

            'female_workers' =>
                $this->normalizeInteger(
                    $this->firstValue(
                        $row,
                        ['female']
                    )
                ),

            'workers_benefited' =>
                $this->normalizeInteger(
                    $this->firstValue(
                        $row,
                        ['workers_benefited']
                    )
                ),

            'filer_class' =>
                $this->nullableText(
                    $this->firstValue(
                        $row,
                        ['filer_class']
                    )
                ),

            'issues' =>
                $this->nullableText(
                    $this->firstValue(
                        $row,
                        ['issues']
                    )
                ),

            'disposition_status' =>
                $dispositionStatus,

            'disposition_mode' =>
                $dispositionMode,

            'date_disposed' =>
                $dateDisposed,

            'monetary_benefit' =>
                $this->normalizeMoney(
                    $this->firstValue(
                        $row,
                        ['monetary_benefit']
                    )
                ),

            'source_row_key' =>
                $sourceRowKey,

            'import_batch_uuid' =>
                $batchUuid,

            'import_payload' =>
                $payload,
        ];
    }

    private function deriveWorkflowStatus(
        ?string $sourceStatus,
        ?string $dateAssignedInterviewer,
        ?string $dateInterview,
        ?string $dateValidated,
        ?string $dateTurnedOverLr,
        ?string $dateAssignedSeado,
        ?string $dateInitialConference,
        ?string $dateDisposed
    ): string {
        $sourceToken =
            $sourceStatus
                ? $this->normalizeToken(
                    $sourceStatus
                )
                : null;

        if (
            $sourceToken === 'disposed'
            || $dateDisposed
        ) {
            return 'disposed';
        }

        if ($dateInitialConference) {
            return 'for_conference';
        }

        if ($dateAssignedSeado) {
            return 'assigned_to_seado';
        }

        if ($dateTurnedOverLr) {
            return 'for_seado_assignment';
        }

        if ($dateValidated) {
            return 'for_turnover';
        }

        if (
            $dateInterview
            || $dateAssignedInterviewer
        ) {
            return 'for_validation';
        }

        return 'for_interviewer_assignment';
    }

    private function determineMonitoringBucket(
        ?string $explicitBucket,
        ?string $sourceCaseStatus,
        string $workflowStatus,
        ?string $dateAssignedInterviewer,
        ?string $dateInterview,
        ?string $dateAssignedSeado,
        ?string $dateInitialConference,
        ?string $dateDisposed
    ): string {
        if ($explicitBucket) {
            $bucket =
                $this->normalizeToken(
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
            $workflowStatus === 'disposed'
            || $dateDisposed
        ) {
            return 'disposed';
        }

        $sourceToken =
            $sourceCaseStatus
                ? $this->normalizeToken(
                    $sourceCaseStatus
                )
                : null;

        /*
        |--------------------------------------------------------------------------
        | Actual export behavior
        |--------------------------------------------------------------------------
        |
        | Every TA-OL / TA-OS / NORES row in this supplied CSV has no
        | docket number. Keep these in the pre-processing/pending bucket
        | until their exact business meaning is formally defined.
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $sourceToken,
                [
                    'ta_ol',
                    'ta_os',
                    'nores',
                ],
                true
            )
        ) {
            return 'pending';
        }

        if ($sourceToken === 'pending') {
            return 'ongoing';
        }

        if (
            $dateAssignedInterviewer
            || $dateInterview
            || $dateAssignedSeado
            || $dateInitialConference
        ) {
            return 'ongoing';
        }

        return 'pending';
    }

    private function deriveModeOfFiling(
        array $row,
        ?string $docketNo,
        ?string $sourceCaseStatus
    ): ?string {
        $explicit =
            $this->firstValue(
                $row,
                [
                    'mode_of_filing',
                    'filing_mode',
                ]
            );

        if ($explicit) {
            return $this->normalizeToken(
                $explicit
            );
        }

        if ($docketNo) {
            if (
                preg_match(
                    '/-OL$/i',
                    $docketNo
                )
            ) {
                return 'online';
            }

            if (
                preg_match(
                    '/-OS$/i',
                    $docketNo
                )
            ) {
                return 'onsite';
            }
        }

        if ($sourceCaseStatus) {
            $status =
                $this->normalizeToken(
                    $sourceCaseStatus
                );

            if ($status === 'ta_ol') {
                return 'online';
            }

            if ($status === 'ta_os') {
                return 'onsite';
            }
        }

        return null;
    }

    private function makeSourceRowKey(
        ?string $office,
        ?string $docketNo,
        ?string $dateFiled,
        ?string $requestingParty,
        ?string $respondingParty
    ): string {
        $identity = [
            $this->identityToken($office),
            $this->identityToken($docketNo),
            $dateFiled ?? '',
            $this->identityToken(
                $requestingParty
            ),
            $this->identityToken(
                $respondingParty
            ),
        ];

        return hash(
            'sha256',
            implode('|', $identity)
        );
    }

    private function identityToken(
        ?string $value
    ): string {
        if (! $value) {
            return '';
        }

        $value = mb_strtolower(
            trim($value)
        );

        $value = preg_replace(
            '/\s+/u',
            ' ',
            $value
        );

        return $value;
    }

    private function normalizeWorkflowStatus(
        ?string $value
    ): string {
        if (! $value) {
            return 'for_interviewer_assignment';
        }

        return $this->normalizeToken(
            $value
        );
    }

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

    private function normalizeHeader(
        ?string $header
    ): string {
        return $this->normalizeToken(
            $this->cleanHeader($header)
        );
    }

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
        $value =
            $this->nullableText($value);

        if (! $value) {
            return null;
        }

        return $this->normalizeToken(
            $value
        );
    }

    private function nullableText(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (
            in_array(
                strtolower($value),
                [
                    '-',
                    'na',
                    'n/a',
                ],
                true
            )
        ) {
            return null;
        }

        return $value;
    }

    private function normalizeInteger(
        ?string $value
    ): ?int {
        $value =
            $this->nullableText($value);

        if ($value === null) {
            return null;
        }

        $value = str_replace(
            [
                ',',
                ' ',
            ],
            '',
            $value
        );

        if (! is_numeric($value)) {
            return null;
        }

        return max(
            0,
            (int) $value
        );
    }

    private function normalizeMoney(
        ?string $value
    ): ?string {
        $value =
            $this->nullableText($value);

        if ($value === null) {
            return null;
        }

        $value = preg_replace(
            '/[^\d.\-]/u',
            '',
            $value
        );

        if (
            $value === ''
            || ! is_numeric($value)
        ) {
            return null;
        }

        return number_format(
            (float) $value,
            2,
            '.',
            ''
        );
    }

    private function normalizeDate(
        ?string $value
    ): ?string {
        $value =
            $this->nullableText($value);

        if (! $value) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Invalid legacy zero-date sentinels
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/^0000-00-00/',
                $value
            )
        ) {
            return null;
        }

        $formats = [
            'Y-m-d',
            'Y/m/d',
            'm/d/Y',
            'm/d/y',
            'm-d-Y',
            'm-d-y',
            'M j, Y',
            'F j, Y',
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat(
                    $format,
                    $value
                )->toDateString();
            } catch (Throwable) {
                //
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

    private function isEmptyRow(
        array $row
    ): bool {
        foreach ($row as $value) {
            if (
                trim((string) $value) !== ''
            ) {
                return false;
            }
        }

        return true;
    }
}
