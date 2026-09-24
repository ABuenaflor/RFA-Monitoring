<?php

namespace Tests\Feature;

use App\Models\Rfa;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CSV import is reserved for the System Administrator; everyone else
 * works with the imported records.
 */
class ImportAccessTest extends TestCase
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

    public function test_the_administrator_can_open_the_import_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('imports.index'))
            ->assertOk();
    }

    public function test_other_roles_cannot_open_or_submit_an_import(): void
    {
        foreach (['seado', 'interviewer', 'viewer'] as $slug) {
            $user = User::factory()
                ->withRole($slug)
                ->create();

            $this->actingAs($user)
                ->get(route('imports.index'))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('imports.store'), [
                    'csv_file' => UploadedFile::fake()->create(
                        'rfas.csv',
                        1,
                        'text/csv'
                    ),
                ])
                ->assertForbidden();
        }
    }

    public function test_a_role_with_a_leftover_import_permission_is_still_refused(): void
    {
        $role = Role::create([
            'name' => 'Records Officer',
            'slug' => 'records-officer',
            'is_system' => false,
        ]);

        /*
        | Written directly, as if saved before the restriction existed.
        */

        $role->permissions()->create([
            'permission' => Permissions::IMPORT_MANAGE,
        ]);

        $user = User::factory()
            ->withRole($role)
            ->create();

        $this->assertFalse(
            $user->fresh()->hasPermission(Permissions::IMPORT_MANAGE)
        );

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertForbidden();
    }

    public function test_import_buttons_show_only_for_the_administrator(): void
    {
        $viewer = User::factory()
            ->withRole('viewer')
            ->create();

        foreach (['dashboard', 'listing'] as $page) {
            $this->actingAs($this->admin)
                ->get(route($page))
                ->assertOk()
                ->assertSee(route('imports.index'), false);

            $this->actingAs($viewer)
                ->get(route($page))
                ->assertOk()
                ->assertDontSee(route('imports.index'), false);
        }
    }

    public function test_imported_records_are_visible_to_other_users(): void
    {
        $rfa = Rfa::factory()->create([
            'import_batch_uuid' => (string) Str::uuid(),
        ]);

        foreach (['seado', 'interviewer', 'viewer'] as $slug) {
            $user = User::factory()
                ->withRole($slug)
                ->create();

            $this->actingAs($user)
                ->get(route('listing'))
                ->assertOk()
                ->assertSee($rfa->reference_no);
        }
    }
}
