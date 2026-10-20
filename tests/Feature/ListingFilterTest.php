<?php

namespace Tests\Feature;

use App\Models\Rfa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private Rfa $albay;

    private Rfa $masbate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->viewer = User::factory()
            ->withRole('viewer')
            ->create();

        $this->albay = Rfa::factory()->create([
            'office' => 'RO-V-APFO',
            'seado_name' => 'Juan Dela Cruz',
        ]);

        $this->masbate = Rfa::factory()->create([
            'office' => 'RO-V-MPO',
            'seado_name' => 'Maria Santos',
        ]);
    }

    public function test_the_dropdowns_list_every_office_and_seado(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get(route('listing'))
            ->assertOk()
            ->assertSeeText('All Offices')
            ->assertSeeText('All SEADOs')
            ->assertSee('value="Juan Dela Cruz"', false)
            ->assertSee('value="Maria Santos"', false);

        foreach (config('offices.list') as $label => $code) {
            $response->assertSee('value="' . $code . '"', false);
        }
    }

    public function test_the_office_filter_limits_the_listing(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('listing', ['office' => 'RO-V-APFO']))
            ->assertOk()
            ->assertViewHas('filteredCount', 1)
            ->assertSee($this->albay->reference_no)
            ->assertDontSee($this->masbate->reference_no);
    }

    public function test_the_seado_filter_limits_the_listing(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('listing', ['seado' => 'Maria Santos']))
            ->assertOk()
            ->assertViewHas('filteredCount', 1)
            ->assertSee($this->masbate->reference_no)
            ->assertDontSee($this->albay->reference_no);
    }

    public function test_an_unknown_office_value_is_ignored(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('listing', ['office' => 'NOT-AN-OFFICE']))
            ->assertOk()
            ->assertViewHas('filteredCount', 2);
    }
}
