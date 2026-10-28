<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Rfa;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private User $seado;

    protected function setUp(): void
    {
        parent::setUp();

        // Tuesday. SEADO assigned Thu 09-24 = working day 8 of 10: nearing.
        Carbon::setTestNow('2026-10-06 09:00:00');

        $this->seed(RolePermissionSeeder::class);

        $this->seado = User::factory()->withRole('seado')->create(['name' => 'Rosa Seado']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function nearingCase(array $attributes = []): Rfa
    {
        return Rfa::factory()->create($attributes + [
            'status' => 'assigned_to_seado',
            'monitoring_bucket' => 'ongoing',
            'date_assigned_seado' => '2026-09-24',
            'seado_id' => $this->seado->id,
            'seado_name' => $this->seado->name,
        ]);
    }

    public function test_the_scan_raises_a_nearing_alert_for_the_assigned_seado(): void
    {
        $rfa = $this->nearingCase();

        app(NotificationService::class)->scanPct();

        $alert = AppNotification::query()
            ->where('user_id', $this->seado->id)
            ->where('dedupe_key', "pct:seado_conference:{$rfa->id}")
            ->first();

        $this->assertNotNull($alert);
        $this->assertSame(AppNotification::SEVERITY_INFO, $alert->severity);
        $this->assertSame('nearing', $alert->pctLevel());
        $this->assertStringContainsString('nearing PCT', $alert->title);
    }

    public function test_an_imported_case_reaches_the_account_with_the_same_name(): void
    {
        $rfa = $this->nearingCase(['seado_id' => null, 'seado_name' => 'ROSA SEADO']);

        app(NotificationService::class)->scanPct();

        $this->assertTrue(
            AppNotification::query()
                ->where('user_id', $this->seado->id)
                ->where('rfa_id', $rfa->id)
                ->exists()
        );
    }

    public function test_the_feed_returns_counts_per_level_and_refreshes_alerts(): void
    {
        $this->nearingCase();

        Cache::forget('rfa.pct_auto_scan');

        // The refresh runs after the response, so this poll returns at once…
        $this->actingAs($this->seado)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread', 0);

        // …and the next poll shows the refreshed alerts.
        $this->actingAs($this->seado)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('pct.nearing', 1)
            ->assertJsonPath('pct.due', 0)
            ->assertJsonPath('pct.beyond', 0)
            ->assertJsonPath('items.0.level', 'nearing');
    }

    public function test_the_feed_only_shows_your_own_notifications(): void
    {
        $other = User::factory()->withRole('seado')->create();

        AppNotification::create([
            'user_id' => $other->id,
            'category' => 'pct_seado_conference',
            'severity' => 'critical',
            'title' => 'Not yours',
            'dedupe_key' => 'x',
        ]);

        Cache::put('rfa.pct_auto_scan', true, 3600);

        $this->actingAs($this->seado)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread', 0)
            ->assertJsonCount(0, 'items');
    }

    public function test_opening_a_notification_marks_it_read_and_goes_to_the_case(): void
    {
        $rfa = $this->nearingCase();

        $notification = AppNotification::create([
            'user_id' => $this->seado->id,
            'rfa_id' => $rfa->id,
            'category' => 'pct_seado_conference',
            'severity' => 'info',
            'title' => 'Nearing',
            'action_url' => route('rfas.show', $rfa),
            'dedupe_key' => 'pct:seado_conference:' . $rfa->id,
        ]);

        $this->actingAs($this->seado)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('rfas.show', $rfa));

        $this->assertNotNull($notification->fresh()->read_at);

        $intruder = User::factory()->withRole('seado')->create();

        $this->actingAs($intruder)
            ->get(route('notifications.open', $notification))
            ->assertNotFound();
    }

    public function test_the_bell_is_on_the_dashboard(): void
    {
        $this->actingAs($this->seado)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('notificationBell', false)
            ->assertSee('aria-label="Notifications"', false);
    }
}
