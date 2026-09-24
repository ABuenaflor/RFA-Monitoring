<?php

namespace Tests\Feature;

use App\Models\Rfa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PctClickableCardsTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    /**
     * @var array<string, Rfa>
     */
    private array $cases = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->viewer = User::factory()
            ->withRole('viewer')
            ->create();

        /*
        | One active "Date Filed - Interviewer Assignment" timer per status: an online case filed N days ago (2-day limit), unassigned.
        */

        foreach ([
            'within' => 0,
            'nearing' => 1,
            'on' => 2,
            'beyond' => 8,
        ] as $status => $days) {
            $this->cases[$status] = Rfa::factory()->create([
                'office' => 'RO-V-APFO',
                'date_filed' => now()->subDays($days)->toDateString(),
            ]);
        }
    }

    private function cardLink(array $query): string
    {
        return e(route('pct-process', $query)) . '#active-timers';
    }

    public function test_every_card_links_to_its_list(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get(route('pct-process'))
            ->assertOk();

        $response->assertSee(
            'href="' . $this->cardLink([]) . '"',
            false
        );

        foreach (['within', 'nearing', 'on', 'beyond'] as $status) {
            $response->assertSee(
                'href="' . $this->cardLink(['pct_status' => $status]) . '"',
                false
            );
        }
    }

    public function test_clicking_a_card_lists_only_that_group(): void
    {
        foreach ($this->cases as $status => $rfa) {
            $response = $this->actingAs($this->viewer)
                ->get(route('pct-process', ['pct_status' => $status]))
                ->assertOk()
                ->assertViewHas(
                    'activePaginator',
                    fn ($paginator) => $paginator->total() === 1
                        && $paginator->items()[0]['rfa']->is($rfa)
                )
                ->assertSeeText('1 case')
                ->assertSeeText('Show all')
                ->assertSee('aria-current="true"', false);

            /*
            | The cards keep showing every group's count.
            */

            $response->assertViewHas(
                'activeSummary',
                fn (array $summary) => $summary['total'] === 4
            );
        }
    }

    public function test_the_active_timers_card_shows_every_group(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('pct-process'))
            ->assertOk()
            ->assertViewHas(
                'activePaginator',
                fn ($paginator) => $paginator->total() === 4
            )
            ->assertDontSeeText('Show all');
    }

    public function test_card_links_keep_the_selected_office(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('pct-process', ['office' => 'RO-V-APFO']))
            ->assertOk()
            ->assertSee(
                'href="' . $this->cardLink([
                    'office' => 'RO-V-APFO',
                    'pct_status' => 'beyond',
                ]) . '"',
                false
            )
            ->assertSee(
                'href="' . $this->cardLink(['office' => 'RO-V-APFO']) . '"',
                false
            );
    }

    public function test_a_card_clears_search_and_checkpoint_filters(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get(route('pct-process', [
                'search' => 'something',
                'stage' => 'interview',
            ]))
            ->assertOk();

        $response->assertSee(
            'href="' . $this->cardLink(['pct_status' => 'nearing']) . '"',
            false
        );
    }
}
