<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | Role Assignment
            |--------------------------------------------------------------------------
            */

            $table->foreignId('role_id')
                ->nullable()
                ->after('email')
                ->constrained('roles')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Organisational Assignment
            |--------------------------------------------------------------------------
            |
            | office mirrors rfas.office so operational scoping stays possible.
            |
            */

            $table->string('office', 50)
                ->nullable()
                ->after('role_id')
                ->index();

            $table->string('position', 100)
                ->nullable()
                ->after('office');

            /*
            |--------------------------------------------------------------------------
            | Account Status
            |--------------------------------------------------------------------------
            |
            | active | inactive
            |
            */

            $table->string('status', 20)
                ->default('active')
                ->after('position')
                ->index();

            $table->boolean('must_change_password')
                ->default(false)
                ->after('status');

            $table->timestamp('deactivated_at')
                ->nullable()
                ->after('must_change_password');

            /*
            |--------------------------------------------------------------------------
            | Sign-in Tracking
            |--------------------------------------------------------------------------
            */

            $table->timestamp('last_login_at')
                ->nullable()
                ->after('deactivated_at');

            $table->string('last_login_ip', 45)
                ->nullable()
                ->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);

            $table->dropIndex(['office']);

            $table->dropIndex(['status']);

            $table->dropColumn([
                'role_id',
                'office',
                'position',
                'status',
                'must_change_password',
                'deactivated_at',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
