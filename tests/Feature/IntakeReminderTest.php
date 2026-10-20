<?php

namespace Tests\Feature;

use App\Models\Rfa;
use App\Models\User;
use App\Support\Workflow;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * After sign-in a SEADO is reminded of their open cases missing a
 * Date Interviewed or Date TA/NORES, and the case page requires both.
 */
class IntakeReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $seado;

    private Rfa $mine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->seado = User::factory()
            ->withRole('seado')
            ->create(['name' => 'Sheiva A. Llamera']);

        // Imported case: the SEADO is only a name, in a different case.
        $this->mine = Rfa::factory()->create([
            'seado_name' => 'SHEIVA A. LLAMERA ',
            'requesting_party' => 'Juan Dela Cruz',
            'responding_party' => 'Thom Minimart',
            'docket_no' => 'SEAD-RO-V-06-00001-2026-OS',
            'date_filed' => '2026-08-03',
        ]);
    }

    private function signIn(User $user): void
    {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect();
    }

    public function test_signing_in_shows_the_reminder_with_case_details(): void
    {
        $this->signIn($this->seado);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('1 case needs dates')
            ->assertSeeText('Juan Dela Cruz')
            ->assertSeeText('Thom Minimart')
            ->assertSeeText('SEAD-RO-V-06-00001-2026-OS')
            ->assertSeeText('Filed Aug 03, 2026')
            ->assertSee('href="' . route('rfas.show', $this->mine) . '"', false);
    }

    public function test_the_reminder_lists_only_the_users_open_cases_missing_dates(): void
    {
        $linked = Rfa::factory()->create([
            'seado_id' => $this->seado->id,
            'requesting_party' => 'Linked Account Case',
        ]);

        Rfa::factory()->create([
            'seado_name' => 'Someone Else',
            'requesting_party' => 'Other SEADO Case',
        ]);

        Rfa::factory()->disposed()->create([
            'seado_name' => 'Sheiva A. Llamera',
            'requesting_party' => 'Closed Case',
        ]);

        Rfa::factory()->ongoing()->create([
            'seado_name' => 'Sheiva A. Llamera',
            'requesting_party' => 'Complete Case',
            'date_ta_nores' => '2026-08-06',
        ]);

        $this->signIn($this->seado);

        $this->get(route('dashboard'))
            ->assertSeeText('2 cases need dates')
            ->assertSeeText('Juan Dela Cruz')
            ->assertSeeText($linked->requesting_party)
            ->assertDontSeeText('Other SEADO Case')
            ->assertDontSeeText('Closed Case')
            ->assertDontSeeText('Complete Case');
    }

    public function test_dismissing_hides_the_reminder_until_the_next_sign_in(): void
    {
        $this->signIn($this->seado);

        $this->post(route('intake-reminder.dismiss'))->assertNoContent();

        $this->get(route('dashboard'))->assertDontSee('intake-reminder-title', false);

        $this->post('/logout');

        $this->signIn($this->seado);

        $this->get(route('dashboard'))->assertSeeText('1 case needs dates');
    }

    public function test_the_case_page_requires_both_dates_from_its_seado(): void
    {
        $this->actingAs($this->seado)
            ->get(route('rfas.show', $this->mine))
            ->assertOk()
            ->assertSeeText('Enter the missing case dates')
            ->assertSee('action="' . route('rfas.intake-dates', $this->mine) . '"', false);

        $this->actingAs($this->seado)
            ->put(route('rfas.intake-dates', $this->mine), [])
            ->assertSessionHasErrors(['date_interview', 'date_ta_nores']);

        $this->actingAs($this->seado)
            ->put(route('rfas.intake-dates', $this->mine), [
                'date_interview' => '2026-08-03',
                'date_ta_nores' => '2026-08-04',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('rfas.show', $this->mine));

        $this->mine->refresh();

        $this->assertSame('2026-08-03', $this->mine->date_interview->toDateString());
        $this->assertSame('2026-08-04', $this->mine->date_ta_nores->toDateString());

        $this->actingAs($this->seado)
            ->get(route('rfas.show', $this->mine))
            ->assertDontSeeText('Enter the missing case dates');
    }

    public function test_dates_cannot_be_in_the_future_or_break_the_date_order(): void
    {
        $this->actingAs($this->seado)
            ->put(route('rfas.intake-dates', $this->mine), [
                'date_interview' => now()->addDay()->toDateString(),
                'date_ta_nores' => '2026-08-04',
            ])
            ->assertSessionHasErrors('date_interview');

        $this->actingAs($this->seado)
            ->put(route('rfas.intake-dates', $this->mine), [
                'date_interview' => '2026-08-01',
                'date_ta_nores' => '2026-08-04',
            ])
            ->assertSessionHasErrors('chronology');

        $this->assertNull($this->mine->fresh()->date_interview);
    }

    public function test_other_users_do_not_get_the_form_and_viewers_cannot_save(): void
    {
        $viewer = User::factory()->withRole('viewer')->create();

        $this->actingAs($viewer)
            ->get(route('rfas.show', $this->mine))
            ->assertOk()
            ->assertDontSeeText('Enter the missing case dates');

        $this->actingAs($viewer)
            ->put(route('rfas.intake-dates', $this->mine), [
                'date_interview' => '2026-08-03',
                'date_ta_nores' => '2026-08-04',
            ])
            ->assertForbidden();
    }

    public function test_the_cases_interviewer_is_not_reminded(): void
    {
        $interviewer = User::factory()
            ->withRole('interviewer')
            ->create(['name' => 'Rico Interviewer']);

        $this->mine->update(['interviewer_name' => 'Rico Interviewer']);

        $this->signIn($interviewer);

        $this->get(route('dashboard'))
            ->assertDontSee('intake-reminder-title', false);

        $this->get(route('rfas.show', $this->mine))
            ->assertDontSeeText('Enter the missing case dates');
    }

    public function test_a_case_with_both_dates_needs_nothing(): void
    {
        $this->mine->update([
            'date_interview' => '2026-08-03',
            'date_ta_nores' => '2026-08-03',
            'status' => Workflow::FOR_VALIDATION,
        ]);

        $this->assertFalse($this->mine->fresh()->needsIntakeDates());

        $this->signIn($this->seado);

        $this->get(route('dashboard'))->assertDontSee('intake-reminder-title', false);
    }
}
