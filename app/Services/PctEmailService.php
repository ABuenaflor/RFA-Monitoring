<?php

namespace App\Services;

use App\Mail\PctAlertDigest;
use App\Models\PctEmailDelivery;
use App\Models\Rfa;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails officers about their cases that reached a PCT alert level.
 *
 * Fed by NotificationService::scanPct(). Each officer gets at most one
 * digest per scan, and each case appears in an officer's email once per
 * alert level — a case moving from nearing to due today to breached
 * produces three emails over three days, not one every day.
 */
class PctEmailService
{
    /**
     * Most urgent first within a digest.
     */
    private const LEVEL_ORDER = [
        PctEmailDelivery::LEVEL_BREACHED => 0,
        PctEmailDelivery::LEVEL_DUE_TODAY => 1,
        PctEmailDelivery::LEVEL_NEARING => 2,
    ];

    /**
     * @param  array<int, array{
     *     user: User,
     *     rfa: Rfa,
     *     stage: string,
     *     level: string,
     *     stage_label: string,
     *     level_label: string,
     *     days: ?int
     * }>  $candidates
     * @return array{emails_sent: int, emails_failed: int}
     */
    public function send(array $candidates): array
    {
        $result = [
            'emails_sent' => 0,
            'emails_failed' => 0,
        ];

        if (! config('notifications.pct_email.enabled')) {
            return $result;
        }

        $byRecipient = collect($candidates)
            ->groupBy(fn (array $candidate) => $candidate['user']->id);

        foreach ($byRecipient as $items) {
            $recipient = $items->first()['user'];

            if (! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $pending = $this->notYetSent($recipient, $items);

            if ($pending->isEmpty()) {
                continue;
            }

            $maxItems = max(
                1,
                (int) config('notifications.pct_email.max_items', 50)
            );

            $shown = $pending->take($maxItems);

            try {
                Mail::to($recipient)->send(
                    new PctAlertDigest(
                        recipient: $recipient,
                        items: $shown
                            ->map(fn (array $item) => $this->row($item))
                            ->all(),
                        hiddenCount: $pending->count() - $shown->count()
                    )
                );
            } catch (Throwable $exception) {
                /*
                | Not recorded as delivered, so the next scan tries again.
                */

                Log::warning('PCT email alert could not be sent.', [
                    'user_id' => $recipient->id,
                    'cases' => $pending->count(),
                    'error' => $exception->getMessage(),
                ]);

                $result['emails_failed']++;

                continue;
            }

            $this->recordDelivered($recipient, $pending);

            $result['emails_sent']++;
        }

        return $result;
    }

    /**
     * Drop alerts this recipient was already emailed about, most urgent
     * first.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function notYetSent(
        User $recipient,
        Collection $items
    ): Collection {
        $sent = PctEmailDelivery::query()
            ->where('user_id', $recipient->id)
            ->whereIn(
                'rfa_id',
                $items->map(fn (array $item) => $item['rfa']->id)->unique()
            )
            ->get()
            ->mapWithKeys(fn (PctEmailDelivery $delivery) => [
                $this->key(
                    $delivery->rfa_id,
                    $delivery->stage,
                    $delivery->level
                ) => true,
            ]);

        return $items
            ->reject(fn (array $item) => $sent->has(
                $this->key($item['rfa']->id, $item['stage'], $item['level'])
            ))
            ->sortBy(fn (array $item) => [
                self::LEVEL_ORDER[$item['level']] ?? 9,
                -($item['days'] ?? 0),
            ])
            ->values();
    }

    /**
     * Everything pending is recorded — including cases summarised as
     * "and N more" — so a large backlog is announced once, not drip-fed.
     *
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function recordDelivered(
        User $recipient,
        Collection $items
    ): void {
        $now = now();

        PctEmailDelivery::query()->insertOrIgnore(
            $items
                ->map(fn (array $item) => [
                    'user_id' => $recipient->id,
                    'rfa_id' => $item['rfa']->id,
                    'stage' => $item['stage'],
                    'level' => $item['level'],
                    'sent_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function row(array $item): array
    {
        return [
            'reference' => $item['rfa']->displayReference(),
            'url' => route('rfas.show', $item['rfa']),
            'stage_label' => $item['stage_label'],
            'level' => $item['level'],
            'level_label' => $item['level_label'],
            'days' => $item['days'],
        ];
    }

    private function key(int $rfaId, string $stage, string $level): string
    {
        return "{$rfaId}:{$stage}:{$level}";
    }
}
