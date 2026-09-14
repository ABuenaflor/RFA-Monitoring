<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_an_administrator_reaches_every_protected_screen(): void
    {
        $admin = User::factory()
            ->administrator()
            ->create();

        foreach ([
            '/dashboard',
            '/listing',
            '/reports',
            '/reports/export',
            '/reports/print',
            '/pct-process',
            '/imports',
            '/users',
            '/roles',
        ] as $path) {
            $this->actingAs($admin)
                ->get($path)
                ->assertOk();
        }
    }

    public function test_a_viewer_is_limited_to_read_only_screens(): void
    {
        $viewer = User::factory()
            ->withRole('viewer')
            ->create();

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertOk();

        $this->actingAs($viewer)
            ->get('/reports')
            ->assertOk();

        foreach ([
            '/reports/export',
            '/reports/print',
            '/imports',
            '/users',
            '/roles',
        ] as $path) {
            $this->actingAs($viewer)
                ->get($path)
                ->assertForbidden();
        }
    }

    public function test_an_interviewer_cannot_reach_access_control(): void
    {
        $interviewer = User::factory()
            ->withRole('interviewer')
            ->create();

        $this->actingAs($interviewer)
            ->get('/users')
            ->assertForbidden();

        $this->actingAs($interviewer)
            ->get('/roles')
            ->assertForbidden();
    }

    public function test_granting_a_permission_immediately_opens_the_route(): void
    {
        $role = Role::create([
            'name' => 'Records Officer',
            'slug' => 'records-officer',
            'is_system' => false,
        ]);

        $user = User::factory()
            ->withRole($role)
            ->create();

        $this->actingAs($user)
            ->get('/imports')
            ->assertForbidden();

        $role->syncPermissions([
            Permissions::IMPORT_MANAGE,
        ]);

        $this->actingAs($user->fresh())
            ->get('/imports')
            ->assertOk();
    }

    public function test_unknown_permission_keys_are_never_stored(): void
    {
        $role = Role::create([
            'name' => 'Experimental',
            'slug' => 'experimental',
            'is_system' => false,
        ]);

        $role->syncPermissions([
            Permissions::REPORTS_VIEW,
            'totally.made.up',
        ]);

        $this->assertSame(
            [Permissions::REPORTS_VIEW],
            $role->fresh()->permissionKeys()
        );
    }

    public function test_a_deactivated_user_holds_no_permissions(): void
    {
        $user = User::factory()
            ->administrator()
            ->inactive()
            ->create();

        $this->assertFalse(
            $user->hasPermission(Permissions::DASHBOARD_VIEW)
        );
    }

    public function test_a_user_without_a_role_holds_no_permissions(): void
    {
        $user = User::factory()->create([
            'role_id' => null,
        ]);

        $this->assertFalse(
            $user->hasPermission(Permissions::DASHBOARD_VIEW)
        );
    }
}
