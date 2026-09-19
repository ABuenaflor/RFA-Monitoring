<?php

namespace App\Services;

use App\Models\Rfa;
use App\Models\RfaActivity;
use App\Models\User;
use App\Support\Workflow;
use Carbon\CarbonInterface;

/**
 * Applies operational changes to an RFA.
 *
 * Every write goes through here so that three things always happen together:
 * the record is updated, the monitoring bucket is re-derived, and the change
 * is written to the case timeline.
 */
class RfaWorkflowService
{
    /**
     * Apply an attribute change set and record it on the timeline.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function apply(
        Rfa $rfa,
        array $attributes,
        ?User $actor,
        string $type,
        string $title,
        ?string $description = null
    ): ?RfaActivity {
        $rfa->fill($attributes);

        /*
        |--------------------------------------------------------------------------
        | Monitoring bucket is always derived, never typed
        |--------------------------------------------------------------------------
        |
        | The dashboard depends on it, so it must never drift away from the
        | dates and status actually stored on the record.
        |
        */

        $rfa->monitoring_bucket = $this->deriveBucket($rfa);

        $changes = $this->diff($rfa);

        if ($changes === [] && $description === null) {
            return null;
        }

        $rfa->save();

        return $this->log(
            $rfa,
            $actor,
            $type,
            $title,
            $description,
            $changes
        );
    }

    /**
     * Record a timeline entry that carries no attribute change, such as a note.
     *
     * @param  array<int, array<string, mixed>>  $changes
     */
    public function log(
        Rfa $rfa,
        ?User $actor,
        string $type,
        string $title,
        ?string $description = null,
        array $changes = []
    ): RfaActivity {
        return $rfa->activities()->create([
            'user_id' => $actor?->id,

            'type' => $type,

            'title' => $title,

            'description' => $description,

            'changes' => $changes === []
                ? null
                : $changes,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Derivation
    |--------------------------------------------------------------------------
    */

    /**
     * The workflow status the record's dates imply.
     *
     * Offered to the user as a suggestion — the status itself stays under
     * explicit control so a case officer can hold a case at a stage the
     * dates alone would not show.
     */
    public function deriveStatus(Rfa $rfa): string
    {
        if ($rfa->date_disposed) {
            return Workflow::DISPOSED;
        }

        if (
            $rfa->date_second_conference
            || $rfa->date_initial_conference
        ) {
            return Workflow::FOR_CONFERENCE;
        }

        if ($rfa->date_assigned_seado) {
            return Workflow::ASSIGNED_TO_SEADO;
        }

        if ($rfa->date_turned_over_lr) {
            return Workflow::FOR_SEADO_ASSIGNMENT;
        }

        if ($rfa->date_validated) {
            return Workflow::FOR_TURNOVER;
        }

        if (
            $rfa->date_interview
            || $rfa->date_assigned_interviewer
        ) {
            return Workflow::FOR_VALIDATION;
        }

        return Workflow::FOR_INTERVIEWER_ASSIGNMENT;
    }

    /**
     * pending → nothing has started
     * ongoing → the case is moving through the workflow
     * disposed → the case is closed
     */
    public function deriveBucket(Rfa $rfa): string
    {
        if (
            $rfa->date_disposed
            || $rfa->status === Workflow::DISPOSED
        ) {
            return Workflow::BUCKET_DISPOSED;
        }

        $hasMovement =
            $rfa->date_assigned_interviewer
            || $rfa->date_interview
            || $rfa->date_validated
            || $rfa->date_turned_over_lr
            || $rfa->date_assigned_seado
            || $rfa->date_initial_conference
            || $rfa->date_second_conference;

        return $hasMovement
            ? Workflow::BUCKET_ONGOING
            : Workflow::BUCKET_PENDING;
    }


    /*
    |--------------------------------------------------------------------------
    | Chronology
    |--------------------------------------------------------------------------
    */

    /**
     * Inconsistencies in the record's date chain.
     *
     * Reported rather than enforced retroactively: historical imports are
     * allowed to be imperfect, but the problem is always visible and the
     * forms refuse to make it worse.
     *
     * @return array<int, string>
     */
    public function chronologyIssues(Rfa $rfa): array
    {
        $issues = [];

        $chain = array_keys(Workflow::dateChain());

        /*
        | Each date must not precede any earlier date that is present.
        */

        foreach ($chain as $index => $field) {
            $value = $this->dateOf($rfa, $field);

            if ($value === null) {
                continue;
            }

            for ($earlier = 0; $earlier < $index; $earlier++) {
                $previousField = $chain[$earlier];

                $previous = $this->dateOf($rfa, $previousField);

                if ($previous === null) {
                    continue;
                }

                if ($value->lt($previous)) {
                    $issues[] = sprintf(
                        '%s (%s) is earlier than %s (%s).',
                        Workflow::dateLabel($field),
                        $value->format('M d, Y'),
                        Workflow::dateLabel($previousField),
                        $previous->format('M d, Y')
                    );
                }
            }
        }

        /*
        | A second conference without a first one recorded.
        */

        if (
            $rfa->date_second_conference
            && ! $rfa->date_initial_conference
        ) {
            $issues[] =
                'A 2nd Conference date is recorded without a 1st Conference date.';
        }

        /*
        | Closed without the date that closes it.
        */

        if (
            $rfa->isDisposed()
            && ! $rfa->date_disposed
        ) {
            $issues[] =
                'The case is marked disposed but has no Date Disposed, so its 30-day PCT cannot be computed.';
        }

        /*
        | A disposition date with no official disposition recorded.
        */

        if (
            $rfa->date_disposed
            && ! $rfa->disposition_status
        ) {
            $issues[] =
                'A Date Disposed is recorded without an official disposition status.';
        }

        return array_values(
            array_unique($issues)
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Field-level before/after values for the attributes actually changing.
     *
     * Eloquent's own dirty tracking decides what counts as a change, so a
     * value that only differs in formatting — "1500" against "1500.00", or a
     * date re-submitted unchanged — is correctly treated as no change at all.
     *
     * @return array<int, array<string, mixed>>
     */
    private function diff(Rfa $rfa): array
    {
        $changes = [];

        foreach (array_keys($rfa->getDirty()) as $field) {
            $changes[] = [
                'field' => $field,

                'label' => $this->labelFor($field),

                'from' => $this->presentField(
                    $field,
                    $rfa->getOriginal($field)
                ),

                'to' => $this->presentField(
                    $field,
                    $rfa->getAttribute($field)
                ),
            ];
        }

        return $changes;
    }

    private function labelFor(string $field): string
    {
        $dateLabels = Workflow::dateChain();

        if (isset($dateLabels[$field])) {
            return $dateLabels[$field];
        }

        return match ($field) {
            'status' => 'Workflow Status',
            'monitoring_bucket' => 'Monitoring Bucket',
            'interviewer_name' => 'Interviewer',
            'interviewer_id' => 'Interviewer Account',
            'seado_name' => 'SEADO',
            'seado_id' => 'SEADO Account',
            'disposition_status' => 'Official Disposition',
            'disposition_mode' => 'Source Disposition Mode',
            'monetary_benefit' => 'Monetary Benefit',
            'workers_benefited' => 'Workers Benefited',
            'workers_involved' => 'Workers Involved',
            'male_workers' => 'Male Workers',
            'female_workers' => 'Female Workers',
            'requesting_party' => 'Requesting Party',
            'responding_party' => 'Responding Party',
            'company_address' => 'Company Address',
            'contact_no' => 'Contact Number',
            'total_employment' => 'Total Employment',
            'size_of_enterprise' => 'Size of Enterprise',
            'industry' => 'Industry',
            'industry_code' => 'Industry Code',
            'filer_class' => 'Filer Class',
            'issues' => 'Issues',
            'office' => 'Office',
            'docket_no' => 'Docket Number',
            default => \Illuminate\Support\Str::headline($field),
        };
    }

    /**
     * Render a value the way the user recognises it, so the timeline reads as
     * "Ongoing → Disposed" rather than "ongoing → disposed".
     */
    private function presentField(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($field) {
            'status' => Workflow::label((string) $value),

            'monitoring_bucket' => Workflow::bucketLabel((string) $value),

            default => $this->present($value),
        };
    }

    /**
     * Normalise a value for comparison and for display in the timeline.
     */
    private function present(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return trim((string) $value);
    }

    private function dateOf(Rfa $rfa, string $field): ?CarbonInterface
    {
        $value = $rfa->getAttribute($field);

        return $value instanceof CarbonInterface
            ? $value
            : null;
    }
}
