<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationsController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'event' =>
                ['nullable', 'string', 'max:40'],

            'actor' =>
                ['nullable', 'integer'],

            'from' =>
                ['nullable', 'date'],

            'to' =>
                ['nullable', 'date', 'after_or_equal:from'],

            'unread' =>
                ['nullable', 'in:1'],
        ]);

        $user = $request->user();

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        |
        | Always scoped to the signed-in user. A notification is a work item
        | addressed to a person, not a public record.
        |
        */

        $notificationQuery = AppNotification::query()
            ->where('user_id', $user->id);

        if ($request->query('unread') === '1') {
            $notificationQuery->unread();
        }

        $notifications = $notificationQuery
            ->orderByRaw("
                CASE severity
                    WHEN 'critical' THEN 0
                    WHEN 'warning' THEN 1
                    ELSE 2
                END
            ")
            ->latest('created_at')
            ->limit(50)
            ->get();

        $summary = [
            'unread' =>
                AppNotification::query()
                    ->where('user_id', $user->id)
                    ->unread()
                    ->count(),

            'due_today' =>
                AppNotification::query()
                    ->where('user_id', $user->id)
                    ->where('severity', AppNotification::SEVERITY_WARNING)
                    ->count(),

            'overdue' =>
                AppNotification::query()
                    ->where('user_id', $user->id)
                    ->where('severity', AppNotification::SEVERITY_CRITICAL)
                    ->count(),

            'assignments' =>
                AppNotification::query()
                    ->where('user_id', $user->id)
                    ->where('category', 'assignment')
                    ->count(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Audit trail
        |--------------------------------------------------------------------------
        |
        | Only loaded for users who may see it.
        |
        */

        $canViewAudit = $user->hasPermission(Permissions::AUDIT_VIEW);

        $events = null;

        $actors = collect();

        if ($canViewAudit) {
            $auditQuery = AuditLog::query()->with('user');

            if ($request->filled('event')) {
                $auditQuery->where(
                    'event',
                    $request->query('event')
                );
            }

            if ($request->filled('actor')) {
                $auditQuery->where(
                    'user_id',
                    $request->query('actor')
                );
            }

            if ($request->filled('from')) {
                $auditQuery->whereDate(
                    'created_at',
                    '>=',
                    $request->query('from')
                );
            }

            if ($request->filled('to')) {
                $auditQuery->whereDate(
                    'created_at',
                    '<=',
                    $request->query('to')
                );
            }

            $events = $auditQuery
                ->latest('id')
                ->paginate(25)
                ->withQueryString();

            $actors = User::query()
                ->has('auditEntries')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return view('operations.index', [
            'notifications' => $notifications,

            'summary' => $summary,

            'canViewAudit' => $canViewAudit,

            'events' => $events,

            'actors' => $actors,

            'auditEvents' => AuditLog::events(),

            'filters' => [
                'event' => (string) $request->query('event', ''),

                'actor' => (string) $request->query('actor', ''),

                'from' => (string) $request->query('from', ''),

                'to' => (string) $request->query('to', ''),

                'unread' => $request->query('unread') === '1',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Notification Actions
    |--------------------------------------------------------------------------
    */

    public function markRead(
        Request $request,
        AppNotification $notification
    ): RedirectResponse {
        $this->authorizeOwnership($request, $notification);

        $notification->forceFill([
            'read_at' => now(),
        ])->save();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return back()->with(
            'status',
            'All notifications marked as read.'
        );
    }

    public function destroy(
        Request $request,
        AppNotification $notification
    ): RedirectResponse {
        $this->authorizeOwnership($request, $notification);

        $notification->delete();

        return back()->with(
            'status',
            'Notification dismissed.'
        );
    }

    /**
     * Recalculate PCT alerts on demand.
     */
    public function scan(
        Request $request,
        NotificationService $notifications
    ): RedirectResponse {
        $result = $notifications->scanPct();

        return back()->with(
            'status',
            sprintf(
                '%d active cases scanned, %d alerts current, %d cleared.',
                $result['cases_scanned'],
                $result['alerts'],
                $result['cleared']
            )
        );
    }

    /**
     * A notification belongs to exactly one person.
     */
    private function authorizeOwnership(
        Request $request,
        AppNotification $notification
    ): void {
        abort_unless(
            $notification->user_id === $request->user()->id,
            403
        );
    }
}
