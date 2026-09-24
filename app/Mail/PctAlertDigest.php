<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * One email per officer per scan, listing the cases that newly reached
 * a PCT alert level.
 *
 * Sent synchronously (not queued) so the scan knows whether delivery
 * succeeded before recording it.
 */
class PctAlertDigest extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public readonly User $recipient,
        public readonly array $items,
        public readonly int $hiddenCount = 0
    ) {
    }

    public function envelope(): Envelope
    {
        $total = count($this->items) + $this->hiddenCount;

        return new Envelope(
            subject: sprintf(
                'PCT alert: %d %s %s attention',
                $total,
                Str::plural('case', $total),
                $total === 1 ? 'needs' : 'need'
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.pct-alert-digest',
            with: [
                'recipient' => $this->recipient,
                'items' => $this->items,
                'hiddenCount' => $this->hiddenCount,
                'notificationsUrl' => route('operations.index'),
            ],
        );
    }
}
