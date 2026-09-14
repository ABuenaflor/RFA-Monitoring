<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_the_login_screen_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in to continue');
    }

    public function test_an_active_user_can_sign_in(): void
    {
        $user = User::factory()
            ->administrator()
            ->create([
                'password' => Hash::make('correct-horse-9'),
            ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-9',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);

        $this->assertNotNull(
            $user->fresh()->last_login_at
        );
    }

    public function test_sign_in_fails_with_the_wrong_password(): void
    {
        $user = User::factory()
            ->administrator()
            ->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $user = User::factory()
            ->administrator()
            ->inactive()
            ->create([
                'password' => Hash::make('correct-horse-9'),
            ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-9',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_account_without_a_role_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'role_id' => null,
            'password' => Hash::make('correct-horse-9'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse-9',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivation_ends_an_existing_session(): void
    {
        $user = User::factory()
            ->administrator()
            ->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();

        $user->forceFill([
            'status' => User::STATUS_INACTIVE,
        ])->save();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_a_forced_password_change_blocks_the_rest_of_the_system(): void
    {
        $user = User::factory()
            ->administrator()
            ->mustChangePassword()
            ->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('account.edit'));

        $this->actingAs($user)
            ->get(route('account.edit'))
            ->assertOk();
    }

    public function test_changing_the_password_clears_the_forced_change_flag(): void
    {
        $user = User::factory()
            ->administrator()
            ->mustChangePassword()
            ->create([
                'password' => Hash::make('temporary-pass-1'),
            ]);

        $this->actingAs($user)
            ->put(route('account.password'), [
                'current_password' => 'temporary-pass-1',
                'password' => 'brand-new-pass-2',
                'password_confirmation' => 'brand-new-pass-2',
            ])
            ->assertRedirect(route('account.edit'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);

        $this->assertTrue(
            Hash::check('brand-new-pass-2', $user->password)
        );
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()
            ->administrator()
            ->create([
                'password' => Hash::make('temporary-pass-1'),
            ]);

        $this->actingAs($user)
            ->put(route('account.password'), [
                'current_password' => 'wrong-pass-1',
                'password' => 'brand-new-pass-2',
                'password_confirmation' => 'brand-new-pass-2',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(
            Hash::check(
                'temporary-pass-1',
                $user->fresh()->password
            )
        );
    }

    public function test_a_user_can_sign_out(): void
    {
        $user = User::factory()
            ->administrator()
            ->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_the_administrator_role_is_seeded_as_a_system_role(): void
    {
        $role = Role::query()
            ->where('slug', Role::ADMINISTRATOR)
            ->first();

        $this->assertNotNull($role);

        $this->assertTrue($role->is_system);
    }
}
