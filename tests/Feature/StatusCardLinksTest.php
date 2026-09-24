<?php

namespace Tests\Feature;

use App\Models\Rfa;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Pending / Ongoing / Disposed cards on the RFA listing and the
 * dashboard open the listing filtered to that group.
 */
class StatusCardLinksTest extends TestCase
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

        Rfa::factory()->count(2)->create();
        Rfa::factory()->ongoing()->create();
        Rfa::factory()->disposed()->count(3)->create();
    }

    private function listingLink(array $query = []): string
    {
        return 'href="' . e(route('listing', $query)) . '#rfa-records"';
    }

    /*
    |--------------------------------------------------------------------------
    | RFA listing
    |--------------------------------------------------------------------------
    */

    public function test_listing_cards_link_to_their_group(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get(route('listing'))
            ->assertOk()
            ->assertSee($this->listingLink(), false);

        foreach (['pending', 'ongoing', 'disposed'] as $bucket) {
            $response->assertSee(
                $this->listingLink(['monitoring_bucket' => $bucket]),
                false
            );
        }
    }

    public function test_clicking_a_listing_card_lists_only_that_group(): void
    {
        foreach (['pending' => 2, 'ongoing' => 1, 'disposed' => 3] as $bucket => $expected) {
            $this->actingAs($this->viewer)
                ->get(route('listing', ['monitoring_bucket' => $bucket]))
                ->assertOk()
                ->assertViewHas('filteredCount', $expected)
                ->assertSeeText('Showing: ' . ucfirst($bucket))
                ->assertSeeText('Show all')
                ->assertSee('aria-current="true"', false);
        }
    }

    public function test_listing_cards_keep_sorting_but_clear_other_filters(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('listing', [
                'search' => 'something',
                'status' => 'validated',
                'sort' => 'date_filed',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertSee(
                $this->listingLink([
                    'sort' => 'date_filed',
                    'direction' => 'asc',
                    'monitoring_bucket' => 'pending',
                ]),
                false
            );
    }

    public function test_the_total_card_shows_every_record(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('listing'))
            ->assertViewHas('filteredCount', 6)
            ->assertDontSeeText('Show all');
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    public function test_dashboard_cards_open_the_filtered_listing(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($this->listingLink(), false)
            ->assertSeeText('View list')
            ->assertDontSee('openStatus', false);

        foreach (['pending', 'ongoing', 'disposed'] as $bucket) {
            $response->assertSee(
                $this->listingLink(['monitoring_bucket' => $bucket]),
                false
            );
        }
    }

    public function test_dashboard_cards_are_not_links_without_listing_access(): void
    {
        $role = Role::create([
            'name' => 'Dashboard Only',
            'slug' => 'dashboard-only',
            'is_system' => false,
        ]);

        $role->syncPermissions([Permissions::DASHBOARD_VIEW]);

        $user = User::factory()->withRole($role)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('#rfa-records', false)
            ->assertDontSeeText('View list');
    }
}
