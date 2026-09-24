<?php

namespace Tests\Feature;

use App\Mail\PctAlertDigest;
use App\Models\PctEmailDelivery;
use App\Models\Rfa;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class PctEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $interviewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        User::factory()
            ->administrator()
            ->create();

        $this->interviewer = User::factory()
            ->withRole('interviewer')
            ->create();

        config(['notifications.pct_email.enabled' => true]);

        Mail::fake();
    }

    private function scan(): array
    {
        return app(NotificationService::class)->scanPct();
    }

    private function caseFiledDaysAgo(int $days, ?User $interviewer = null): Rfa
    {
        $interviewer ??= $this->interviewer;

        return Rfa::factory()->create([
            'date_filed' => now()->subDays($days)->toDateString(),
            'interviewer_id' => $interviewer->id,
            'interviewer_name' => $interviewer->name,
        ]);
    }

    public function test_a_breached_case_emails_the_assigned_interviewer(): void
    {
        $rfa = $this->caseFiledDaysAgo(10);

        $result = $this->scan();

        $this->assertSame(1, $result['emails_sent']);

        Mail::assertSent(
            PctAlertDigest::class,
            function (PctAlertDigest $mail) use ($rfa) {
                $html = $mail->render();

                return $mail->hasTo($this->interviewer->email)
                    && str_contains($html, e($rfa->displayReference()))
                    && str_contains($html, 'Beyond PCT');
            }
        );
    }

    public function test_a_nearing_case_is_emailed_before_the_deadline(): void
    {
        $this->caseFiledDaysAgo(1);

        $this->scan();

        Mail::assertSent(
            PctAlertDigest::class,
            fn (PctAlertDigest $mail) => $mail->items[0]['level']
                === PctEmailDelivery::LEVEL_NEARING
        );
    }

    public function test_a_case_within_pct_is_not_emailed(): void
    {
        $this->caseFiledDaysAgo(0);

        $result = $this->scan();

        $this->assertSame(0, $result['emails_sent']);

        Mail::assertNothingSent();
    }

    public function test_scanning_again_does_not_resend_the_same_alert(): void
    {
        $this->caseFiledDaysAgo(10);

        $this->scan();

        $result = $this->scan();

        $this->assertSame(0, $result['emails_sent']);

        Mail::assertSentCount(1);
    }

    public function test_an_escalation_to_a_new_level_is_emailed_again(): void
    {
        $rfa = $this->caseFiledDaysAgo(1);

        $this->scan();

        $rfa->forceFill([
            'date_filed' => now()->subDays(10)->toDateString(),
        ])->save();

        $this->scan();

        Mail::assertSentCount(2);

        $this->assertSame(
            [PctEmailDelivery::LEVEL_BREACHED, PctEmailDelivery::LEVEL_NEARING],
            PctEmailDelivery::query()
                ->where('rfa_id', $rfa->id)
                ->orderBy('level')
                ->pluck('level')
                ->all()
        );
    }

    public function test_several_cases_arrive_in_one_email_per_officer(): void
    {
        $this->caseFiledDaysAgo(10);
        $this->caseFiledDaysAgo(8);
        $this->caseFiledDaysAgo(1);

        $this->scan();

        Mail::assertSentCount(1);

        Mail::assertSent(
            PctAlertDigest::class,
            fn (PctAlertDigest $mail) => count($mail->items) === 3
                && $mail->items[0]['level'] === PctEmailDelivery::LEVEL_BREACHED
                && $mail->items[2]['level'] === PctEmailDelivery::LEVEL_NEARING
        );
    }

    public function test_the_digest_is_capped_and_the_rest_summarised(): void
    {
        config(['notifications.pct_email.max_items' => 2]);

        $this->caseFiledDaysAgo(10);
        $this->caseFiledDaysAgo(9);
        $this->caseFiledDaysAgo(8);

        $this->scan();

        Mail::assertSent(
            PctAlertDigest::class,
            fn (PctAlertDigest $mail) => count($mail->items) === 2
                && $mail->hiddenCount === 1
                && str_contains($mail->render(), '1 more case')
        );

        /*
        | The summarised case counts as announced — it is not drip-fed later.
        */

        $this->assertSame(3, PctEmailDelivery::query()->count());
    }

    public function test_unassigned_cases_are_not_emailed(): void
    {
        Rfa::factory()->create([
            'date_filed' => now()->subDays(10)->toDateString(),
        ]);

        $this->scan();

        Mail::assertNothingSent();
    }

    public function test_an_officer_without_a_valid_email_is_skipped(): void
    {
        $this->interviewer->forceFill(['email' => 'not-an-email'])->save();

        $this->caseFiledDaysAgo(10);

        $result = $this->scan();

        $this->assertSame(0, $result['emails_sent']);

        Mail::assertNothingSent();
    }

    public function test_email_alerts_can_be_switched_off(): void
    {
        config(['notifications.pct_email.enabled' => false]);

        $this->caseFiledDaysAgo(10);

        $this->scan();

        Mail::assertNothingSent();

        $this->assertSame(0, PctEmailDelivery::query()->count());
    }

    public function test_a_failed_send_is_retried_on_the_next_scan(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('SMTP unavailable'));

        $this->caseFiledDaysAgo(10);

        $result = $this->scan();

        $this->assertSame(0, $result['emails_sent']);
        $this->assertSame(1, $result['emails_failed']);

        $this->assertSame(0, PctEmailDelivery::query()->count());

        /*
        | In-app alerts are unaffected by the mail failure.
        */

        $this->assertGreaterThan(0, $result['alerts']);
    }

    public function test_the_scan_command_reports_emails_sent(): void
    {
        $this->caseFiledDaysAgo(10);

        $this->artisan('rfa:scan-pct')
            ->expectsOutputToContain('Emails sent')
            ->assertSuccessful();

        Mail::assertSentCount(1);
    }
}
