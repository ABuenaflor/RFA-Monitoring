<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\Rfa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConferenceScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $seado;

    private User $otherSeado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->seado = User::factory()->withRole('seado')->create(['name' => 'Juana Dela Cruz']);

        $this->otherSeado = User::factory()->withRole('seado')->create(['name' => 'Pedro Penduko']);
    }

    public function test_a_user_sees_only_their_own_conferences_for_the_week(): void
    {
        Rfa::factory()->create([
            'docket_no' => 'MINE-BY-ACCOUNT',
            'seado_id' => $this->seado->id,
            'seado_name' => $this->seado->name,
            'requesting_party' => 'Maria Requester',
            'responding_party' => 'Acme Responding Corp',
            'date_initial_conference' => '2026-10-07',
        ]);

        // Imported case: name only, different case and spacing.
        Rfa::factory()->create([
            'docket_no' => 'MINE-BY-NAME',
            'seado_name' => '  JUANA DELA CRUZ ',
            'date_second_conference' => '2026-10-09',
        ]);

        Rfa::factory()->create([
            'docket_no' => 'SOMEONE-ELSE',
            'seado_id' => $this->otherSeado->id,
            'seado_name' => $this->otherSeado->name,
            'date_initial_conference' => '2026-10-07',
        ]);

        Rfa::factory()->create([
            'docket_no' => 'MINE-NEXT-WEEK',
            'seado_id' => $this->seado->id,
            'date_initial_conference' => '2026-10-14',
        ]);

        $response = $this->actingAs($this->seado)
            ->get(route('schedule.index', ['week' => '2026-10-08']))
            ->assertOk()
            ->assertSeeText('Oct 05 – Oct 11, 2026')
            ->assertSeeText('MINE-BY-ACCOUNT')
            ->assertSeeText('MINE-BY-NAME')
            ->assertSeeText('2nd Conference')
            ->assertSeeText('Maria Requester')
            ->assertSeeText('Acme Responding Corp')
            ->assertDontSeeText('SOMEONE-ELSE')
            ->assertViewHas('total', 2);

        $dockets = $response->viewData('days')
            ->flatMap(fn (array $day) => $day['entries'])
            ->map(fn (array $entry) => $entry['rfa']->docket_no)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['MINE-BY-ACCOUNT', 'MINE-BY-NAME'], $dockets);

        // Next week's case is only pointed to as the next conference.
        $this->assertSame('MINE-NEXT-WEEK', $response->viewData('upcoming')['rfa']->docket_no);
    }

    public function test_the_other_user_sees_only_theirs(): void
    {
        Rfa::factory()->create([
            'docket_no' => 'JUANAS-CASE',
            'seado_id' => $this->seado->id,
            'date_initial_conference' => '2026-10-07',
        ]);

        $this->actingAs($this->otherSeado)
            ->get(route('schedule.index', ['week' => '2026-10-07']))
            ->assertOk()
            ->assertDontSeeText('JUANAS-CASE')
            ->assertViewHas('total', 0);
    }

    public function test_the_week_shows_monday_to_friday_and_marks_holidays(): void
    {
        Holiday::create(['date' => '2026-10-07', 'name' => 'Town Fiesta', 'type' => 'event']);

        $response = $this->actingAs($this->seado)
            ->get(route('schedule.index', ['week' => '2026-10-07']))
            ->assertOk()
            ->assertSeeText('Town Fiesta')
            ->assertSeeText('Friday')
            ->assertDontSeeText('Saturday');

        $this->assertCount(5, $response->viewData('days'));
    }
}
