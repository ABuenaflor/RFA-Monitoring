<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Rfa;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $interviewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()
            ->administrator()
            ->create();

        $this->interviewer = User::factory()
            ->withRole('interviewer')
            ->create();
    }

    private function scan(): array
    {
        return app(NotificationService::class)->scanPct();
    }

    /*
    |--------------------------------------------------------------------------
    | PCT Scanning
    |--------------------------------------------------------------------------
    */

    public function test_a_breached_filing_checkpoint_alerts_the_assigned_interviewer(): void
    {
        $rfa = Rfa::factory()->create([
            'date_filed' => now()->subDays(10)->toDateString(),
            'interviewer_id' => $this->interviewer->id,
            'interviewer_name' => $this->interviewer->name,
        ]);

        $this->scan();

        $notification = AppNotification::query()
            ->where('user_id', $this->interviewer->id)
            ->where('dedupe_key', "pct:filing_assignment:{$rfa->id}")
            ->first();

        $this->assertNotNull($notification);

        $this->assertSame(
            AppNotification::SEVERITY_CRITICAL,
            $notification->severity
        );

        $this->assertNull($notification->read_at);
    }

    public function test_scanning_twice_does_not_duplicate_the_queue(): void
    {
        Rfa::factory()->create([
            'date_filed' => now()->subDays(10)->toDateString(),
            'interviewer_id' => $this->interviewer->id,
        ]);

        $this->scan();

        $first = AppNotification::query()
            ->where('user_id', $this->interviewer->id)
            ->count();

        $this->scan();

        $this->assertSame(
            $first,
            AppNotification::query()
                ->where('user_id', $this->interviewer->id)
                ->count()
        );
    }

    public function test_an_alert_is_removed_once_its_condition_clears(): void
    {
        $rfa = Rfa::factory()->create([
            'date_filed' => now()->subDays(10)->toDateString(),
            'interviewer_id' => $this->interviewer->id,
        ]);

        $this->scan();

        $this->assertSame(
            1,
            AppNotification::query()
                ->where('dedupe_key', "pct:filing_assignment:{$rfa->id}")
                ->count()
        );

        /*
        | Closing the case takes it out of active monitoring entirely.
        */

        $rfa->forceFill([
            'date_disposed' => now()->toDateString(),
            'monitoring_bucket' => 'disposed',
        ])->save();

        $result = $this->scan();

        $this->assertSame(
            0,
            AppNotification::query()
                ->where('dedupe_key', 'like', 'pct:%')
                ->count()
        );

        $this->assertGreaterThan(0, $result['cleared']);
    }

    public function test_an_unassigned_breach_becomes_one_aggregate_alert_for_watchers(): void
    {
        Rfa::factory()->count(3)->create([
            'date_filed' => now()->subDays(10)->toDateString(),
        ]);

        $this->scan();

        $aggregate = AppNotification::query()
            ->where('user_id', $this->admin->id)
            ->where('dedupe_key', 'pct:unassigned:filing_assignment')
            ->first();

        $this->assertNotNull($aggregate);

        $this->assertStringContainsString(
            '3 unassigned cases',
            $aggregate->title
        );

        /*
        | One aggregate, not one alert per case.
        */

        $this->assertSame(
            1,
            AppNotification::query()
                ->where('user_id', $this->admin->id)
                ->where('dedupe_key', 'like', 'pct:%')
                ->count()
        );
    }

    public function test_a_disposed_case_is_not_scanned(): void
    {
        Rfa::factory()->disposed()->create([
            'interviewer_id' => $this->interviewer->id,
        ]);

        $result = $this->scan();

        $this->assertSame(0, $result['cases_scanned']);

        $this->assertSame(
            0,
            AppNotification::query()->count()
        );
    }

    public function test_an_escalation_reopens_an_alert_that_was_already_read(): void
    {
        $rfa = Rfa::factory()->create([
            'date_filed' => now()->subDays(2)->toDateString(),
            'interviewer_id' => $this->interviewer->id,
        ]);

        $this->scan();

        $notification = AppNotification::query()
            ->where('dedupe_key', "pct:filing_assignment:{$rfa->id}")
            ->firstOrFail();

        $this->assertSame(
            AppNotification::SEVERITY_WARNING,
            $notification->severity
        );

        $notification->forceFill(['read_at' => now()])->save();

        /*
        | The case ages past the deadline.
        */

        $rfa->forceFill([
            'date_filed' => now()->subDays(6)->toDateString(),
        ])->save();

        $this->scan();

        $notification->refresh();

        $this->assertSame(
            AppNotification::SEVERITY_CRITICAL,
            $notification->severity
        );

        $this->assertNull($notification->read_at);
    }

    /*
    |--------------------------------------------------------------------------
    | Assignment
    |--------------------------------------------------------------------------
    */

    public function test_assigning_a_case_notifies_the_officer(): void
    {
        $rfa = Rfa::factory()->create();

        $this->actingAs($this->admin)
            ->put(route('rfas.assignment', $rfa), [
                'interviewer_user_id' => $this->interviewer->id,
                'date_assigned_interviewer' => now()->toDateString(),
            ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $this->interviewer->id,
            'rfa_id' => $rfa->id,
            'category' => 'assignment',
        ]);
    }

    public function test_resaving_the_same_assignment_does_not_re_notify(): void
    {
        $rfa = Rfa::factory()->create([
            'interviewer_id' => $this->interviewer->id,
            'interviewer_name' => $this->interviewer->name,
        ]);

        $this->actingAs($this->admin)
            ->put(route('rfas.assignment', $rfa), [
                'interviewer_user_id' => $this->interviewer->id,
            ]);

        $this->assertSame(
            0,
            AppNotification::query()
                ->where('category', 'assignment')
                ->count()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Notification Centre
    |--------------------------------------------------------------------------
    */

    public function test_the_page_only_shows_your_own_notifications(): void
    {
        Rfa::factory()->create([
            'date_filed' => now()->subDays(10)->toDateString(),
            'interviewer_id' => $this->interviewer->id,
        ]);

        $this->scan();

        $other = AppNotification::query()
            ->where('user_id', $this->interviewer->id)
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertDontSee($other->title);
    }

    public function test_a_notification_cannot_be_read_by_another_user(): void
    {
        Rfa::factory()->create([
            'date_filed' => now()->subDays(10)->toDateString(),
            'interviewer_id' => $this->interviewer->id,
        ]);

        $this->scan();

        $notification = AppNotification::query()
            ->where('user_id', $this->interviewer->id)
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('operations.notifications.read', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_marking_all_read_clears_only_your_own_queue(): void
    {
        Rfa::factory()->create([
            'date_filed' => now()->subDays(10)->toDateString(),
            'interviewer_id' => $this->interviewer->id,
        ]);

        $this->scan();

        $this->actingAs($this->admin)
            ->post(route('operations.notifications.read-all'))
            ->assertRedirect();

        $this->assertNull(
            AppNotification::query()
                ->where('user_id', $this->interviewer->id)
                ->firstOrFail()
                ->read_at
        );

        $this->assertSame(
            0,
            AppNotification::query()
                ->where('user_id', $this->admin->id)
                ->unread()
                ->count()
        );
    }

    public function test_a_notification_can_be_dismissed(): void
    {
        Rfa::factory()->create([
            'date_filed' => now()->subDays(10)->toDateString(),
            'interviewer_id' => $this->interviewer->id,
        ]);

        $this->scan();

        $notification = AppNotification::query()
            ->where('user_id', $this->interviewer->id)
            ->firstOrFail();

        $this->actingAs($this->interviewer)
            ->delete(route('operations.notifications.destroy', $notification))
            ->assertRedirect();

        $this->assertDatabaseMissing('app_notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_the_page_is_closed_to_roles_without_the_permission(): void
    {
        $viewer = User::factory()
            ->withRole('viewer')
            ->create();

        $this->actingAs($viewer)
            ->get(route('operations.index'))
            ->assertForbidden();
    }
}
