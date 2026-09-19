<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
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

    public function test_an_administrator_can_create_a_role(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.roles.store'), [
                'name' => 'Records Officer',
                'description' => 'Handles CSV intake.',
                'permissions' => [
                    Permissions::DASHBOARD_VIEW,
                    Permissions::IMPORT_MANAGE,
                ],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::query()
            ->where('slug', 'records-officer')
            ->first();

        $this->assertNotNull($role);

        $this->assertEqualsCanonicalizing(
            [
                Permissions::DASHBOARD_VIEW,
                Permissions::IMPORT_MANAGE,
            ],
            $role->permissionKeys()
        );
    }

    public function test_updating_a_role_replaces_its_permission_set(): void
    {
        $role = Role::query()
            ->where('slug', 'viewer')
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $role), [
                'name' => 'Viewer',
                'permissions' => [
                    Permissions::DASHBOARD_VIEW,
                ],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertSame(
            [Permissions::DASHBOARD_VIEW],
            $role->fresh()->permissionKeys()
        );
    }

    public function test_the_administrator_role_cannot_be_narrowed(): void
    {
        $role = Role::query()
            ->where('slug', Role::ADMINISTRATOR)
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->put(route('admin.roles.update', $role), [
                'name' => 'Administrator',
                'permissions' => [
                    Permissions::DASHBOARD_VIEW,
                ],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertTrue(
            $this->admin->fresh()->hasPermission(
                Permissions::ROLES_MANAGE
            )
        );

        $this->assertSame(
            [],
            $role->fresh()->permissionKeys()
        );
    }

    public function test_a_system_role_cannot_be_deleted(): void
    {
        $role = Role::query()
            ->where('slug', 'viewer')
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_a_role_still_holding_users_cannot_be_deleted(): void
    {
        $role = Role::create([
            'name' => 'Temporary',
            'slug' => 'temporary',
            'is_system' => false,
        ]);

        User::factory()
            ->withRole($role)
            ->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_an_unused_custom_role_can_be_deleted(): void
    {
        $role = Role::create([
            'name' => 'Temporary',
            'slug' => 'temporary',
            'is_system' => false,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', [
            'id' => $role->id,
        ]);
    }

    public function test_duplicate_role_names_are_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.roles.store'), [
                'name' => 'Viewer',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_a_non_administrator_cannot_manage_roles(): void
    {
        $seado = User::factory()
            ->withRole('seado')
            ->create();

        $this->actingAs($seado)
            ->get(route('admin.roles.index'))
            ->assertForbidden();
    }
}
