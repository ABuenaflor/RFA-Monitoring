<?php

namespace App\Services;

use App\Models\Rfa;
use Carbon\Carbon;

class PctService
{
    public const MAX_DAYS = 3;

    private const DISPOSITION_MAX_DAYS = 30;




    public function evaluate(
        Rfa $rfa,
        ?Carbon $asOf = null
    ): array {
        $asOf = ($asOf ?? Carbon::today())
            ->copy()
            ->startOfDay();

        $stageOne = $this->checkpoint(
            rfa: $rfa,
            stageKey: 'assignment',
            stageLabel: 'Filed → Interviewer Assignment',
            startField: 'date_filed',
            endField: 'date_assigned_interviewer',
            laterDateFields: [
                'date_interview',
                'date_validated',
                'date_turned_over_lr',
                'date_assigned_seado',
                'date_initial_conference',
                'date_both_parties_appeared',
                'date_disposed',
            ],
            asOf: $asOf,
        );

        $stageTwo = $this->checkpoint(
            rfa: $rfa,
            stageKey: 'interview',
            stageLabel: 'Interviewer Assignment → Interview',
            startField: 'date_assigned_interviewer',
            endField: 'date_interview',
            laterDateFields: [
                'date_validated',
                'date_turned_over_lr',
                'date_assigned_seado',
                'date_initial_conference',
                'date_both_parties_appeared',
                'date_disposed',
            ],
            asOf: $asOf,
        );

        return [
            'stage_one' => $stageOne,
            'stage_two' => $stageTwo,
            'disposition_pct' =>
            $this->evaluateDispositionPct(
                $rfa,
                $asOf
            ),
            'total_processing' =>
                $this->totalProcessing($rfa),
        ];
    }

    private function evaluateDispositionPct(
    Rfa $rfa,
    ?Carbon $asOf = null
): array {
    $asOf = (
        $asOf
        ?? now()
    )
        ->copy()
        ->startOfDay();

    $start = $rfa->date_filed
        ? Carbon::parse(
            $rfa->date_filed
        )->startOfDay()
        : null;

    $end = $rfa->date_disposed
        ? Carbon::parse(
            $rfa->date_disposed
        )->startOfDay()
        : null;

    /*
    |--------------------------------------------------------------------------
    | Missing Date Filed
    |--------------------------------------------------------------------------
    */

    if ($start === null) {
        return [
            'stage_key' =>
                'disposition',

            'label' =>
                'Overall Disposition PCT',

            'state' =>
                'missing_start',

            'is_active' =>
                false,

            'is_completed' =>
                false,

            'days' =>
                null,

            'status_key' =>
                'indeterminate',

            'status_label' =>
                'Disposition PCT Indeterminate',

            'is_compliant' =>
                null,

            'start' =>
                null,

            'end' =>
                $end,

            'deadline' =>
                null,

            'remaining_days' =>
                null,

            'overdue_days' =>
                null,

            'message' =>
                'Date Filed is missing.',
        ];
    }

    $deadline = $start
        ->copy()
        ->addDays(
            self::DISPOSITION_MAX_DAYS
        );

    /*
    |--------------------------------------------------------------------------
    | Completed / disposed RFA
    |--------------------------------------------------------------------------
    */

    if ($end !== null) {
        $days =
            $this->signedCalendarDays(
                $start,
                $end
            );

        if ($days < 0) {
            return [
                'stage_key' =>
                    'disposition',

                'label' =>
                    'Overall Disposition PCT',

                'state' =>
                    'invalid',

                'is_active' =>
                    false,

                'is_completed' =>
                    false,

                'days' =>
                    $days,

                'status_key' =>
                    'indeterminate',

                'status_label' =>
                    'Disposition PCT Indeterminate',

                'is_compliant' =>
                    null,

                'start' =>
                    $start,

                'end' =>
                    $end,

                'deadline' =>
                    $deadline,

                'remaining_days' =>
                    null,

                'overdue_days' =>
                    null,

                'message' =>
                    'Date Disposed is earlier than Date Filed.',
            ];
        }

        $withinPct =
            $days
            <= self::DISPOSITION_MAX_DAYS;

        return [
            'stage_key' =>
                'disposition',

            'label' =>
                'Overall Disposition PCT',

            'state' =>
                'completed',

            'is_active' =>
                false,

            'is_completed' =>
                true,

            'days' =>
                $days,

            'status_key' =>
                $withinPct
                    ? 'disposed_within'
                    : 'disposed_beyond',

            'status_label' =>
                $withinPct
                    ? 'Disposed Within PCT'
                    : 'Disposed Beyond PCT',

            'is_compliant' =>
                $withinPct,

            'start' =>
                $start,

            'end' =>
                $end,

            'deadline' =>
                $deadline,

            'remaining_days' =>
                null,

            'overdue_days' =>
                $withinPct
                    ? 0
                    : (
                        $days
                        - self::DISPOSITION_MAX_DAYS
                    ),

            'message' =>
                $withinPct
                    ? 'RFA was disposed within the 30-day PCT.'
                    : 'RFA was disposed beyond the 30-day PCT.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Already marked disposed but Date Disposed is missing
    |--------------------------------------------------------------------------
    |
    | Do not treat this as an active timer.
    |
    */

    if (
        $this->showsDisposedWithoutDate(
            $rfa
        )
    ) {
        return [
            'stage_key' =>
                'disposition',

            'label' =>
                'Overall Disposition PCT',

            'state' =>
                'missing_end',

            'is_active' =>
                false,

            'is_completed' =>
                false,

            'days' =>
                null,

            'status_key' =>
                'indeterminate',

            'status_label' =>
                'Disposition PCT Indeterminate',

            'is_compliant' =>
                null,

            'start' =>
                $start,

            'end' =>
                null,

            'deadline' =>
                $deadline,

            'remaining_days' =>
                null,

            'overdue_days' =>
                null,

            'message' =>
                'RFA is marked disposed but Date Disposed is missing.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Active / undisposed RFA
    |--------------------------------------------------------------------------
    */

    $days =
        $this->signedCalendarDays(
            $start,
            $asOf
        );

    if ($days < 0) {
        return [
            'stage_key' =>
                'disposition',

            'label' =>
                'Overall Disposition PCT',

            'state' =>
                'invalid',

            'is_active' =>
                false,

            'is_completed' =>
                false,

            'days' =>
                $days,

            'status_key' =>
                'indeterminate',

            'status_label' =>
                'Disposition PCT Indeterminate',

            'is_compliant' =>
                null,

            'start' =>
                $start,

            'end' =>
                null,

            'deadline' =>
                $deadline,

            'remaining_days' =>
                null,

            'overdue_days' =>
                null,

            'message' =>
                'Date Filed is later than the monitoring date.',
        ];
    }

    if (
        $days
        < self::DISPOSITION_MAX_DAYS
    ) {
        $statusKey =
            'active_within';

        $statusLabel =
            'Active Within 30-Day PCT';

        $message =
            'RFA is still within the 30-day disposition PCT.';
    } elseif (
        $days
        === self::DISPOSITION_MAX_DAYS
    ) {
        $statusKey =
            'due_today';

        $statusLabel =
            'Due Today';

        $message =
            'RFA has reached the 30-day disposition deadline.';
    } else {
        $statusKey =
            'active_beyond';

        $statusLabel =
            'Active Beyond 30-Day PCT';

        $message =
            'RFA remains undisposed beyond the 30-day PCT deadline.';
    }

    return [
        'stage_key' =>
            'disposition',

        'label' =>
            'Overall Disposition PCT',

        'state' =>
            'active',

        'is_active' =>
            true,

        'is_completed' =>
            false,

        'days' =>
            $days,

        'status_key' =>
            $statusKey,

        'status_label' =>
            $statusLabel,

        'is_compliant' =>
            null,

        'start' =>
            $start,

        'end' =>
            null,

        'deadline' =>
            $deadline,

        'remaining_days' =>
            max(
                self::DISPOSITION_MAX_DAYS
                - $days,
                0
            ),

        'overdue_days' =>
            max(
                $days
                - self::DISPOSITION_MAX_DAYS,
                0
            ),

        'message' =>
            $message,
    ];
}

    private function showsDisposedWithoutDate(
    Rfa $rfa
): bool {
    if (
        $rfa->monitoring_bucket
        === 'disposed'
    ) {
        return true;
    }

    if (
        $rfa->status
        === 'disposed'
    ) {
        return true;
    }

    return strtolower(
        trim(
            (string)
            $rfa->source_case_status
        )
    ) === 'disposed';
}

private function signedCalendarDays(
    Carbon $start,
    Carbon $end
): int {
    return (int) $start
        ->copy()
        ->startOfDay()
        ->diffInDays(
            $end
                ->copy()
                ->startOfDay(),
            false
        );
}

    private function checkpoint(
        Rfa $rfa,
        string $stageKey,
        string $stageLabel,
        string $startField,
        string $endField,
        array $laterDateFields,
        Carbon $asOf
    ): array {
        $startDate = $this->dateValue(
            $rfa,
            $startField
        );

        $endDate = $this->dateValue(
            $rfa,
            $endField
        );

        /*
        |--------------------------------------------------------------------------
        | Missing start date
        |--------------------------------------------------------------------------
        */

        if (! $startDate) {
            return $this->result(
                stageKey: $stageKey,
                stageLabel: $stageLabel,
                state: 'missing_start',
                message: $this->startMissingMessage(
                    $stageKey
                ),
            );
        }

        $deadline = $startDate
            ->copy()
            ->addDays(self::MAX_DAYS);

        /*
        |--------------------------------------------------------------------------
        | Completed checkpoint
        |--------------------------------------------------------------------------
        */

        if ($endDate) {
            $days = $this->daysBetween(
                $startDate,
                $endDate
            );

            if ($days < 0) {
                return $this->result(
                    stageKey: $stageKey,
                    stageLabel: $stageLabel,
                    state: 'invalid',
                    startDate: $startDate,
                    endDate: $endDate,
                    deadline: $deadline,
                    message:
                        'The completion date occurs before the start date.',
                );
            }

            $classification =
                $this->classify($days);

            return $this->result(
                stageKey: $stageKey,
                stageLabel: $stageLabel,
                state: 'completed',
                days: $days,
                classificationKey:
                    $classification['key'],
                classificationLabel:
                    $classification['label'],
                startDate: $startDate,
                endDate: $endDate,
                deadline: $deadline,
                remainingDays:
                    self::MAX_DAYS - $days,
                message:
                    'Historical PCT result.',
            );
        }

        /*
        |--------------------------------------------------------------------------
        | End date missing although case already progressed
        |--------------------------------------------------------------------------
        |
        | Do not keep aging a historical case when evidence shows that
        | the workflow already passed this checkpoint.
        |--------------------------------------------------------------------------
        */

        if (
            $this->hasLaterEvidence(
                $rfa,
                $laterDateFields
            )
            || $this->workflowBeyondStage(
                $rfa,
                $stageKey
            )
            || $this->isDisposed($rfa)
        ) {
            return $this->result(
                stageKey: $stageKey,
                stageLabel: $stageLabel,
                state: 'missing_end',
                startDate: $startDate,
                deadline: $deadline,
                message: $this->endMissingMessage(
                    $stageKey
                ),
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Active checkpoint
        |--------------------------------------------------------------------------
        */

        $days = $this->daysBetween(
            $startDate,
            $asOf
        );

        if ($days < 0) {
            return $this->result(
                stageKey: $stageKey,
                stageLabel: $stageLabel,
                state: 'invalid',
                startDate: $startDate,
                deadline: $deadline,
                message:
                    'The checkpoint start date is in the future.',
            );
        }

        $classification =
            $this->classify($days);

        return $this->result(
            stageKey: $stageKey,
            stageLabel: $stageLabel,
            state: 'active',
            days: $days,
            classificationKey:
                $classification['key'],
            classificationLabel:
                $classification['label'],
            startDate: $startDate,
            deadline: $deadline,
            remainingDays:
                self::MAX_DAYS - $days,
            message:
                'Active PCT timer.',
        );
    }

    /**
     * Classify elapsed processing days.
     */
    public function classify(int $days): array
    {
        if ($days <= 1) {
            return [
                'key' => 'within',
                'label' => 'Within PCT',
            ];
        }

        if ($days === 2) {
            return [
                'key' => 'nearing',
                'label' => 'Nearing PCT',
            ];
        }

        if ($days === 3) {
            return [
                'key' => 'on',
                'label' => 'On PCT',
            ];
        }

        return [
            'key' => 'beyond',
            'label' => 'Beyond PCT',
        ];
    }

    /**
     * Date Filed → Date Disposed.
     *
     * This is a processing-duration measurement only.
     * It is not assigned a PCT compliance classification.
     */
    private function totalProcessing(
        Rfa $rfa
    ): array {
        $dateFiled = $this->dateValue(
            $rfa,
            'date_filed'
        );

        $dateDisposed = $this->dateValue(
            $rfa,
            'date_disposed'
        );

        if (! $dateFiled) {
            return [
                'state' => 'unavailable',
                'days' => null,
                'message' =>
                    'Date filed is not available.',
            ];
        }

        if (! $dateDisposed) {
            return [
                'state' => 'not_disposed',
                'days' => null,
                'message' =>
                    'Case has not been disposed.',
            ];
        }

        $days = $this->daysBetween(
            $dateFiled,
            $dateDisposed
        );

        if ($days < 0) {
            return [
                'state' => 'invalid',
                'days' => null,
                'message' =>
                    'Date disposed occurs before date filed.',
            ];
        }

        return [
            'state' => 'completed',
            'days' => $days,
            'message' =>
                'Total processing duration.',
        ];
    }

    private function workflowBeyondStage(
        Rfa $rfa,
        string $stageKey
    ): bool {
        $status = (string) $rfa->status;

        /*
        |--------------------------------------------------------------------------
        | Stage 1
        |--------------------------------------------------------------------------
        |
        | Once the RFA reaches validation-related workflow, interviewer
        | assignment should already have occurred.
        |--------------------------------------------------------------------------
        */

        if ($stageKey === 'assignment') {
            return in_array(
                $status,
                [
                    'for_validation',
                    'validated',
                    'for_turnover',
                    'for_seado_assignment',
                    'assigned_to_seado',
                    'for_notice_preparation',
                    'for_conference',
                    'ongoing',
                    'for_disposition',
                    'disposed',
                ],
                true
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Stage 2
        |--------------------------------------------------------------------------
        |
        | "For Validation" may still be the current interviewer's stage,
        | so it is deliberately NOT treated as proof that the interview
        | date should already exist.
        |--------------------------------------------------------------------------
        */

        return in_array(
            $status,
            [
                'validated',
                'for_turnover',
                'for_seado_assignment',
                'assigned_to_seado',
                'for_notice_preparation',
                'for_conference',
                'ongoing',
                'for_disposition',
                'disposed',
            ],
            true
        );
    }

    private function hasLaterEvidence(
        Rfa $rfa,
        array $fields
    ): bool {
        foreach ($fields as $field) {
            if ($rfa->{$field}) {
                return true;
            }
        }

        return false;
    }

    private function isDisposed(
        Rfa $rfa
    ): bool {
        return $rfa->monitoring_bucket
                === 'disposed'
            || $rfa->status === 'disposed'
            || $rfa->date_disposed !== null;
    }

    private function dateValue(
        Rfa $rfa,
        string $field
    ): ?Carbon {
        $value = $rfa->{$field};

        if (! $value) {
            return null;
        }

        return Carbon::parse($value)
            ->startOfDay();
    }

    private function daysBetween(
        Carbon $start,
        Carbon $end
    ): int {
        return (int) $start->diffInDays(
            $end,
            false
        );
    }

    private function startMissingMessage(
        string $stageKey
    ): string {
        return match ($stageKey) {
            'assignment' =>
                'Date filed is missing.',

            'interview' =>
                'Interviewer assignment date is missing.',

            default =>
                'Checkpoint start date is missing.',
        };
    }

    private function endMissingMessage(
        string $stageKey
    ): string {
        return match ($stageKey) {
            'assignment' =>
                'Interviewer assignment date is missing although the case has already progressed.',

            'interview' =>
                'Date of Interview is missing although the case has already progressed.',

            default =>
                'Checkpoint completion date is missing.',
        };
    }

    private function result(
        string $stageKey,
        string $stageLabel,
        string $state,
        ?int $days = null,
        ?string $classificationKey = null,
        ?string $classificationLabel = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?Carbon $deadline = null,
        ?int $remainingDays = null,
        ?string $message = null
    ): array {
        return [
            'stage_key' => $stageKey,
            'stage_label' => $stageLabel,

            'state' => $state,

            'is_active' =>
                $state === 'active',

            'is_completed' =>
                $state === 'completed',

            'days' => $days,

            'classification_key' =>
                $classificationKey,

            'classification_label' =>
                $classificationLabel,

            'start_date' => $startDate,
            'end_date' => $endDate,
            'deadline' => $deadline,

            'remaining_days' =>
                $remainingDays,

            'message' => $message,
        ];
    }
}
