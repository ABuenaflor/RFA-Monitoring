<?php

namespace App\Console\Commands;

use App\Models\Rfa;
use App\Services\PctService;
use App\Services\RfaWorkflowService;
use App\Support\Workflow;
use Illuminate\Console\Command;

/**
 * Brings every on-site case in line with its Date Filed: the Date Assigned
 * to Interviewer is set to it, and a missing Date Interviewed takes it too
 * (see RfaWorkflowService::applyOnsiteDates()). Online cases are left alone.
 * Each change is written to the case timeline.
 */
class ApplyOnsiteDatesCommand extends Command
{
    protected $signature = 'rfa:apply-onsite-dates
        {--dry-run : Count the cases that would change without saving}';

    protected $description = 'Set the interviewer assignment and missing interview dates of on-site cases to their Date Filed.';

    public function handle(RfaWorkflowService $workflow): int
    {
        $changed = 0;

        Rfa::query()
            ->whereNotNull('date_filed')
            ->orderBy('id')
            ->each(function (Rfa $rfa) use ($workflow, &$changed) {
                if (PctService::normalizeMode($rfa->mode_of_filing) !== PctService::MODE_ONSITE) {
                    return;
                }

                if ($this->option('dry-run')) {
                    $candidate = clone $rfa;

                    $workflow->applyOnsiteDates($candidate);

                    $changed += $candidate->isDirty() ? 1 : 0;

                    return;
                }

                $attributes = [];

                if ($rfa->status === Workflow::FOR_INTERVIEWER_ASSIGNMENT) {
                    $attributes['status'] = Workflow::FOR_VALIDATION;
                }

                $activity = $workflow->apply(
                    $rfa,
                    $attributes,
                    null,
                    'workflow',
                    'On-site dates set from Date Filed',
                    null
                );

                $changed += $activity ? 1 : 0;
            });

        $this->info(sprintf(
            '%s %d on-site case(s).',
            $this->option('dry-run') ? 'Would update' : 'Updated',
            $changed
        ));

        return self::SUCCESS;
    }
}
