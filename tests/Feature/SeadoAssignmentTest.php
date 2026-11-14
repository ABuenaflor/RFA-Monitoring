<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Rfa;
use App\Models\User;
use App\Support\Workflow;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeadoAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->administrator()->create();

        config(['seados.designated' => []]);
    }

    private function designate(string $name, string $office): void
    {
        config(['seados.designated' => [
            ...config('seados.designated'),
            ['name' => $name, 'office' => $office, 'position' => 'LEO II', 'id_number' => null],
        ]]);
    }

    public function test_the_designated_seado_list_comes_from_the_office_order(): void
    {
        $designated = require config_path('seados.php');

        $names = array_column($designated['designated'], 'name');

        $this->assertCount(33, $names);
        $this->assertSame($names, array_unique($names));
        $this->assertContains('ERIC A. FETALCORIN', $names);
        $this->assertContains('KEVIN B. BRIONES', $names);

        foreach ($designated['designated'] as $seado) {
            $this->assertContains($seado['office'], config('offices.list'));
        }
    }

    public function test_designated_seados_are_listed_in_their_office_before_handling_a_case(): void
    {
        $this->handled('BUSY SEADO', 3, 'RO-V-SPO');
        $this->designate('NEW SORSOGON SEADO', 'RO-V-SPO');
        $this->designate('NEW ALBAY SEADO', 'RO-V-APFO');

        $response = $this->actingAs($this->admin)
            ->get(route('seado-assignment.index', ['office' => 'RO-V-SPO']))
            ->assertOk();

        $this->assertSame(['NEW SORSOGON SEADO', 'BUSY SEADO'], $response->viewData('roster')->pluck('name')->all());
        $this->assertSame(['NEW SORSOGON SEADO', 'BUSY SEADO'], $response->viewData('roulette')->pluck('name')->all());
        $this->assertContains('NEW SORSOGON SEADO', $response->viewData('excludable')->all());
    }

    public function test_a_designated_seado_already_on_cases_is_listed_once(): void
    {
        $this->handled('ROLDAN MARK R. REYES', 2, 'RO-V-CPO');
        $this->designate('Roldan Mark R.  Reyes', 'RO-V-CPO');

        $response = $this->actingAs($this->admin)
            ->get(route('seado-assignment.index'))
            ->assertOk();

        $roster = $response->viewData('roster');

        $this->assertCount(1, $roster);
        $this->assertSame(2, $roster->first()['total']);
    }

    public function test_a_designated_seado_can_be_assigned_and_appears_in_the_filters(): void
    {
        $this->designate('NEW ALBAY SEADO', 'RO-V-APFO');

        $rfa = Rfa::factory()->create(['office' => 'RO-V-APFO']);

        $this->actingAs($this->admin)
            ->post(route('seado-assignment.assign', $rfa), [
                'seado_name' => 'NEW ALBAY SEADO',
                'date_assigned_seado' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('NEW ALBAY SEADO', $rfa->fresh()->seado_name);

        $this->designate('UNASSIGNED SEADO', 'RO-V-APFO');

        $listing = $this->actingAs($this->admin)->get(route('listing'))->assertOk();

        $this->assertSame(['NEW ALBAY SEADO', 'UNASSIGNED SEADO'], $listing->viewData('seados')->all());

        $this->actingAs($this->admin)
            ->get(route('rfas.show', $rfa))
            ->assertOk()
            ->assertSee('<option value="UNASSIGNED SEADO"></option>', false);
    }

    private function handled(string $name, int $count, string $office = 'RO-V-APFO'): void
    {
        Rfa::factory()->count($count)->create([
            'office' => $office,
            'seado_name' => $name,
            'date_assigned_seado' => '2026-08-04',
        ]);
    }

    public function test_the_roster_lists_seados_with_the_fewest_handled_first(): void
    {
        $this->handled('BUSY SEADO', 5);
        $this->handled('LIGHT SEADO', 1);
        $this->handled('MIDDLE SEADO', 3);

        $response = $this->actingAs($this->admin)
            ->get(route('seado-assignment.index'))
            ->assertOk()
            ->assertSeeTextInOrder(['LIGHT SEADO', 'MIDDLE SEADO', 'BUSY SEADO']);

        $this->assertSame(
            ['LIGHT SEADO', 'MIDDLE SEADO', 'BUSY SEADO'],
            $response->viewData('roster')->pluck('name')->all()
        );
    }

    public function test_the_province_filter_counts_only_that_office(): void
    {
        $this->handled('ALBAY SEADO', 2, 'RO-V-APFO');
        $this->handled('SORSOGON SEADO', 2, 'RO-V-SPO');

        $response = $this->actingAs($this->admin)
            ->get(route('seado-assignment.index', ['office' => 'RO-V-SPO']))
            ->assertOk();

        $this->assertSame(['SORSOGON SEADO'], $response->viewData('roster')->pluck('name')->all());
    }

    public function test_assign_offers_the_five_least_handled_seados_in_the_case_office(): void
    {
        foreach (range(1, 7) as $count) {
            $this->handled('SEADO ' . $count, $count);
        }

        $this->handled('OTHER OFFICE SEADO', 1, 'RO-V-SPO');

        $waiting = Rfa::factory()->create(['office' => 'RO-V-APFO', 'docket_no' => 'WAITING-1']);

        $response = $this->actingAs($this->admin)
            ->get(route('seado-assignment.index'))
            ->assertOk()
            ->assertSeeText('WAITING-1')
            ->assertSeeText('Assign to a SEADO');

        $this->assertSame(
            ['SEADO 1', 'SEADO 2', 'SEADO 3', 'SEADO 4', 'SEADO 5'],
            $response->viewData('suggestions')->get('RO-V-APFO')->pluck('name')->all()
        );

        $this->assertTrue($waiting->is($response->viewData('queue')->first()));
    }

    public function test_assigning_sets_the_seado_date_and_status_and_notifies_the_account(): void
    {
        $seadoUser = User::factory()->withRole('seado')->create(['name' => 'ANA SEADO', 'office' => 'Albay PFO']);

        $rfa = Rfa::factory()->create(['office' => 'RO-V-APFO']);

        $this->actingAs($this->admin)
            ->post(route('seado-assignment.assign', $rfa), [
                'seado_name' => 'ANA SEADO',
                'date_assigned_seado' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $rfa->refresh();

        $this->assertSame($seadoUser->id, $rfa->seado_id);
        $this->assertSame('ANA SEADO', $rfa->seado_name);
        $this->assertSame(now()->toDateString(), $rfa->date_assigned_seado->toDateString());
        $this->assertSame(Workflow::ASSIGNED_TO_SEADO, $rfa->status);

        $this->assertTrue(
            AppNotification::query()
                ->where('user_id', $seadoUser->id)
                ->where('category', 'assignment')
                ->exists()
        );
    }

    public function test_an_assigned_case_or_unknown_seado_is_refused(): void
    {
        $this->handled('KNOWN SEADO', 1);

        $assigned = Rfa::factory()->create(['seado_name' => 'KNOWN SEADO']);

        $this->actingAs($this->admin)
            ->post(route('seado-assignment.assign', $assigned), [
                'seado_name' => 'KNOWN SEADO',
                'date_assigned_seado' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('seado_name');

        $open = Rfa::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('seado-assignment.assign', $open), [
                'seado_name' => 'MADE UP PERSON',
                'date_assigned_seado' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('seado_name');

        $this->assertNull($open->fresh()->seado_name);
    }

    public function test_the_page_needs_the_assign_permission(): void
    {
        $viewer = User::factory()->withRole('viewer')->create();

        $this->actingAs($viewer)
            ->get(route('seado-assignment.index'))
            ->assertForbidden();
    }
    public function test_the_roulette_draws_from_the_five_least_handled_in_the_province(): void
    {
        foreach (range(1, 7) as $count) {
            $this->handled('SEADO ' . $count, $count, 'RO-V-SPO');
        }

        $this->handled('ALBAY SEADO', 1, 'RO-V-APFO');

        $response = $this->actingAs($this->admin)
            ->get(route('seado-assignment.index', ['office' => 'RO-V-SPO']))
            ->assertOk()
            ->assertSeeText('Random SEADO Draw')
            ->assertSee('seadoDraw(', false);

        $this->assertSame(
            ['SEADO 1', 'SEADO 2', 'SEADO 3', 'SEADO 4', 'SEADO 5'],
            $response->viewData('roulette')->pluck('name')->all()
        );
    }
    public function test_excluded_seados_are_skipped_and_the_next_least_handled_take_their_place(): void
    {
        foreach (range(1, 7) as $count) {
            $this->handled('SEADO ' . $count, $count);
        }

        $this->actingAs($this->admin)
            ->post(route('seado-assignment.exclusions.store'), [
                'seado_name' => 'seado 2',
                'reason' => 'On leave',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('roulette_exclusions', [
            'seado_key' => 'SEADO 2',
            'seado_name' => 'SEADO 2',
            'reason' => 'On leave',
            'excluded_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('seado-assignment.index'))
            ->assertOk()
            ->assertSeeText('On leave');

        $this->assertSame(
            ['SEADO 1', 'SEADO 3', 'SEADO 4', 'SEADO 5', 'SEADO 6'],
            $response->viewData('roulette')->pluck('name')->all()
        );

        $this->assertNotContains('SEADO 2', $response->viewData('excludable')->all());

        // The assign dialog still offers everyone; only the roulette skips.
        $this->assertContains('SEADO 2', $response->viewData('roster')->take(5)->pluck('name')->all());
    }

    public function test_removing_an_exclusion_puts_the_seado_back(): void
    {
        $this->handled('SEADO A', 1);

        $this->actingAs($this->admin)
            ->post(route('seado-assignment.exclusions.store'), ['seado_name' => 'SEADO A']);

        $exclusion = \App\Models\RouletteExclusion::query()->firstOrFail();

        $response = $this->actingAs($this->admin)->get(route('seado-assignment.index'));

        $this->assertTrue($response->viewData('roulette')->isEmpty());
        $response->assertSeeText('Every SEADO is excluded');

        $this->actingAs($this->admin)
            ->delete(route('seado-assignment.exclusions.destroy', $exclusion))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseCount('roulette_exclusions', 0);

        $this->assertSame(
            ['SEADO A'],
            $this->actingAs($this->admin)->get(route('seado-assignment.index'))->viewData('roulette')->pluck('name')->all()
        );
    }

    public function test_unknown_or_duplicate_exclusions_are_refused(): void
    {
        $this->handled('SEADO A', 1);

        $this->actingAs($this->admin)
            ->post(route('seado-assignment.exclusions.store'), ['seado_name' => 'NOBODY'])
            ->assertSessionHasErrors('seado_name');

        $this->actingAs($this->admin)
            ->post(route('seado-assignment.exclusions.store'), ['seado_name' => 'SEADO A'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->post(route('seado-assignment.exclusions.store'), ['seado_name' => 'SEADO A'])
            ->assertSessionHasErrors('seado_name');

        $this->assertDatabaseCount('roulette_exclusions', 1);
    }

    public function test_managing_exclusions_needs_the_assign_permission(): void
    {
        $this->handled('SEADO A', 1);

        $viewer = User::factory()->withRole('viewer')->create();

        $this->actingAs($viewer)
            ->post(route('seado-assignment.exclusions.store'), ['seado_name' => 'SEADO A'])
            ->assertForbidden();

        $this->assertDatabaseCount('roulette_exclusions', 0);
    }
}
