<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * Creates the four system roles.
 *
 * Safe to re-run: existing roles keep whatever permission set an
 * administrator has since configured for them.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->definitions() as $definition) {
            $role = Role::query()
                ->where('slug', $definition['slug'])
                ->first();

            if ($role !== null) {
                $role->fill([
                    'is_system' => true,
                ])->save();

                continue;
            }

            $role = Role::create([
                'name' => $definition['name'],

                'slug' => $definition['slug'],

                'description' => $definition['description'],

                'is_system' => true,
            ]);

            $role->syncPermissions($definition['permissions']);
        }
    }

    /**
     * @return array<int, array{name: string, slug: string, description: string, permissions: array<int, string>}>
     */
    private function definitions(): array
    {
        return [
            [
                'name' => 'Administrator',

                'slug' => Role::ADMINISTRATOR,

                'description' =>
                    'Full system access including access control, governance, and release readiness.',

                /*
                |--------------------------------------------------------------------------
                | Implicit
                |--------------------------------------------------------------------------
                |
                | The administrator role grants every permission by definition,
                | so nothing needs to be stored against it.
                |
                */

                'permissions' => [],
            ],

            [
                'name' => 'SEADO',

                'slug' => 'seado',

                'description' =>
                    'Conference handling, disposition capture, and operational reporting.',

                'permissions' => [
                    Permissions::DASHBOARD_VIEW,
                    Permissions::RFA_VIEW,
                    Permissions::PCT_VIEW,
                    Permissions::RFA_MANAGE,
                    Permissions::RFA_ASSIGN,
                    Permissions::RFA_DISPOSE,
                    Permissions::REPORTS_VIEW,
                    Permissions::REPORTS_EXPORT,
                    Permissions::NOTIFICATIONS_VIEW,
                ],
            ],

            [
                'name' => 'Interviewer',

                'slug' => 'interviewer',

                'description' =>
                    'Interview stage processing and case record updates.',

                'permissions' => [
                    Permissions::DASHBOARD_VIEW,
                    Permissions::RFA_VIEW,
                    Permissions::PCT_VIEW,
                    Permissions::RFA_MANAGE,
                    Permissions::REPORTS_VIEW,
                    Permissions::NOTIFICATIONS_VIEW,
                ],
            ],

            [
                'name' => 'Viewer',

                'slug' => 'viewer',

                'description' =>
                    'Read-only monitoring and reporting access.',

                'permissions' => [
                    Permissions::DASHBOARD_VIEW,
                    Permissions::RFA_VIEW,
                    Permissions::PCT_VIEW,
                    Permissions::REPORTS_VIEW,
                ],
            ],
        ];
    }
}
