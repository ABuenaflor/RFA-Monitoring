<?php

namespace Tests\Feature;

use App\Models\Rfa;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\SystemSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->viewer = User::factory()->withRole('viewer')->create();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function disposed(string $seado, string $mode, array $attributes = []): Rfa
    {
        return Rfa::factory()->disposed()->create($attributes + [
            'seado_name' => $seado,
            'disposition_status' => null,
            'disposition_mode' => $mode,
            'date_initial_conference' => '2026-08-12',
            'date_disposed' => '2026-08-12',
        ]);
    }

    public function test_settlement_rate_per_seado_is_split_by_conference_phase(): void
    {
        $this->disposed('ANA', 'SC');                                          // 1st conference
        $this->disposed('ANA', 'SC', ['date_disposed' => '2026-08-20']);      // later
        $this->disposed('ANA', 'SC', [                                        // 2nd conference
            'date_second_conference' => '2026-08-19',
            'date_disposed' => '2026-08-19',
        ]);
        $this->disposed('ANA', 'RCA');                                         // not settled
        Rfa::factory()->create(['seado_name' => 'ANA']);                       // open

        $settlement = $this->actingAs($this->viewer)
            ->get(route('reports'))
            ->assertOk()
            ->assertSeeText('Settlement rate per SEADO, by conference phase')
            ->viewData('seadoSettlement');

        $ana = $settlement['rows']->firstWhere('name', 'ANA');

        $this->assertSame(5, $ana['handled']);
        $this->assertSame(4, $ana['disposed']);
        $this->assertSame(3, $ana['settled']);
        $this->assertSame(75.0, $ana['rate']);
        $this->assertSame(['first' => 1, 'second' => 1, 'later' => 1, 'unknown' => 0], $ana['phases']);
    }

    public function test_the_settled_codes_follow_the_setting(): void
    {
        $this->disposed('BEN', 'SC');
        $this->disposed('BEN', 'SWBF');

        app(SettingsService::class)->put([
            SystemSettings::SETTLED_DISPOSITION_MODES => 'sc, swbf',
        ]);

        $settlement = $this->actingAs($this->viewer)
            ->get(route('reports'))
            ->viewData('seadoSettlement');

        $this->assertSame(100.0, $settlement['rows']->firstWhere('name', 'BEN')['rate']);
    }

    public function test_the_official_disposition_missing_card_is_gone(): void
    {
        $this->disposed('ANA', 'SC');

        $this->actingAs($this->viewer)
            ->get(route('reports'))
            ->assertOk()
            ->assertSeeText('Official Disposition Recorded')
            ->assertDontSeeText('Official Disposition Missing');
    }

    public function test_seado_pct_performance_defines_indeterminate(): void
    {
        $this->disposed('ANA', 'SC');

        $this->actingAs($this->viewer)
            ->get(route('reports', ['seado_name' => 'ANA']))
            ->assertOk()
            ->assertSeeText('SEADO PCT Performance')
            ->assertSeeText('means the checkpoint could not be rated');
    }

    public function test_cycle_time_shows_no_date_input_for_pct_5_without_a_first_conference(): void
    {
        Rfa::factory()->create([
            'date_assigned_seado' => '2026-08-04',
            'status' => 'assigned_to_seado',
        ]);

        $this->actingAs($this->viewer)
            ->get(route('pct-cycle-time'))
            ->assertOk()
            ->assertSeeText('No Date Input');
    }
}
