<?php

namespace Tests\Feature\Admin;

use App\Models\Holiday;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * SENA Focal: everything a SEADO can do, plus CSV import, user accounts
 * (no deleting) and holidays & events — without a way to reach
 * administrator access.
 */
class SenaFocalRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $focal;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->focal = User::factory()->withRole(Role::SENA_FOCAL)->create();

        $this->admin = User::factory()->administrator()->create();
    }

    public function test_the_role_has_seado_access_plus_import_users_and_holidays(): void
    {
        $seado = Role::query()->where('slug', 'seado')->firstOrFail();

        foreach ($seado->permissionKeys() as $permission) {
            $this->assertTrue($this->focal->hasPermission($permission), $permission);
        }

        foreach ([
            Permissions::IMPORT_MANAGE,
            Permissions::USERS_VIEW,
            Permissions::USERS_MANAGE,
            Permissions::HOLIDAYS_MANAGE,
        ] as $permission) {
            $this->assertTrue($this->focal->hasPermission($permission), $permission);
        }

        foreach ([
            Permissions::USERS_DELETE,
            Permissions::ROLES_MANAGE,
            Permissions::SETTINGS_MANAGE,
            Permissions::AUDIT_VIEW,
            Permissions::BACKUP_VIEW,
        ] as $permission) {
            $this->assertFalse($this->focal->hasPermission($permission), $permission);
        }
    }

    public function test_import_stays_reserved_for_the_administrator_and_sena_focal(): void
    {
        $custom = Role::create(['name' => 'Records Officer', 'slug' => 'records-officer']);

        $custom->syncPermissions([Permissions::IMPORT_MANAGE, Permissions::RFA_VIEW]);

        $this->assertSame([Permissions::RFA_VIEW], $custom->fresh()->permissionKeys());

        $this->actingAs($this->focal)
            ->get(route('imports.index'))
            ->assertOk();

        $this->actingAs($this->focal)
            ->post(route('imports.store'), [
                'csv_file' => UploadedFile::fake()->createWithContent(
                    'rfas.csv',
                    "Office,Docket No.,Complainant,Company,Date Filed,Case Status\n"
                        . "RO-V,SEAD-RO-V-07-00009-2026-OL,Ana Cruz,Shop A,2026-07-01,Pending\n"
                ),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rfas', ['docket_no' => 'SEAD-RO-V-07-00009-2026-OL']);
    }

    public function test_sena_focal_can_create_edit_and_disable_users_but_not_delete(): void
    {
        $seadoRole = Role::query()->where('slug', 'seado')->firstOrFail();

        $this->actingAs($this->focal)
            ->post(route('admin.access.store'), [
                'name' => 'New Seado',
                'email' => 'new.seado@example.com',
                'role_id' => $seadoRole->id,
                'status' => 'active',
                'password' => 'Password1234',
                'password_confirmation' => 'Password1234',
            ])
            ->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'new.seado@example.com')->firstOrFail();

        $this->actingAs($this->focal)
            ->put(route('admin.access.update', $user), [
                'name' => 'Renamed Seado',
                'email' => 'new.seado@example.com',
                'role_id' => $seadoRole->id,
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed Seado', $user->fresh()->name);

        $this->actingAs($this->focal)
            ->patch(route('admin.access.toggle-status', $user))
            ->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->isActive());

        $this->actingAs($this->focal)
            ->delete(route('admin.access.destroy', $user))
            ->assertForbidden();

        $this->assertNotNull($user->fresh());

        $this->actingAs($this->focal)
            ->get(route('admin.access.index'))
            ->assertOk()
            ->assertDontSee('Delete this user account?');
    }

    public function test_sena_focal_cannot_reach_administrator_access(): void
    {
        $adminRole = Role::query()->where('slug', Role::ADMINISTRATOR)->firstOrFail();

        // Cannot create an administrator.
        $this->actingAs($this->focal)
            ->post(route('admin.access.store'), [
                'name' => 'Sneaky Admin',
                'email' => 'sneaky@example.com',
                'role_id' => $adminRole->id,
                'status' => 'active',
                'password' => 'Password1234',
                'password_confirmation' => 'Password1234',
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);

        // The Administrator role is not offered.
        $this->actingAs($this->focal)
            ->get(route('admin.access.create'))
            ->assertOk()
            ->assertViewHas(
                'roles',
                fn ($roles) => ! $roles->contains('id', $adminRole->id)
                    && $roles->contains('slug', Role::SENA_FOCAL)
            );

        // Cannot change their own role.
        $seadoRole = Role::query()->where('slug', 'seado')->firstOrFail();

        $this->actingAs($this->focal)
            ->put(route('admin.access.update', $this->focal), [
                'name' => $this->focal->name,
                'email' => $this->focal->email,
                'role_id' => $seadoRole->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertTrue($this->focal->fresh()->role->slug === Role::SENA_FOCAL);

        // Cannot open, edit or disable an administrator account.
        $this->actingAs($this->focal)
            ->get(route('admin.access.edit', $this->admin))
            ->assertForbidden();

        $this->actingAs($this->focal)
            ->put(route('admin.access.update', $this->admin), [
                'name' => 'Taken Over',
                'email' => $this->admin->email,
                'role_id' => $seadoRole->id,
                'status' => 'active',
                'password' => 'Password1234',
                'password_confirmation' => 'Password1234',
            ])
            ->assertForbidden();

        $this->actingAs($this->focal)
            ->patch(route('admin.access.toggle-status', $this->admin))
            ->assertForbidden();

        $this->assertTrue($this->admin->fresh()->isAdministrator());
        $this->assertTrue($this->admin->fresh()->isActive());
    }

    public function test_sena_focal_can_manage_holidays(): void
    {
        $this->actingAs($this->focal)
            ->get(route('holidays.index'))
            ->assertOk();

        $this->actingAs($this->focal)
            ->post(route('holidays.store'), [
                'name' => 'Office Event',
                'date' => '2026-11-03',
                'type' => Holiday::TYPE_EVENT,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('holidays', ['name' => 'Office Event']);
    }
}
