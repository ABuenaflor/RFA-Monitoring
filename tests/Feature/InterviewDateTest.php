<?php

namespace Tests\Feature;

use App\Models\Rfa;
use App\Models\User;
use App\Services\RfaWorkflowService;
use App\Support\Workflow;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The office export has no Date Interviewed or Date TA/NORES. On-site cases
 * take their Date Filed as the interview date; both dates can be entered on
 * the case page and survive a re-import.
 */
class InterviewDateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()
            ->administrator()
            ->create();
    }

    public function test_the_onsite_filing_date_applies_to_onsite_cases_only(): void
    {
        $this->assertSame('2026-08-03', RfaWorkflowService::onsiteFilingDate('onsite', '2026-08-03'));
        $this->assertSame('2026-08-03', RfaWorkflowService::onsiteFilingDate('Walk-in', '2026-08-03'));

        $this->assertNull(RfaWorkflowService::onsiteFilingDate('online', '2026-08-03'));
        $this->assertNull(RfaWorkflowService::onsiteFilingDate(null, '2026-08-03'));
        $this->assertNull(RfaWorkflowService::onsiteFilingDate('onsite', null));
    }

    public function test_the_backfill_aligns_onsite_cases_and_leaves_online_ones(): void
    {
        $onsite = Rfa::factory()->create(['mode_of_filing' => 'onsite']);

        $online = Rfa::factory()->create(['mode_of_filing' => 'online']);

        $lateAssignment = Rfa::factory()->create([
            'mode_of_filing' => 'onsite',
            'date_assigned_interviewer' => '2026-08-20',
        ]);

        $entered = Rfa::factory()->create([
            'mode_of_filing' => 'onsite',
            'date_interview' => '2026-08-05',
        ]);

        $this->artisan('rfa:apply-onsite-dates')->assertSuccessful();

        $onsite->refresh();

        $this->assertSame('2026-08-03', $onsite->date_assigned_interviewer->toDateString());
        $this->assertSame('2026-08-03', $onsite->date_interview->toDateString());
        $this->assertSame(Workflow::FOR_VALIDATION, $onsite->status);
        $this->assertSame(Workflow::BUCKET_ONGOING, $onsite->monitoring_bucket);
        $this->assertDatabaseHas('rfa_activities', [
            'rfa_id' => $onsite->id,
            'title' => 'On-site dates set from Date Filed',
        ]);

        $this->assertNull($online->fresh()->date_assigned_interviewer);
        $this->assertNull($online->fresh()->date_interview);

        // A later assignment date is replaced by the Date Filed.
        $lateAssignment->refresh();
        $this->assertSame('2026-08-03', $lateAssignment->date_assigned_interviewer->toDateString());
        $this->assertSame('2026-08-03', $lateAssignment->date_interview->toDateString());

        // An interview date already recorded is kept.
        $this->assertSame('2026-08-05', $entered->fresh()->date_interview->toDateString());
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $onsite = Rfa::factory()->create(['mode_of_filing' => 'onsite']);

        $this->artisan('rfa:apply-onsite-dates', ['--dry-run' => true])
            ->expectsOutputToContain('Would update 1')
            ->assertSuccessful();

        $this->assertNull($onsite->fresh()->date_interview);
        $this->assertNull($onsite->fresh()->date_assigned_interviewer);
    }

    public function test_changing_the_date_filed_moves_the_onsite_assignment_date(): void
    {
        $rfa = Rfa::factory()->create(['mode_of_filing' => 'onsite']);

        $this->actingAs($this->admin)
            ->put(route('rfas.workflow', $rfa), [
                'status' => Workflow::FOR_VALIDATION,
                'date_filed' => '2026-08-04',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-04', $rfa->fresh()->date_assigned_interviewer->toDateString());
    }

    public function test_the_onsite_assignment_date_cannot_be_typed_over(): void
    {
        $rfa = Rfa::factory()->create(['mode_of_filing' => 'onsite']);

        $this->actingAs($this->admin)
            ->get(route('rfas.show', $rfa))
            ->assertSeeText('On-site filing: always the same as the Date Filed.');

        $this->actingAs($this->admin)
            ->put(route('rfas.assignment', $rfa), [
                'date_assigned_interviewer' => '2026-08-09',
            ]);

        $this->assertSame('2026-08-03', $rfa->fresh()->date_assigned_interviewer->toDateString());
    }

    public function test_online_assignment_dates_stay_editable(): void
    {
        $rfa = Rfa::factory()->create(['mode_of_filing' => 'online']);

        $this->actingAs($this->admin)
            ->put(route('rfas.assignment', $rfa), [
                'date_assigned_interviewer' => '2026-08-05',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-08-05', $rfa->fresh()->date_assigned_interviewer->toDateString());
    }

    public function test_an_import_assumes_the_interview_date_for_onsite_rows_only(): void
    {
        $this->import([
            ['RO-V', 'SEAD-RO-V-07-00001-2026-OS', 'Ana Cruz', 'Shop A', '2026-07-01', 'Pending', ''],
            ['RO-V', 'SEAD-RO-V-07-00002-2026-OL', 'Ben Reyes', 'Shop B', '2026-07-02', 'Pending', ''],
        ]);

        $onsite = Rfa::query()->where('docket_no', 'SEAD-RO-V-07-00001-2026-OS')->firstOrFail();
        $online = Rfa::query()->where('docket_no', 'SEAD-RO-V-07-00002-2026-OL')->firstOrFail();

        $this->assertSame('2026-07-01', $onsite->date_interview->toDateString());
        $this->assertSame('2026-07-01', $onsite->date_assigned_interviewer->toDateString());
        $this->assertNull($online->date_interview);
        $this->assertNull($online->date_assigned_interviewer);
    }

    public function test_the_import_reads_date_ta_nores(): void
    {
        $this->import([
            ['RO-V', 'SEAD-RO-V-07-00003-2026-OL', 'Cara Lim', 'Shop C', '2026-07-03', 'Pending', '2026-07-04'],
        ]);

        $this->assertSame(
            '2026-07-04',
            Rfa::query()->where('docket_no', 'SEAD-RO-V-07-00003-2026-OL')->firstOrFail()->date_ta_nores->toDateString()
        );
    }

    public function test_a_reimport_keeps_dates_entered_on_the_case(): void
    {
        $row = ['RO-V', 'SEAD-RO-V-07-00004-2026-OL', 'Dan Uy', 'Shop D', '2026-07-06', 'Pending', ''];

        $this->import([$row]);

        $rfa = Rfa::query()->where('docket_no', 'SEAD-RO-V-07-00004-2026-OL')->firstOrFail();

        $rfa->update([
            'date_interview' => '2026-07-08',
            'date_ta_nores' => '2026-07-07',
        ]);

        $this->import([$row]);

        $rfa->refresh();

        $this->assertSame('2026-07-08', $rfa->date_interview->toDateString());
        $this->assertSame('2026-07-07', $rfa->date_ta_nores->toDateString());
    }

    public function test_both_dates_can_be_entered_on_the_case_page(): void
    {
        $rfa = Rfa::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('rfas.show', $rfa))
            ->assertOk()
            ->assertSee('name="date_interview"', false)
            ->assertSee('name="date_ta_nores"', false)
            ->assertSeeText('Date Interviewed')
            ->assertSeeText('Date TA/NORES');

        $this->actingAs($this->admin)
            ->put(route('rfas.workflow', $rfa), [
                'status' => Workflow::FOR_VALIDATION,
                'date_filed' => '2026-08-03',
                'date_ta_nores' => '2026-08-04',
                'date_interview' => '2026-08-05',
            ])
            ->assertRedirect(route('rfas.show', $rfa));

        $rfa->refresh();

        $this->assertSame('2026-08-04', $rfa->date_ta_nores->toDateString());
        $this->assertSame('2026-08-05', $rfa->date_interview->toDateString());
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function import(array $rows): void
    {
        $lines = ['Office,Docket No.,Complainant,Company,Date Filed,Case Status,Date TA/NORES'];

        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }

        $this->actingAs($this->admin)
            ->post(route('imports.store'), [
                'csv_file' => UploadedFile::fake()->createWithContent(
                    'rfas.csv',
                    implode("\n", $lines) . "\n"
                ),
            ])
            ->assertSessionHasNoErrors();
    }
}
