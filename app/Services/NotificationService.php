<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Rfa;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Workflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds the in-system work queue.
 *
 * Two shapes of notification are produced:
 *
 *  - per case, for the officer whose account is actually assigned to it
 *  - one aggregate per watcher, counting the cases in breach that nobody
 *    is assigned to — so an unassigned backlog is visible without burying
 *    every supervisor under hundreds of individual alerts
 */
class NotificationService
{
    public function __construct(
        private readonly PctService $pctService
    ) {
    }

    /**
     * Create or refresh one notification.
     */
    public function push(
        User $user,
        string $dedupeKey,
        string $category,
        string $severity,
        string $title,
        ?string $message = null,
        ?Rfa $rfa = null,
        ?string $actionUrl = null,
        bool $resetRead = false
    ): AppNotification {
        $notification = AppNotification::query()
            ->where('user_id', $user->id)
            ->where('dedupe_key', $dedupeKey)
            ->first();

        $attributes = [
            'user_id' => $user->id,

            'rfa_id' => $rfa?->id,

            'category' => $category,

            'severity' => $severity,

            'title' => $title,

            'message' => $message,

            'action_url' => $actionUrl,

            'dedupe_key' => $dedupeKey,
        ];

        if ($notification === null) {
            return AppNotification::create($attributes);
        }

        /*
        | An unchanged alert that has already been read stays read. An
        | escalation — warning becoming critical — surfaces again.
        */

        $escalated =
            $notification->severity !== AppNotification::SEVERITY_CRITICAL
            && $severity === AppNotification::SEVERITY_CRITICAL;

        if ($resetRead || $escalated) {
            $attributes['read_at'] = null;
        }

        $notification->fill($attributes)->save();

        return $notification;
    }

    /**
     * Recalculate every PCT notification in the system.
     *
     * Idempotent: running it twice leaves the same queue, and alerts whose
     * condition has cleared are removed.
     *
     * @return array<string, int>
     */
    public function scanPct(): array
    {
        $watchers = $this->watchers();

        $generated = [];

        $unassigned = [
            'stage_one' => 0,
            'stage_two' => 0,
            'disposition' => 0,
        ];

        $counts = [
            'cases_scanned' => 0,
            'alerts' => 0,
        ];

        Rfa::query()
            ->where('monitoring_bucket', '!=', Workflow::BUCKET_DISPOSED)
            ->whereNull('date_disposed')
            ->with(['interviewer', 'seado'])
            ->chunkById(200, function (Collection $rfas) use (
                &$generated,
                &$unassigned,
                &$counts
            ) {
                foreach ($rfas as $rfa) {
                    $counts['cases_scanned']++;

                    $pct = $this->pctService->evaluate($rfa);

                    foreach ($this->breaches($pct) as $breach) {
                        $recipient = $this->recipientFor(
                            $rfa,
                            $breach['stage']
                        );

                        if ($recipient === null) {
                            $unassigned[$breach['stage']]++;

                            continue;
                        }

                        $notification = $this->push(
                            user: $recipient,

                            dedupeKey: "pct:{$breach['stage']}:{$rfa->id}",

                            category: $breach['category'],

                            severity: $breach['severity'],

                            title: $breach['title']
                                . ' — ' . $rfa->displayReference(),

                            message: $breach['message'],

                            rfa: $rfa,

                            actionUrl: route('rfas.show', $rfa)
                        );

                        $generated[] = $notification->id;

                        $counts['alerts']++;
                    }
                }
            });

        /*
        |--------------------------------------------------------------------------
        | Unassigned backlog
        |--------------------------------------------------------------------------
        */

        foreach ($unassigned as $stage => $total) {
            foreach ($watchers as $watcher) {
                $key = "pct:unassigned:{$stage}";

                if ($total === 0) {
                    continue;
                }

                $notification = $this->push(
                    user: $watcher,

                    dedupeKey: $key,

                    category: $this->categoryFor($stage),

                    severity: AppNotification::SEVERITY_WARNING,

                    title: $total . ' unassigned '
                        . \Illuminate\Support\Str::plural('case', $total)
                        . ' breaching ' . $this->stageLabel($stage),

                    message: 'These cases have no system account assigned, so no'
                        . ' individual officer has been alerted.',

                    actionUrl: route('pct-process')
                );

                $generated[] = $notification->id;

                $counts['alerts']++;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Clear alerts whose condition no longer holds
        |--------------------------------------------------------------------------
        */

        $counts['cleared'] = AppNotification::query()
            ->where('dedupe_key', 'like', 'pct:%')
            ->whereNotIn('id', $generated ?: [0])
            ->delete();

        return $counts;
    }

    /**
     * Tell a user that a case has been handed to them.
     */
    public function notifyAssignment(
        User $recipient,
        Rfa $rfa,
        string $roleLabel
    ): AppNotification {
        return $this->push(
            user: $recipient,

            dedupeKey: "assignment:{$rfa->id}:" . strtolower($roleLabel),

            category: 'assignment',

            severity: AppNotification::SEVERITY_INFO,

            title: 'Assigned as ' . $roleLabel
                . ' — ' . $rfa->displayReference(),

            message: $rfa->requesting_party
                ? 'Requesting party: ' . $rfa->requesting_party
                : null,

            rfa: $rfa,

            actionUrl: route('rfas.show', $rfa),

            resetRead: true
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * PCT conditions worth alerting on, derived from a PctService result.
     *
     * @param  array<string, mixed>  $pct
     * @return array<int, array<string, string>>
     */
    private function breaches(array $pct): array
    {
        $breaches = [];

        foreach ([
            'stage_one' => ['Stage 1 PCT', 'pct_stage_one'],
            'stage_two' => ['Stage 2 PCT', 'pct_stage_two'],
        ] as $key => [$label, $category]) {
            $stage = $pct[$key];

            if ($stage['state'] !== 'active') {
                continue;
            }

            if ($stage['classification_key'] === 'on') {
                $breaches[] = [
                    'stage' => $key,
                    'category' => $category,
                    'severity' => AppNotification::SEVERITY_WARNING,
                    'title' => $label . ' due today',
                    'message' => $stage['stage_label']
                        . ' has reached day ' . $stage['days']
                        . ' of the 3-day PCT.',
                ];

                continue;
            }

            if ($stage['classification_key'] === 'beyond') {
                $breaches[] = [
                    'stage' => $key,
                    'category' => $category,
                    'severity' => AppNotification::SEVERITY_CRITICAL,
                    'title' => $label . ' breached',
                    'message' => $stage['stage_label']
                        . ' is at day ' . $stage['days']
                        . ', beyond the 3-day PCT.',
                ];
            }
        }

        $disposition = $pct['disposition_pct'];

        if ($disposition['status_key'] === 'due_today') {
            $breaches[] = [
                'stage' => 'disposition',
                'category' => 'pct_disposition',
                'severity' => AppNotification::SEVERITY_WARNING,
                'title' => '30-day disposition PCT due today',
                'message' => $disposition['message'],
            ];
        }

        if ($disposition['status_key'] === 'active_beyond') {
            $breaches[] = [
                'stage' => 'disposition',
                'category' => 'pct_disposition',
                'severity' => AppNotification::SEVERITY_CRITICAL,
                'title' => '30-day disposition PCT breached',
                'message' => $disposition['message']
                    . ' Overdue by ' . $disposition['overdue_days'] . ' day(s).',
            ];
        }

        return $breaches;
    }

    /**
     * Stage 1 and Stage 2 belong to the interviewer; disposition belongs to
     * the SEADO, falling back to the interviewer when no SEADO is assigned.
     */
    private function recipientFor(Rfa $rfa, string $stage): ?User
    {
        $interviewer = $rfa->interviewer;

        $seado = $rfa->seado;

        $candidate = match ($stage) {
            'disposition' => $seado ?? $interviewer,
            default => $interviewer ?? $seado,
        };

        return $candidate?->isActive()
            ? $candidate
            : null;
    }

    private function categoryFor(string $stage): string
    {
        return match ($stage) {
            'stage_one' => 'pct_stage_one',
            'stage_two' => 'pct_stage_two',
            default => 'pct_disposition',
        };
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            'stage_one' => 'the Stage 1 3-day PCT',
            'stage_two' => 'the Stage 2 3-day PCT',
            default => 'the 30-day disposition PCT',
        };
    }

    /**
     * Active users responsible for assignment — administrators plus anyone
     * whose role grants rfa.assign.
     *
     * @return Collection<int, User>
     */
    private function watchers(): Collection
    {
        return User::query()
            ->active()
            ->whereHas(
                'role',
                function (Builder $role) {
                    $role
                        ->where('slug', Role::ADMINISTRATOR)
                        ->orWhereHas(
                            'permissions',
                            fn (Builder $permission) => $permission->where(
                                'permission',
                                Permissions::RFA_ASSIGN
                            )
                        );
                }
            )
            ->get();
    }
}
