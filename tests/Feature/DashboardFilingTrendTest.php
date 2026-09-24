<?php

namespace Tests\Feature;

use App\Models\Rfa;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardFilingTrendTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->viewer = User::factory()
            ->withRole('viewer')
            ->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function filed(string $date, ?string $mode, int $count = 1): void
    {
        Rfa::factory()->count($count)->create([
            'date_filed' => $date,
            'mode_of_filing' => $mode,
        ]);
    }

    private function trend(): array
    {
        return $this->actingAs($this->viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->viewData('filingTrend');
    }

    public function test_it_counts_filings_per_day_by_mode_over_the_last_five_days(): void
    {
        Carbon::setTestNow('2026-09-24 10:00:00');

        $this->filed('2026-09-20', 'onsite', 2);
        $this->filed('2026-09-20', 'online');
        $this->filed('2026-09-22', 'On-site');
        $this->filed('2026-09-24', 'online', 3);

        $trend = $this->trend();

        $this->assertSame(['Sep 20', 'Sep 21', 'Sep 22', 'Sep 23', 'Sep 24'], $trend['labels']);
        $this->assertSame([2, 0, 1, 0, 0], $trend['onsite']);
        $this->assertSame([1, 0, 0, 0, 3], $trend['online']);
    }

    public function test_filings_outside_the_window_are_ignored(): void
    {
        Carbon::setTestNow('2026-09-24 10:00:00');

        $this->filed('2026-09-19', 'onsite', 4);
        $this->filed('2026-09-25', 'online', 4);

        $trend = $this->trend();

        $this->assertSame([0, 0, 0, 0, 0], $trend['onsite']);
        $this->assertSame([0, 0, 0, 0, 0], $trend['online']);
        $this->assertSame(0, $trend['unplotted']);
    }

    public function test_early_in_a_month_it_reaches_back_into_the_previous_month(): void
    {
        Carbon::setTestNow('2026-10-02 08:00:00');

        $this->filed('2026-09-28', 'online');

        $trend = $this->trend();

        $this->assertSame(['Sep 28', 'Sep 29', 'Sep 30', 'Oct 1', 'Oct 2'], $trend['labels']);
        $this->assertSame([1, 0, 0, 0, 0], $trend['online']);
    }

    public function test_filings_without_a_mode_are_counted_but_not_plotted(): void
    {
        Carbon::setTestNow('2026-09-24 10:00:00');

        $this->filed('2026-09-23', null, 2);
        $this->filed('2026-09-23', '');

        $this->assertSame(3, $this->trend()['unplotted']);

        $this->actingAs($this->viewer)
            ->get(route('dashboard'))
            ->assertSeeText('3 RFAs filed in this period have no mode of filing and are not shown.');
    }

    public function test_the_chart_card_is_rendered(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('RFA Filing Trend')
            ->assertSee('id="rfaFilingTrendChart"', false)
            ->assertSee('data-trend=', false);
    }
}
