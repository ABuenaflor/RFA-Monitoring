<?php

namespace App\Services;

use App\Models\Rfa;
use App\Support\Workflow;
use Carbon\Carbon;

/**
 * Prescribed Case Time (PCT) rules.
 *
 * Five checkpoints, in the order a case moves through them:
 *
 *  1. Date Filed - Interviewer Assignment   on-site: same day, online: 2 days
 *  2. Interviewer Assignment - Date Interviewed          3 days
 *  3. Date Interviewed - SEADO Assignment                3 days
 *  4. SEADO Assignment - 1st Conference                 10 days
 *  5. 1st Conference - Date Disposed                    30 days
 *
 * Days are calendar days and the start day is day 0. Each checkpoint is
 * rated against its own limit:
 *
 *  - Beyond PCT  past the limit
 *  - On PCT      the deadline day itself (still compliant)
 *  - Nearing PCT the day before the deadline; the last 3 days before it
 *                for the 10- and 30-day rules
 *  - Within PCT  anything earlier
 */
class PctService
{
    public const FILING_ASSIGNMENT = 'filing_assignment';

    public const ASSIGNMENT_INTERVIEW = 'assignment_interview';

    public const INTERVIEW_SEADO = 'interview_seado';

    public const SEADO_CONFERENCE = 'seado_conference';

    public const CONFERENCE_DISPOSED = 'conference_disposed';

    public const MODE_ONSITE = 'onsite';

    public const MODE_ONLINE = 'online';

    /**
     * Limits of at least this many days warn over the last three days.
     */
    private const LONG_LIMIT = 10;

    /**
     * Date fields in workflow order. A later field being filled in shows
     * the case has already moved past an earlier checkpoint.
     */
    private const DATE_ORDER = [
        'date_filed',
        'date_assigned_interviewer',
        'date_interview',
        'date_validated',
        'date_turned_over_lr',
        'date_assigned_seado',
        'date_initial_conference',
        'date_second_conference',
        'date_both_parties_appeared',
        'date_disposed',
    ];

    /**
     * The checkpoint definitions, in workflow order.
     *
     * @return array<string, array{
     *     label: string,
     *     short_label: string,
     *     start_field: string,
     *     start_label: string,
     *     end_field: string,
     *     end_label: string,
     *     limit_days: ?int,
     *     limit_label: string,
     *     passed_statuses: array<int, string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            self::FILING_ASSIGNMENT => [
                'label' => 'Date Filed - Interviewer Assignment',
                'short_label' => 'Filed → Interviewer',
                'start_field' => 'date_filed',
                'start_label' => 'Date Filed',
                'end_field' => 'date_assigned_interviewer',
                'end_label' => 'Interviewer Assignment',
                'limit_days' => null,
                'limit_label' => 'On-site: same day · Online: 2 days',
                'passed_statuses' => self::statusesFrom(Workflow::FOR_VALIDATION),
            ],

            self::ASSIGNMENT_INTERVIEW => [
                'label' => 'Interviewer Assignment - Date Interviewed',
                'short_label' => 'Interviewer → Interview',
                'start_field' => 'date_assigned_interviewer',
                'start_label' => 'Interviewer Assignment',
                'end_field' => 'date_interview',
                'end_label' => 'Date Interviewed',
                'limit_days' => 3,
                'limit_label' => '3 days',

                /*
                | "For Validation" can still be the interviewer's own stage,
                | so it is not proof the interview date should exist.
                */

                'passed_statuses' => self::statusesFrom(Workflow::VALIDATED),
            ],

            self::INTERVIEW_SEADO => [
                'label' => 'Date Interviewed - SEADO Assignment',
                'short_label' => 'Interview → SEADO',
                'start_field' => 'date_interview',
                'start_label' => 'Date Interviewed',
                'end_field' => 'date_assigned_seado',
                'end_label' => 'SEADO Assignment',
                'limit_days' => 3,
                'limit_label' => '3 days',
                'passed_statuses' => self::statusesFrom(Workflow::ASSIGNED_TO_SEADO),
            ],

            self::SEADO_CONFERENCE => [
                'label' => 'SEADO Assignment - 1st Conference',
                'short_label' => 'SEADO → 1st Conference',
                'start_field' => 'date_assigned_seado',
                'start_label' => 'SEADO Assignment',
                'end_field' => 'date_initial_conference',
                'end_label' => '1st Conference',
                'limit_days' => 10,
                'limit_label' => '10 days',
                'passed_statuses' => self::statusesFrom(Workflow::ONGOING),
            ],

            self::CONFERENCE_DISPOSED => [
                'label' => '1st Conference - Date Disposed',
                'short_label' => '1st Conference → Disposed',
                'start_field' => 'date_initial_conference',
                'start_label' => '1st Conference',
                'end_field' => 'date_disposed',
                'end_label' => 'Date Disposed',
                'limit_days' => 30,
                'limit_label' => '30 days',
                'passed_statuses' => [Workflow::DISPOSED],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function label(string $key): string
    {
        return self::definitions()[$key]['label'] ?? $key;
    }

    /**
     * Normalise a free-text mode of filing to onsite / online, or null.
     */
    public static function normalizeMode(?string $mode): ?string
    {
        $mode = strtolower(
            preg_replace('/[^a-z]/i', '', (string) $mode)
        );

        return match ($mode) {
            'onsite', 'walkin' => self::MODE_ONSITE,
            'online' => self::MODE_ONLINE,
            default => null,
        };
    }

    /**
     * Days allowed for a checkpoint on this case, or null when it cannot
     * be known (Date Filed - Interviewer Assignment without a mode).
     */
    public static function limitFor(string $key, Rfa $rfa): ?int
    {
        if ($key !== self::FILING_ASSIGNMENT) {
            return self::definitions()[$key]['limit_days'];
        }

        return match (self::normalizeMode($rfa->mode_of_filing)) {
            self::MODE_ONSITE => 0,
            self::MODE_ONLINE => 2,
            default => null,
        };
    }

    /**
     * @return array{
     *     checkpoints: array<string, array<string, mixed>>,
     *     total_processing: array<string, mixed>
     * }
     */
    public function evaluate(
        Rfa $rfa,
        ?Carbon $asOf = null
    ): array {
        $asOf = ($asOf ?? Carbon::today())
            ->copy()
            ->startOfDay();

        $checkpoints = [];

        foreach (self::definitions() as $key => $definition) {
            $checkpoints[$key] = $this->checkpoint(
                $rfa,
                $key,
                $definition,
                $asOf
            );
        }

        return [
            'checkpoints' => $checkpoints,

            'total_processing' =>
                $this->totalProcessing($rfa),
        ];
    }

    /**
     * Rate elapsed days against a limit.
     *
     * @return array{key: string, label: string}
     */
    public function classify(int $days, int $limit = 3): array
    {
        $nearingWindow = $limit >= self::LONG_LIMIT ? 3 : 1;

        if ($days > $limit) {
            return ['key' => 'beyond', 'label' => 'Beyond PCT'];
        }

        if ($days === $limit) {
            return ['key' => 'on', 'label' => 'On PCT'];
        }

        if ($days >= $limit - $nearingWindow) {
            return ['key' => 'nearing', 'label' => 'Nearing PCT'];
        }

        return ['key' => 'within', 'label' => 'Within PCT'];
    }

    /**
     * Human label for any checkpoint state, including the ones that could
     * not be rated.
     *
     * @param  array<string, mixed>  $checkpoint
     */
    public static function statusLabel(array $checkpoint): string
    {
        if (in_array($checkpoint['state'], ['active', 'completed'], true)) {
            return (string) $checkpoint['classification_label'];
        }

        return match ($checkpoint['state']) {
            'missing_start' => 'Start Date Missing',
            'missing_end' => 'Completion Date Missing',
            'missing_mode' => 'Mode of Filing Missing',
            'invalid' => 'Invalid Dates',
            default => 'Unavailable',
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function checkpoint(
        Rfa $rfa,
        string $key,
        array $definition,
        Carbon $asOf
    ): array {
        $startDate = $this->dateValue($rfa, $definition['start_field']);

        $endDate = $this->dateValue($rfa, $definition['end_field']);

        $base = [
            'stage_key' => $key,
            'stage_label' => $definition['label'],
            'limit_label' => $definition['limit_label'],
        ];

        if (! $startDate) {
            return $this->result($base, 'missing_start', message:
                $definition['start_label'] . ' is missing.');
        }

        $limit = self::limitFor($key, $rfa);

        if ($limit === null) {
            return $this->result($base, 'missing_mode', startDate: $startDate,
                message: 'Mode of filing is missing, so the limit (same day'
                    . ' for on-site, 2 days for online) cannot be applied.');
        }

        $base['limit_days'] = $limit;

        $deadline = $startDate->copy()->addDays($limit);

        /*
        | Completed
        */

        if ($endDate) {
            $days = $this->daysBetween($startDate, $endDate);

            if ($days < 0) {
                return $this->result($base, 'invalid',
                    startDate: $startDate, endDate: $endDate, deadline: $deadline,
                    message: $definition['end_label'] . ' is earlier than '
                        . $definition['start_label'] . '.');
            }

            return $this->result($base, 'completed',
                days: $days,
                classification: $this->classify($days, $limit),
                startDate: $startDate, endDate: $endDate, deadline: $deadline,
                message: 'Completed in ' . $days . ' day(s) against a limit of '
                    . $this->limitText($limit) . '.');
        }

        /*
        | End date missing although the case has already moved on — do not
        | keep aging a checkpoint the workflow has passed.
        */

        if ($this->hasPassed($rfa, $definition)) {
            return $this->result($base, 'missing_end',
                startDate: $startDate, deadline: $deadline,
                message: $definition['end_label']
                    . ' is missing although the case has already progressed.');
        }

        /*
        | Active
        */

        $days = $this->daysBetween($startDate, $asOf);

        if ($days < 0) {
            return $this->result($base, 'invalid',
                startDate: $startDate, deadline: $deadline,
                message: $definition['start_label'] . ' is in the future.');
        }

        return $this->result($base, 'active',
            days: $days,
            classification: $this->classify($days, $limit),
            startDate: $startDate, deadline: $deadline,
            message: 'Waiting for ' . $definition['end_label'] . ' — day '
                . $days . ' of ' . $this->limitText($limit) . '.');
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function hasPassed(Rfa $rfa, array $definition): bool
    {
        $endIndex = array_search(
            $definition['end_field'],
            self::DATE_ORDER,
            true
        );

        foreach (array_slice(self::DATE_ORDER, $endIndex + 1) as $field) {
            if ($rfa->{$field}) {
                return true;
            }
        }

        if (in_array((string) $rfa->status, $definition['passed_statuses'], true)) {
            return true;
        }

        return $this->isDisposed($rfa);
    }

    /**
     * @return array<int, string>
     */
    private static function statusesFrom(string $status): array
    {
        $order = [
            Workflow::FOR_VALIDATION,
            Workflow::VALIDATED,
            Workflow::FOR_TURNOVER,
            Workflow::FOR_SEADO_ASSIGNMENT,
            Workflow::ASSIGNED_TO_SEADO,
            Workflow::FOR_NOTICE_PREPARATION,
            Workflow::FOR_CONFERENCE,
            Workflow::ONGOING,
            Workflow::FOR_DISPOSITION,
            Workflow::DISPOSED,
        ];

        return array_slice($order, (int) array_search($status, $order, true));
    }

    private function limitText(int $limit): string
    {
        return $limit === 0 ? 'the same day' : $limit . ' day(s)';
    }

    /**
     * Date Filed → Date Disposed.
     *
     * A processing-duration measurement only, never rated against a limit.
     *
     * @return array<string, mixed>
     */
    private function totalProcessing(Rfa $rfa): array
    {
        $dateFiled = $this->dateValue($rfa, 'date_filed');

        $dateDisposed = $this->dateValue($rfa, 'date_disposed');

        if (! $dateFiled) {
            return [
                'state' => 'unavailable',
                'days' => null,
                'message' => 'Date filed is not available.',
            ];
        }

        if (! $dateDisposed) {
            return [
                'state' => 'not_disposed',
                'days' => null,
                'message' => 'Case has not been disposed.',
            ];
        }

        $days = $this->daysBetween($dateFiled, $dateDisposed);

        if ($days < 0) {
            return [
                'state' => 'invalid',
                'days' => null,
                'message' => 'Date disposed occurs before date filed.',
            ];
        }

        return [
            'state' => 'completed',
            'days' => $days,
            'message' => 'Total processing duration.',
        ];
    }

    private function isDisposed(Rfa $rfa): bool
    {
        return $rfa->monitoring_bucket === Workflow::BUCKET_DISPOSED
            || $rfa->status === Workflow::DISPOSED
            || $rfa->date_disposed !== null
            || strtolower(trim((string) $rfa->source_case_status)) === 'disposed';
    }

    private function dateValue(Rfa $rfa, string $field): ?Carbon
    {
        $value = $rfa->{$field};

        if (! $value) {
            return null;
        }

        return Carbon::parse($value)->startOfDay();
    }

    private function daysBetween(Carbon $start, Carbon $end): int
    {
        return (int) $start->diffInDays($end, false);
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array{key: string, label: string}|null  $classification
     * @return array<string, mixed>
     */
    private function result(
        array $base,
        string $state,
        ?int $days = null,
        ?array $classification = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?Carbon $deadline = null,
        ?string $message = null
    ): array {
        $limit = $base['limit_days'] ?? null;

        $isRated = $days !== null && $limit !== null;

        return $base + [
            'limit_days' => $limit,

            'state' => $state,

            'is_active' => $state === 'active',

            'is_completed' => $state === 'completed',

            'days' => $days,

            'classification_key' => $classification['key'] ?? null,

            'classification_label' => $classification['label'] ?? null,

            'is_compliant' => $state === 'completed'
                ? $classification['key'] !== 'beyond'
                : null,

            'start_date' => $startDate,

            'end_date' => $endDate,

            'deadline' => $deadline,

            'remaining_days' => $isRated && $state === 'active'
                ? max($limit - $days, 0)
                : null,

            'overdue_days' => $isRated
                ? max($days - $limit, 0)
                : null,

            'message' => $message,
        ];
    }
}
