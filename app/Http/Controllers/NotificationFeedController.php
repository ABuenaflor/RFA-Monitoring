<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The header notification bell: the signed-in user's own alerts as JSON,
 * polled by the page, which also raises desktop notifications for new
 * nearing / due today / beyond PCT alerts.
 */
class NotificationFeedController extends Controller
{
    /**
     * How often the bell may refresh every PCT alert in the system. The
     * daily 07:00 scan still runs when the scheduler is installed; this
     * keeps alerts current when it is not.
     */
    private const AUTO_SCAN_MINUTES = 60;

    private const ITEMS = 20;

    public function feed(Request $request, NotificationService $notifications): JsonResponse
    {
        $this->refreshPctAlerts($notifications);

        $user = $request->user();

        $mine = AppNotification::query()->where('user_id', $user->id);

        $pctLevels = (clone $mine)
            ->pct()
            ->selectRaw('severity, COUNT(*) AS total')
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $items = (clone $mine)
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->latest('updated_at')
            ->limit(self::ITEMS)
            ->get();

        return response()->json([
            'unread' => (clone $mine)->unread()->count(),

            'pct' => [
                'nearing' => (int) ($pctLevels[AppNotification::SEVERITY_INFO] ?? 0),
                'due' => (int) ($pctLevels[AppNotification::SEVERITY_WARNING] ?? 0),
                'beyond' => (int) ($pctLevels[AppNotification::SEVERITY_CRITICAL] ?? 0),
            ],

            'items' => $items->map(fn (AppNotification $notification) => [
                'id' => $notification->id,
                'key' => $notification->id . ':' . $notification->severity,
                'title' => $notification->title,
                'message' => $notification->message,
                'level' => $notification->pctLevel(),
                'severity' => $notification->severity,
                'read' => $notification->isRead(),
                'url' => route('notifications.open', $notification),
                'when' => $notification->updated_at?->diffForHumans(),
            ])->values(),
        ]);
    }

    /**
     * Follow a notification from the bell: mark it read, then go to the case.
     */
    public function open(Request $request, AppNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if (! $notification->isRead()) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return redirect()->to(
            $notification->action_url ?: route('dashboard')
        );
    }

    /**
     * The refresh scans every open case, which takes seconds. It runs after
     * this response has been sent, so the user whose poll triggered it is not
     * kept waiting; the refreshed alerts appear on the next poll.
     */
    private function refreshPctAlerts(NotificationService $notifications): void
    {
        if (! Cache::add('rfa.pct_auto_scan', now()->toIso8601String(), now()->addMinutes(self::AUTO_SCAN_MINUTES))) {
            return;
        }

        defer(function () use ($notifications): void {
            try {
                $notifications->scanPct(sendEmails: false);
            } catch (\Throwable $exception) {
                Log::warning('Automatic PCT alert refresh failed: ' . $exception->getMessage());
            }
        });
    }
}
