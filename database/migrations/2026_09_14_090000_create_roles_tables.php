<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        |
        | A role is a named bundle of permissions. System roles are protected
        | from deletion so the application can never be left without an
        | administrative role.
        |
        */

        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);

            $table->string('slug', 100)
                ->unique();

            $table->string('description', 255)
                ->nullable();

            $table->boolean('is_system')
                ->default(false);

            $table->timestamps();
        });


        /*
        |--------------------------------------------------------------------------
        | Role Permissions
        |--------------------------------------------------------------------------
        |
        | Permission keys are defined in App\Support\Permissions. Only granted
        | keys are stored, so removing a permission simply deletes the row.
        |
        */

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->string('permission', 100);

            $table->timestamps();

            $table->unique([
                'role_id',
                'permission',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');

        Schema::dropIfExists('roles');
    }
};
