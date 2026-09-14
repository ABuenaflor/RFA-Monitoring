<?php

namespace App\Services;

use App\Models\Rfa;
use App\Support\Workflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Standing data-quality checks over the RFA table.
 *
 * Each check is a named query rather than a stored flag, so a finding
 * disappears the moment the underlying record is corrected — there is no
 * separate state to keep in step.
 */
class DataQualityService
{
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    /**
     * Run every check and return its result.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findings(): array
    {
        $findings = [];

        foreach ($this->checks() as $key => $check) {
            $query = ($check['query'])(Rfa::query());

            $count = (clone $query)->count();

            $findings[] = [
                'key' => $key,

                'label' => $check['label'],

                'description' => $check['description'],

                'impact' => $check['impact'],

                'severity' => $check['severity'],

                'count' => $count,

                'samples' => $count === 0
                    ? []
                    : (clone $query)
                        ->orderBy('id')
                        ->limit(5)
                        ->get(['id', 'reference_no', 'docket_no'])
                        ->all(),
            ];
        }

        return $findings;
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $findings = $this->findings();

        $affected = array_sum(
            array_column($findings, 'count')
        );

        $failing = array_filter(
            $findings,
            fn (array $finding) => $finding['count'] > 0
        );

        return [
            'checks' => count($findings),

            'passing' => count($findings) - count($failing),

            'failing' => count($failing),

            'affected' => $affected,

            'critical' => array_sum(
                array_map(
                    fn (array $finding) => $finding['severity'] === self::SEVERITY_CRITICAL
                        && $finding['count'] > 0
                            ? 1
                            : 0,
                    $findings
                )
            ),

            'total_records' => Rfa::query()->count(),
        ];
    }

    /**
     * The check catalogue.
     *
     * @return array<string, array<string, mixed>>
     */
    public function checks(): array
    {
        return [
            'missing_date_filed' => [
                'label' => 'Missing Date Filed',

                'description' =>
                    'Records with no Date Filed.',

                'impact' =>
                    'No PCT stage can be measured, because every checkpoint '
                    . 'starts from the filing date.',

                'severity' => self::SEVERITY_CRITICAL,

                'query' => fn (Builder $query) => $query
                    ->whereNull('date_filed'),
            ],

            'disposed_without_date' => [
                'label' => 'Disposed Without a Date Disposed',

                'description' =>
                    'Records marked disposed that carry no Date Disposed.',

                'impact' =>
                    'The 30-day disposition PCT is indeterminate. These are '
                    . 'deliberately held out of the active timer rather than '
                    . 'left ageing forever.',

                'severity' => self::SEVERITY_CRITICAL,

                'query' => fn (Builder $query) => $query
                    ->whereNull('date_disposed')
                    ->where(
                        fn (Builder $inner) => $inner
                            ->where('monitoring_bucket', Workflow::BUCKET_DISPOSED)
                            ->orWhere('status', Workflow::DISPOSED)
                            ->orWhereRaw('LOWER(source_case_status) = ?', ['disposed'])
                    ),
            ],

            'disposed_before_filed' => [
                'label' => 'Disposed Before Filed',

                'description' =>
                    'Date Disposed falls before Date Filed.',

                'impact' =>
                    'Processing duration would be negative, so the record is '
                    . 'reported as indeterminate instead.',

                'severity' => self::SEVERITY_CRITICAL,

                'query' => fn (Builder $query) => $query
                    ->whereNotNull('date_filed')
                    ->whereNotNull('date_disposed')
                    ->whereColumn('date_disposed', '<', 'date_filed'),
            ],

            'interview_before_assignment' => [
                'label' => 'Interview Before Assignment',

                'description' =>
                    'Date of Interview falls before the interviewer '
                    . 'assignment date.',

                'impact' =>
                    'Stage 2 cannot be measured from an end date that '
                    . 'precedes its start.',

                'severity' => self::SEVERITY_WARNING,

                'query' => fn (Builder $query) => $query
                    ->whereNotNull('date_assigned_interviewer')
                    ->whereNotNull('date_interview')
                    ->whereColumn('date_interview', '<', 'date_assigned_interviewer'),
            ],

            'second_conference_without_first' => [
                'label' => '2nd Conference Without a 1st',

                'description' =>
                    'A 2nd Conference date exists with no 1st Conference date.',

                'impact' =>
                    'Conference analytics count a progression that has no '
                    . 'recorded starting point.',

                'severity' => self::SEVERITY_WARNING,

                'query' => fn (Builder $query) => $query
                    ->whereNotNull('date_second_conference')
                    ->whereNull('date_initial_conference'),
            ],

            'conference_after_disposal' => [
                'label' => 'Conference After Disposal',

                'description' =>
                    'A conference date falls after the case was disposed.',

                'impact' =>
                    'The case timeline is out of order and conference '
                    . 'analytics may double count.',

                'severity' => self::SEVERITY_WARNING,

                'query' => fn (Builder $query) => $query
                    ->whereNotNull('date_disposed')
                    ->where(
                        fn (Builder $inner) => $inner
                            ->whereColumn('date_initial_conference', '>', 'date_disposed')
                            ->orWhereColumn('date_second_conference', '>', 'date_disposed')
                    ),
            ],

            'disposal_without_official_status' => [
                'label' => 'Disposal Without an Official Disposition',

                'description' =>
                    'A Date Disposed exists with no official disposition '
                    . 'status recorded.',

                'impact' =>
                    'The official disposition breakdown under-counts. The raw '
                    . 'source Mode is never substituted for it.',

                'severity' => self::SEVERITY_WARNING,

                'query' => fn (Builder $query) => $query
                    ->whereNotNull('date_disposed')
                    ->where(
                        fn (Builder $inner) => $inner
                            ->whereNull('disposition_status')
                            ->orWhere('disposition_status', '')
                    ),
            ],

            'missing_docket' => [
                'label' => 'Missing Docket Number',

                'description' =>
                    'Records with no docket number.',

                'impact' =>
                    'Expected for TA / NORES rows. Listed so the proportion '
                    . 'stays visible rather than assumed.',

                'severity' => self::SEVERITY_INFO,

                'query' => fn (Builder $query) => $query
                    ->where(
                        fn (Builder $inner) => $inner
                            ->whereNull('docket_no')
                            ->orWhere('docket_no', '')
                    ),
            ],

            'missing_office' => [
                'label' => 'Missing Office',

                'description' =>
                    'Records with no office recorded.',

                'impact' =>
                    'These records fall out of every office-filtered report.',

                'severity' => self::SEVERITY_WARNING,

                'query' => fn (Builder $query) => $query
                    ->where(
                        fn (Builder $inner) => $inner
                            ->whereNull('office')
                            ->orWhere('office', '')
                    ),
            ],

            'missing_parties' => [
                'label' => 'Missing Party Information',

                'description' =>
                    'Records with no requesting party or no responding party.',

                'impact' =>
                    'The case cannot be identified operationally from the '
                    . 'listing alone.',

                'severity' => self::SEVERITY_WARNING,

                'query' => fn (Builder $query) => $query
                    ->where(
                        fn (Builder $inner) => $inner
                            ->whereNull('requesting_party')
                            ->orWhere('requesting_party', '')
                            ->orWhereNull('responding_party')
                            ->orWhere('responding_party', '')
                    ),
            ],

            'unassigned_active' => [
                'label' => 'Active Cases With No Assigned Account',

                'description' =>
                    'Undisposed records with neither an interviewer nor a '
                    . 'SEADO system account linked.',

                'impact' =>
                    'PCT alerts for these cases cannot reach an individual '
                    . 'officer and fall back to the aggregate supervisor alert.',

                'severity' => self::SEVERITY_INFO,

                'query' => fn (Builder $query) => $query
                    ->whereNull('date_disposed')
                    ->whereNull('interviewer_id')
                    ->whereNull('seado_id'),
            ],

            'duplicate_docket' => [
                'label' => 'Repeated Docket Numbers',

                'description' =>
                    'Docket numbers appearing on more than one record.',

                'impact' =>
                    'Docket numbers are not guaranteed unique, so this is '
                    . 'informational — reference_no remains the identifier.',

                'severity' => self::SEVERITY_INFO,

                'query' => fn (Builder $query) => $query
                    ->whereNotNull('docket_no')
                    ->where('docket_no', '!=', '')
                    ->whereIn(
                        'docket_no',
                        DB::table('rfas')
                            ->select('docket_no')
                            ->whereNotNull('docket_no')
                            ->where('docket_no', '!=', '')
                            ->groupBy('docket_no')
                            ->havingRaw('COUNT(*) > 1')
                    ),
            ],
        ];
    }
}
