<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\PctEmailDelivery;
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
 *
 * The PCT scan also hands per-case alerts to PctEmailService, which emails
 * the assigned officer. Unassigned backlogs stay in-app only.
 */
class NotificationService
{
    public function __construct(
        private readonly PctService $pctService,
        private readonly PctEmailService $pctEmailService
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
     * condition has cleared are removed. Email alerts are sent once per
     * case and alert level, so re-running never re-sends them.
     *
     * @return array<string, int>
     */
    public function scanPct(): array
    {
        $watchers = $this->watchers();

        $generated = [];

        $unassigned = array_fill_keys(PctService::keys(), 0);

        $counts = [
            'cases_scanned' => 0,
            'alerts' => 0,
        ];

        $emailCandidates = [];

        Rfa::query()
            ->where('monitoring_bucket', '!=', Workflow::BUCKET_DISPOSED)
            ->whereNull('date_disposed')
            ->with(['interviewer', 'seado'])
            ->chunkById(200, function (Collection $rfas) use (
                &$generated,
                &$unassigned,
                &$counts,
                &$emailCandidates
            ) {
                foreach ($rfas as $rfa) {
                    $counts['cases_scanned']++;

                    $pct = $this->pctService->evaluate($rfa);

                    foreach ($this->emailAlerts($pct) as $alert) {
                        $recipient = $this->recipientFor(
                            $rfa,
                            $alert['stage']
                        );

                        if ($recipient !== null) {
                            $emailCandidates[] = $alert + [
                                'user' => $recipient,
                                'rfa' => $rfa,
                            ];
                        }
                    }

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

        /*
        |--------------------------------------------------------------------------
        | Email alerts
        |--------------------------------------------------------------------------
        */

        return $counts + $this->pctEmailService->send($emailCandidates);
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
     * Active checkpoints worth alerting on in-app: due today (warning) and
     * past the limit (critical).
     *
     * @param  array<string, mixed>  $pct
     * @return array<int, array<string, string>>
     */
    private function breaches(array $pct): array
    {
        $breaches = [];

        foreach ($pct['checkpoints'] as $key => $checkpoint) {
            if ($checkpoint['state'] !== 'active') {
                continue;
            }

            $limit = $this->limitPhrase($checkpoint);

            if ($checkpoint['classification_key'] === 'on') {
                $breaches[] = [
                    'stage' => $key,
                    'category' => $this->categoryFor($key),
                    'severity' => AppNotification::SEVERITY_WARNING,
                    'title' => $checkpoint['stage_label'] . ' due today',
                    'message' => 'Day ' . $checkpoint['days'] . ' of ' . $limit
                        . ' — the deadline is today.',
                ];

                continue;
            }

            if ($checkpoint['classification_key'] === 'beyond') {
                $breaches[] = [
                    'stage' => $key,
                    'category' => $this->categoryFor($key),
                    'severity' => AppNotification::SEVERITY_CRITICAL,
                    'title' => $checkpoint['stage_label'] . ' breached',
                    'message' => 'Day ' . $checkpoint['days'] . ', beyond ' . $limit
                        . ' — overdue by ' . $checkpoint['overdue_days'] . ' day(s).',
                ];
            }
        }

        return $breaches;
    }

    /**
     * Active checkpoints worth emailing about.
     *
     * Wider than breaches(): the email also warns ahead ("nearing"), so the
     * officer can act before the deadline.
     *
     * @param  array<string, mixed>  $pct
     * @return array<int, array<string, mixed>>
     */
    private function emailAlerts(array $pct): array
    {
        $alerts = [];

        foreach ($pct['checkpoints'] as $key => $checkpoint) {
            if ($checkpoint['state'] !== 'active') {
                continue;
            }

            $level = match ($checkpoint['classification_key']) {
                'nearing' => PctEmailDelivery::LEVEL_NEARING,
                'on' => PctEmailDelivery::LEVEL_DUE_TODAY,
                'beyond' => PctEmailDelivery::LEVEL_BREACHED,
                default => null,
            };

            if ($level === null) {
                continue;
            }

            $alerts[] = [
                'stage' => $key,
                'level' => $level,
                'stage_label' => $checkpoint['stage_label']
                    . ' (' . $this->limitPhrase($checkpoint) . ')',
                'level_label' => $this->emailLevelLabel($level),
                'days' => $checkpoint['days'],
            ];
        }

        return $alerts;
    }

    private function emailLevelLabel(string $level): string
    {
        return match ($level) {
            PctEmailDelivery::LEVEL_NEARING => 'Nearing PCT',
            PctEmailDelivery::LEVEL_DUE_TODAY => 'Due today',
            default => 'Beyond PCT',
        };
    }

    /**
     * @param  array<string, mixed>  $checkpoint
     */
    private function limitPhrase(array $checkpoint): string
    {
        return $checkpoint['limit_days'] === 0
            ? 'the same-day PCT'
            : 'the ' . $checkpoint['limit_days'] . '-day PCT';
    }

    /**
     * Checkpoints up to SEADO assignment belong to the interviewer; from
     * SEADO assignment on they belong to the SEADO. Each falls back to the
     * other officer when its own is not assigned.
     */
    private function recipientFor(Rfa $rfa, string $stage): ?User
    {
        $interviewer = $rfa->interviewer;

        $seado = $rfa->seado;

        $candidate = in_array($stage, [
            PctService::SEADO_CONFERENCE,
            PctService::CONFERENCE_DISPOSED,
        ], true)
            ? $seado ?? $interviewer
            : $interviewer ?? $seado;

        return $candidate?->isActive()
            ? $candidate
            : null;
    }

    private function categoryFor(string $stage): string
    {
        return 'pct_' . $stage;
    }

    private function stageLabel(string $stage): string
    {
        return PctService::label($stage);
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
