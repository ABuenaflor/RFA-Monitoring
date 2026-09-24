<?php

namespace Tests\Feature;

use App\Models\Rfa;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PctOfficeFilterTest extends TestCase
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

        /*
        | Both have an active "Date Filed - Interviewer Assignment" timer: online, filed yesterday, unassigned (Nearing PCT).
        */

        $this->albay = Rfa::factory()->create([
            'office' => 'RO-V-APFO',
            'date_filed' => now()->subDays(1)->toDateString(),
        ]);

        $this->masbate = Rfa::factory()->create([
            'office' => 'RO-V-MPO',
            'date_filed' => now()->subDays(1)->toDateString(),
        ]);
    }

    public function test_the_dropdown_lists_every_office(): void
    {
        $response = $this->actingAs($this->viewer)
            ->get(route('pct-process'))
            ->assertOk()
            ->assertSeeText('All Offices');

        foreach (config('offices.list') as $label => $code) {
            $response->assertSee('value="' . $code . '"', false);
            $response->assertSeeText($label . ' (' . $code . ')');
        }
    }

    public function test_without_a_filter_every_office_is_shown(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('pct-process'))
            ->assertOk()
            ->assertSee($this->albay->reference_no)
            ->assertSee($this->masbate->reference_no)
            ->assertViewHas(
                'activeSummary',
                fn (array $summary) => $summary['total'] === 2
            );
    }

    public function test_selecting_an_office_limits_the_whole_page_to_it(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('pct-process', ['office' => 'RO-V-APFO']))
            ->assertOk()
            ->assertSee($this->albay->reference_no)
            ->assertDontSee($this->masbate->reference_no)
            ->assertViewHas(
                'activeSummary',
                fn (array $summary) => $summary['total'] === 1
                    && $summary['nearing'] === 1
            )
            ->assertViewHas('officeLabel', 'Albay PFO');
    }

    public function test_the_regional_office_can_be_selected(): void
    {
        $regional = Rfa::factory()->create([
            'office' => 'RO-V',
            'date_filed' => now()->subDays(1)->toDateString(),
        ]);

        $this->actingAs($this->viewer)
            ->get(route('pct-process', ['office' => 'RO-V']))
            ->assertOk()
            ->assertSee($regional->reference_no)
            ->assertDontSee($this->albay->reference_no);
    }

    public function test_the_office_filter_combines_with_the_status_filter(): void
    {
        $breached = Rfa::factory()->create([
            'office' => 'RO-V-APFO',
            'date_filed' => now()->subDays(10)->toDateString(),
        ]);

        $this->actingAs($this->viewer)
            ->get(route('pct-process', [
                'office' => 'RO-V-APFO',
                'pct_status' => 'beyond',
            ]))
            ->assertOk()
            ->assertViewHas(
                'activePaginator',
                fn ($paginator) => $paginator->total() === 1
                    && $paginator->items()[0]['rfa']->is($breached)
            );
    }

    public function test_an_unknown_office_value_is_ignored(): void
    {
        $this->actingAs($this->viewer)
            ->get(route('pct-process', ['office' => 'NOT-AN-OFFICE']))
            ->assertOk()
            ->assertViewHas('officeFilter', '')
            ->assertSee($this->albay->reference_no)
            ->assertSee($this->masbate->reference_no);
    }
}
