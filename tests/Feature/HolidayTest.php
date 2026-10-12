<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Rfa;
use App\Models\User;
use App\Services\PctCalendar;
use App\Services\PctService;
use App\Services\SettingsService;
use App\Support\SystemSettings;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->administrator()->create();

        $this->viewer = User::factory()->withRole('viewer')->create();
    }

    /**
     * PCT 2 days for a case assigned Thu 2026-01-08, interviewed Tue 01-13.
     */
    private function interviewDays(): int
    {
        app()->forgetInstance(PctCalendar::class);

        $rfa = Rfa::factory()->create([
            'date_assigned_interviewer' => '2026-01-08',
            'date_interview' => '2026-01-13',
        ]);

        return app(PctService::class)->evaluate($rfa)['checkpoints'][PctService::ASSIGNMENT_INTERVIEW]['days'];
    }

    public function test_pct_counts_working_days_by_default(): void
    {
        // Fri, Mon, Tue — the weekend is skipped.
        $this->assertSame(3, $this->interviewDays());
    }

    public function test_an_administrator_can_add_a_holiday_and_pct_skips_it(): void
    {
        $this->actingAs($this->admin)
            ->post(route('holidays.store'), [
                'date' => '2026-01-12',
                'name' => 'Special Non-Working Day',
                'type' => Holiday::TYPE_HOLIDAY,
            ])
            ->assertRedirect(route('holidays.index', ['year' => 2026]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('holidays', ['name' => 'Special Non-Working Day']);

        // Fri, Tue — Monday is now a holiday.
        $this->assertSame(2, $this->interviewDays());

        $this->actingAs($this->admin)
            ->get(route('holidays.index', ['year' => 2026]))
            ->assertOk()
            ->assertSeeText('Special Non-Working Day');
    }

    public function test_a_multi_day_event_adds_one_entry_per_date(): void
    {
        $this->actingAs($this->admin)
            ->post(route('holidays.store'), [
                'date' => '2026-12-24',
                'end_date' => '2026-12-26',
                'name' => 'Office Closure',
                'type' => Holiday::TYPE_EVENT,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, Holiday::query()->count());
    }

    public function test_a_date_can_only_hold_one_entry(): void
    {
        Holiday::create(['date' => '2026-06-12', 'name' => 'Independence Day', 'type' => 'holiday']);

        $this->actingAs($this->admin)
            ->from(route('holidays.index'))
            ->post(route('holidays.store'), [
                'date' => '2026-06-12',
                'name' => 'Duplicate',
                'type' => 'holiday',
            ])
            ->assertSessionHasErrors('date');

        $this->assertSame(1, Holiday::query()->count());
    }

    public function test_a_holiday_can_be_edited_and_deleted(): void
    {
        $holiday = Holiday::create(['date' => '2026-06-12', 'name' => 'Independence', 'type' => 'holiday']);

        $this->actingAs($this->admin)
            ->put(route('holidays.update', $holiday), [
                'date' => '2026-06-12',
                'name' => 'Independence Day',
                'type' => 'holiday',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Independence Day', $holiday->fresh()->name);

        $this->actingAs($this->admin)
            ->delete(route('holidays.destroy', $holiday))
            ->assertRedirect();

        $this->assertDatabaseCount('holidays', 0);
    }

    public function test_other_users_can_view_but_not_change_holidays(): void
    {
        Holiday::create(['date' => '2026-06-12', 'name' => 'Independence Day', 'type' => 'holiday']);

        $this->actingAs($this->viewer)
            ->get(route('holidays.index', ['year' => 2026]))
            ->assertOk()
            ->assertSeeText('Independence Day')
            ->assertDontSeeText('Add to Calendar');

        $this->actingAs($this->viewer)
            ->post(route('holidays.store'), [
                'date' => '2026-06-13',
                'name' => 'Nope',
                'type' => 'holiday',
            ])
            ->assertForbidden();
    }

    public function test_calendar_day_counting_can_be_chosen_in_settings(): void
    {
        Holiday::create(['date' => '2026-01-12', 'name' => 'Holiday', 'type' => 'holiday']);

        app(SettingsService::class)->put([
            SystemSettings::PCT_DAY_COUNTING => PctCalendar::MODE_CALENDAR,
        ]);

        // Every day counts: Jan 8 → Jan 13 is 5 days.
        $this->assertSame(5, $this->interviewDays());
    }

    public function test_the_pct_page_says_which_days_are_counted(): void
    {
        Carbon::setTestNow('2026-10-06');

        $this->actingAs($this->viewer)
            ->get(route('pct-process'))
            ->assertOk()
            ->assertSeeText('Working-day calculation');

        Carbon::setTestNow();
    }
}
