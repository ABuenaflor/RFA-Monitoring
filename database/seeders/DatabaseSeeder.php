<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Default seeding is non-destructive and safe to run against a live database.
 *
 * RfaSeeder is deliberately NOT called here: it replaces all RFA records with
 * demonstration data. Run it explicitly when that is actually wanted:
 *
 *     php artisan db:seed --class=RfaSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
        ]);
    }
}
