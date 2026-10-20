<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/*
| Adds the SENA Focal role to an existing installation. The seeder only
| creates roles that are missing; roles already there keep the permissions
| an administrator gave them.
*/

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('db:seed', [
            '--class' => RolePermissionSeeder::class,
            '--force' => true,
        ]);
    }

    public function down(): void
    {
        // Left in place: users may already hold the role.
    }
};
