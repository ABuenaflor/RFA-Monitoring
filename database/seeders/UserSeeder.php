<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates (or resets) the default administrator login from config/admin.php.
 *
 * Safe to re-run: it updates the same account by email instead of
 * duplicating it.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (empty($password)) {
            $this->command?->error(
                'ADMIN_PASSWORD is not set in .env — skipping admin user seed.'
            );

            return;
        }

        $role = Role::query()
            ->where('slug', Role::ADMINISTRATOR)
            ->first();

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrator',
                'password' => Hash::make($password),
                'role_id' => $role?->id,
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
            ]
        );

        $this->command?->info("Admin user ready: {$email}");
    }
}
