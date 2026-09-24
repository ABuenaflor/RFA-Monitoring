<?php

namespace Tests\Feature;

use App\Models\Rfa;
use App\Models\User;
use App\Services\PctService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PctCycleTimeTest extends TestCase
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

    public function test_the_page_explains_all_five_checkpoints(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get(route('pct-cycle-time'))
            ->assertOk()
            ->assertSeeText('How the PCT Clock Runs');

        foreach (PctService::definitions() as $definition) {
            $response
                ->assertSeeText($definition['label'])
                ->assertSeeText('Limit: ' . $definition['limit_label']);
        }
    }

    public function test_each_case_shows_the_start_date_of_every_step(): void
    {
        $rfa = Rfa::factory()->create([
            'mode_of_filing' => 'online',
            'date_filed' => '2026-03-02',
            'date_assigned_interviewer' => '2026-03-03',
            'date_interview' => '2026-03-05',
            'date_assigned_seado' => '2026-03-06',
        ]);

        $this->actingAs($this->viewer)
            ->get(route('pct-cycle-time'))
            ->assertOk()
            ->assertSee($rfa->docket_no)
            ->assertSeeText('Mar 02, 2026')
            ->assertSeeText('Mar 03, 2026')
            ->assertSeeText('Mar 05, 2026')
            ->assertSeeText('Mar 06, 2026')
            ->assertSeeText('No start date');
    }

    public function test_checkpoint_statistics_are_calculated(): void
    {
        Rfa::factory()->create([
            'mode_of_filing' => 'online',
            'date_filed' => '2026-03-02',
            'date_assigned_interviewer' => '2026-03-03',
        ]);

        Rfa::factory()->create([
            'mode_of_filing' => 'onsite',
            'date_filed' => '2026-03-02',
            'date_assigned_interviewer' => '2026-03-05',
        ]);

        $this->actingAs($this->viewer)
            ->get(route('pct-cycle-time'))
            ->assertViewHas('stats', function (array $stats) {
                $filing = $stats[PctService::FILING_ASSIGNMENT];

                return $filing['completed'] === 2
                    && $filing['average_days'] === 2.0
                    && $filing['compliance_rate'] === 50.0;
            });
    }

    public function test_the_office_filter_limits_cases_and_statistics(): void
    {
        $albay = Rfa::factory()->create(['office' => 'RO-V-APFO']);
        Rfa::factory()->create(['office' => 'RO-V-MPO']);

        $this->actingAs($this->viewer)
            ->get(route('pct-cycle-time', ['office' => 'RO-V-APFO']))
            ->assertOk()
            ->assertViewHas('cases', fn ($cases) => $cases->pluck('id')->all() === [$albay->id])
            ->assertViewHas('officeLabel', 'Albay PFO');
    }

    public function test_open_cases_are_shown_by_default_and_disposed_on_request(): void
    {
        $open = Rfa::factory()->create();
        $disposed = Rfa::factory()->disposed()->create();

        $this->actingAs($this->viewer)
            ->get(route('pct-cycle-time'))
            ->assertViewHas('cases', fn ($cases) => $cases->pluck('id')->all() === [$open->id]);

        $this->actingAs($this->viewer)
            ->get(route('pct-cycle-time', ['cases' => 'disposed']))
            ->assertViewHas('cases', fn ($cases) => $cases->pluck('id')->all() === [$disposed->id]);

        $this->actingAs($this->viewer)
            ->get(route('pct-cycle-time', ['cases' => 'all']))
            ->assertViewHas('cases', fn ($cases) => $cases->count() === 2);
    }

    public function test_the_sidebar_offers_it_under_pct_process(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('pct-cycle-time'))
            ->assertSeeText('PCT Monitoring')
            ->assertSee('href="' . route('pct-cycle-time') . '"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_users_without_pct_access_are_refused(): void
    {
        $role = \App\Models\Role::create([
            'name' => 'No PCT',
            'slug' => 'no-pct',
            'is_system' => false,
        ]);

        $user = User::factory()->withRole($role)->create();

        $this->actingAs($user)
            ->get(route('pct-cycle-time'))
            ->assertForbidden();
    }
}
