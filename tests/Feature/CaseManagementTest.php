<?php

namespace Tests\Feature;

use App\Models\Rfa;
use App\Models\User;
use App\Support\Workflow;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $seado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->seado = User::factory()
            ->withRole('seado')
            ->create();
    }

    /*
    |--------------------------------------------------------------------------
    | Access
    |--------------------------------------------------------------------------
    */

    public function test_the_case_page_renders_for_a_permitted_user(): void
    {
        $rfa = Rfa::factory()->create();

        $this->actingAs($this->seado)
            ->get(route('rfas.show', $rfa))
            ->assertOk()
            ->assertSee($rfa->reference_no);
    }

    public function test_a_user_without_case_access_is_refused(): void
    {
        $rfa = Rfa::factory()->create();

        $outsider = User::factory()
            ->withRole('viewer')
            ->create();

        $this->actingAs($outsider)
            ->put(route('rfas.details', $rfa), [])
            ->assertForbidden();
    }

    public function test_only_a_disposing_role_can_record_a_disposition(): void
    {
        $rfa = Rfa::factory()->ongoing()->create();

        $interviewer = User::factory()
            ->withRole('interviewer')
            ->create();

        $this->actingAs($interviewer)
            ->put(route('rfas.disposition', $rfa), [
                'disposition_status' => 'settled',
                'date_disposed' => '2026-08-25',
            ])
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Case Information
    |--------------------------------------------------------------------------
    */

    public function test_case_information_is_saved_and_recorded_on_the_timeline(): void
    {
        $rfa = Rfa::factory()->create([
            'requesting_party' => 'Original Name',
        ]);

        $this->actingAs($this->seado)
            ->put(route('rfas.details', $rfa), [
                'requesting_party' => 'Corrected Name',
                'workers_involved' => 12,
            ])
            ->assertRedirect(route('rfas.show', $rfa));

        $rfa->refresh();

        $this->assertSame('Corrected Name', $rfa->requesting_party);

        $this->assertSame(12, $rfa->workers_involved);

        $activity = $rfa->activities()->first();

        $this->assertNotNull($activity);

        $this->assertSame('details', $activity->type);

        $this->assertSame(
            $this->seado->id,
            $activity->user_id
        );

        $this->assertContains(
            'Requesting Party',
            array_column($activity->changes, 'label')
        );
    }

    public function test_an_unchanged_submission_writes_no_timeline_entry(): void
    {
        $rfa = Rfa::factory()->create([
            'requesting_party' => 'Same Name',
        ]);

        $this->actingAs($this->seado)
            ->put(route('rfas.details', $rfa), [
                'requesting_party' => 'Same Name',
            ]);

        $this->assertSame(0, $rfa->activities()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Assignment
    |--------------------------------------------------------------------------
    */

    public function test_assigning_a_system_account_stores_the_id_and_the_name(): void
    {
        $rfa = Rfa::factory()->create();

        $interviewer = User::factory()
            ->withRole('interviewer')
            ->create(['name' => 'Ana Reyes']);

        $this->actingAs($this->seado)
            ->put(route('rfas.assignment', $rfa), [
                'interviewer_user_id' => $interviewer->id,
                'date_assigned_interviewer' => '2026-08-05',
            ])
            ->assertRedirect(route('rfas.show', $rfa));

        $rfa->refresh();

        $this->assertSame($interviewer->id, $rfa->interviewer_id);

        $this->assertSame('Ana Reyes', $rfa->interviewer_name);
    }

    public function test_a_typed_name_clears_any_linked_account(): void
    {
        $interviewer = User::factory()
            ->withRole('interviewer')
            ->create();

        $rfa = Rfa::factory()->create([
            'interviewer_id' => $interviewer->id,
            'interviewer_name' => $interviewer->name,
        ]);

        $this->actingAs($this->seado)
            ->put(route('rfas.assignment', $rfa), [
                'interviewer_name' => 'Field Officer Without An Account',
            ]);

        $rfa->refresh();

        $this->assertNull($rfa->interviewer_id);

        $this->assertSame(
            'Field Officer Without An Account',
            $rfa->interviewer_name
        );
    }

    public function test_assignment_moves_a_pending_case_into_ongoing(): void
    {
        $rfa = Rfa::factory()->create();

        $this->assertSame(
            Workflow::BUCKET_PENDING,
            $rfa->monitoring_bucket
        );

        $this->actingAs($this->seado)
            ->put(route('rfas.assignment', $rfa), [
                'interviewer_name' => 'Ana Reyes',
                'date_assigned_interviewer' => '2026-08-05',
            ]);

        $this->assertSame(
            Workflow::BUCKET_ONGOING,
            $rfa->fresh()->monitoring_bucket
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Chronology
    |--------------------------------------------------------------------------
    */

    public function test_a_date_that_precedes_the_filing_date_is_refused(): void
    {
        $rfa = Rfa::factory()->create([
            'date_filed' => '2026-08-10',
        ]);

        $this->actingAs($this->seado)
            ->put(route('rfas.assignment', $rfa), [
                'date_assigned_interviewer' => '2026-08-01',
            ])
            ->assertSessionHasErrors('chronology');

        $this->assertNull(
            $rfa->fresh()->date_assigned_interviewer
        );
    }

    public function test_an_inconsistency_already_in_the_data_does_not_block_an_unrelated_edit(): void
    {
        /*
        | Imported record whose interview date precedes its filing date.
        */

        $rfa = Rfa::factory()->create([
            'date_filed' => '2026-08-10',
            'date_assigned_interviewer' => '2026-08-01',
        ]);

        $this->actingAs($this->seado)
            ->put(route('rfas.details', $rfa), [
                'requesting_party' => 'Corrected Name',
            ])
            ->assertRedirect(route('rfas.show', $rfa));

        $this->assertSame(
            'Corrected Name',
            $rfa->fresh()->requesting_party
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Workflow
    |--------------------------------------------------------------------------
    */

    public function test_a_case_cannot_be_marked_disposed_without_a_disposal_date(): void
    {
        $rfa = Rfa::factory()->ongoing()->create();

        $this->actingAs($this->seado)
            ->put(route('rfas.workflow', $rfa), [
                'status' => Workflow::DISPOSED,
            ])
            ->assertSessionHasErrors('status');

        $this->assertNotSame(
            Workflow::DISPOSED,
            $rfa->fresh()->status
        );
    }

    public function test_workflow_progress_is_saved(): void
    {
        $rfa = Rfa::factory()->ongoing()->create();

        $this->actingAs($this->seado)
            ->put(route('rfas.workflow', $rfa), [
                'status' => Workflow::VALIDATED,
                'date_filed' => '2026-08-03',
                'date_interview' => '2026-08-07',
                'date_validated' => '2026-08-09',
            ])
            ->assertRedirect(route('rfas.show', $rfa));

        $rfa->refresh();

        $this->assertSame(Workflow::VALIDATED, $rfa->status);

        $this->assertSame(
            '2026-08-09',
            $rfa->date_validated->format('Y-m-d')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Conference
    |--------------------------------------------------------------------------
    */

    public function test_a_second_conference_requires_a_first_conference(): void
    {
        $rfa = Rfa::factory()->ongoing()->create();

        $this->actingAs($this->seado)
            ->put(route('rfas.conference', $rfa), [
                'date_second_conference' => '2026-08-18',
            ])
            ->assertSessionHasErrors('date_second_conference');

        $this->assertNull(
            $rfa->fresh()->date_second_conference
        );
    }

    public function test_conference_dates_are_saved_in_order(): void
    {
        $rfa = Rfa::factory()->ongoing()->create();

        $this->actingAs($this->seado)
            ->put(route('rfas.conference', $rfa), [
                'date_initial_conference' => '2026-08-12',
                'date_second_conference' => '2026-08-18',
            ])
            ->assertRedirect(route('rfas.show', $rfa));

        $rfa->refresh();

        $this->assertSame(
            '2026-08-12',
            $rfa->date_initial_conference->format('Y-m-d')
        );

        $this->assertSame(
            '2026-08-18',
            $rfa->date_second_conference->format('Y-m-d')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Disposition
    |--------------------------------------------------------------------------
    */

    public function test_a_disposition_needs_both_a_status_and_a_date(): void
    {
        $rfa = Rfa::factory()->ongoing()->create();

        $this->actingAs($this->seado)
            ->put(route('rfas.disposition', $rfa), [
                'disposition_status' => 'settled',
            ])
            ->assertSessionHasErrors('date_disposed');

        $this->actingAs($this->seado)
            ->put(route('rfas.disposition', $rfa), [
                'date_disposed' => '2026-08-25',
            ])
            ->assertSessionHasErrors('disposition_status');

        $this->assertNull($rfa->fresh()->date_disposed);
    }

    public function test_recording_a_disposition_closes_the_case(): void
    {
        $rfa = Rfa::factory()->ongoing()->create();

        $this->actingAs($this->seado)
            ->put(route('rfas.disposition', $rfa), [
                'disposition_status' => 'settled',
                'disposition_mode' => 'SC',
                'date_disposed' => '2026-08-25',
                'monetary_benefit' => 15000,
                'workers_benefited' => 4,
            ])
            ->assertRedirect(route('rfas.show', $rfa));

        $rfa->refresh();

        $this->assertSame(Workflow::DISPOSED, $rfa->status);

        $this->assertSame(
            Workflow::BUCKET_DISPOSED,
            $rfa->monitoring_bucket
        );

        $this->assertSame('settled', $rfa->disposition_status);

        $this->assertSame('SC', $rfa->disposition_mode);
    }

    public function test_the_official_disposition_and_the_source_mode_stay_separate(): void
    {
        $rfa = Rfa::factory()->ongoing()->create();

        $this->actingAs($this->seado)
            ->put(route('rfas.disposition', $rfa), [
                'disposition_status' => 'settled',
                'disposition_mode' => 'SWBF',
                'date_disposed' => '2026-08-25',
            ]);

        $rfa->refresh();

        $this->assertNotSame(
            $rfa->disposition_status,
            $rfa->disposition_mode
        );

        $this->assertSame('SWBF', $rfa->disposition_mode);
    }

    /*
    |--------------------------------------------------------------------------
    | Reopen
    |--------------------------------------------------------------------------
    */

    public function test_reopening_clears_the_disposition_and_keeps_the_history(): void
    {
        $rfa = Rfa::factory()->disposed()->create();

        $this->actingAs($this->seado)
            ->put(route('rfas.reopen', $rfa), [
                'reason' => 'Settlement was not honoured.',
            ])
            ->assertRedirect(route('rfas.show', $rfa));

        $rfa->refresh();

        $this->assertNull($rfa->date_disposed);

        $this->assertNull($rfa->disposition_status);

        $this->assertSame(
            Workflow::FOR_DISPOSITION,
            $rfa->status
        );

        $this->assertSame(
            Workflow::BUCKET_ONGOING,
            $rfa->monitoring_bucket
        );

        $activity = $rfa->activities()->first();

        $this->assertSame('reopened', $activity->type);

        $this->assertSame(
            'Settlement was not honoured.',
            $activity->description
        );

        $this->assertContains(
            'Date Disposed',
            array_column($activity->changes, 'label')
        );
    }

    public function test_reopening_requires_a_reason(): void
    {
        $rfa = Rfa::factory()->disposed()->create();

        $this->actingAs($this->seado)
            ->put(route('rfas.reopen', $rfa), [])
            ->assertSessionHasErrors('reason');

        $this->assertNotNull($rfa->fresh()->date_disposed);
    }

    public function test_an_open_case_cannot_be_reopened(): void
    {
        $rfa = Rfa::factory()->ongoing()->create();

        $this->actingAs($this->seado)
            ->put(route('rfas.reopen', $rfa), [
                'reason' => 'Nothing to reopen.',
            ])
            ->assertSessionHasErrors('reason');
    }

    /*
    |--------------------------------------------------------------------------
    | Notes
    |--------------------------------------------------------------------------
    */

    public function test_a_note_is_added_to_the_timeline(): void
    {
        $rfa = Rfa::factory()->create();

        $this->actingAs($this->seado)
            ->post(route('rfas.notes', $rfa), [
                'note' => 'Requesting party called to follow up.',
            ])
            ->assertRedirect(route('rfas.show', $rfa));

        $activity = $rfa->activities()->first();

        $this->assertSame('note', $activity->type);

        $this->assertSame(
            'Requesting party called to follow up.',
            $activity->description
        );
    }
}
