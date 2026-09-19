<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_an_administrator_can_create_a_user(): void
    {
        $role = Role::query()
            ->where('slug', 'seado')
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.access.store'), [
                'name' => 'Maria Santos',
                'email' => 'maria.santos@example.test',
                'role_id' => $role->id,
                'office' => 'PFO Albay',
                'position' => 'SEADO',
                'status' => 'active',
                'password' => 'initial-pass-1',
                'password_confirmation' => 'initial-pass-1',
                'must_change_password' => '1',
            ])
            ->assertRedirect(route('admin.access.index'));

        $created = User::query()
            ->where('email', 'maria.santos@example.test')
            ->first();

        $this->assertNotNull($created);

        $this->assertSame($role->id, $created->role_id);

        $this->assertTrue($created->must_change_password);

        $this->assertTrue(
            Hash::check('initial-pass-1', $created->password)
        );
    }

    public function test_a_weak_password_is_rejected(): void
    {
        $role = Role::query()
            ->where('slug', 'viewer')
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.access.store'), [
                'name' => 'Weak Password',
                'email' => 'weak@example.test',
                'role_id' => $role->id,
                'status' => 'active',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', [
            'email' => 'weak@example.test',
        ]);
    }

    public function test_the_last_active_administrator_cannot_be_demoted(): void
    {
        $viewer = Role::query()
            ->where('slug', 'viewer')
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.access.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'role_id' => $viewer->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertTrue(
            $this->admin->fresh()->isAdministrator()
        );
    }

    public function test_the_last_active_administrator_cannot_be_deactivated(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.access.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'role_id' => $this->admin->role_id,
                'status' => 'inactive',
            ])
            ->assertSessionHasErrors();

        $this->assertTrue(
            $this->admin->fresh()->isActive()
        );
    }

    public function test_an_administrator_can_be_demoted_once_another_exists(): void
    {
        User::factory()
            ->administrator()
            ->create();

        $viewer = Role::query()
            ->where('slug', 'viewer')
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.access.update', $this->admin), [
                'name' => $this->admin->name,
                'email' => $this->admin->email,
                'role_id' => $viewer->id,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.access.index'));

        $this->assertFalse(
            $this->admin->fresh()->isAdministrator()
        );
    }

    public function test_an_administrator_cannot_delete_their_own_account(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('admin.access.destroy', $this->admin))
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
        ]);
    }

    public function test_an_administrator_can_delete_another_account(): void
    {
        $other = User::factory()
            ->withRole('viewer')
            ->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.access.destroy', $other))
            ->assertRedirect(route('admin.access.index'));

        $this->assertDatabaseMissing('users', [
            'id' => $other->id,
        ]);
    }

    public function test_an_administrative_password_reset_forces_a_change(): void
    {
        $other = User::factory()
            ->withRole('viewer')
            ->create();

        $this->actingAs($this->admin)
            ->put(route('admin.access.update', $other), [
                'name' => $other->name,
                'email' => $other->email,
                'role_id' => $other->role_id,
                'status' => 'active',
                'password' => 'reset-pass-12',
                'password_confirmation' => 'reset-pass-12',
            ])
            ->assertRedirect(route('admin.access.index'));

        $other->refresh();

        $this->assertTrue($other->must_change_password);

        $this->assertTrue(
            Hash::check('reset-pass-12', $other->password)
        );
    }

    public function test_deactivating_a_user_records_the_timestamp(): void
    {
        $other = User::factory()
            ->withRole('viewer')
            ->create();

        $this->actingAs($this->admin)
            ->put(route('admin.access.update', $other), [
                'name' => $other->name,
                'email' => $other->email,
                'role_id' => $other->role_id,
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.access.index'));

        $other->refresh();

        $this->assertFalse($other->isActive());

        $this->assertNotNull($other->deactivated_at);
    }

    public function test_a_non_administrator_cannot_reach_user_management(): void
    {
        $viewer = User::factory()
            ->withRole('viewer')
            ->create();

        $this->actingAs($viewer)
            ->get(route('admin.access.index'))
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('admin.access.store'), [])
            ->assertForbidden();
    }
}
