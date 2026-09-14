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
        | UAT Results
        |--------------------------------------------------------------------------
        |
        | The test plan itself lives in App\Support\UatPlan. Only the outcome of
        | each case is stored, so adding or rewording a case never orphans a
        | result and never needs a data migration.
        |
        */

        Schema::create('uat_results', function (Blueprint $table) {
            $table->id();

            $table->string('case_key', 100)->unique();

            /*
            | pending | passed | failed | blocked | not_applicable
            */

            $table->string('status', 20)
                ->default('pending')
                ->index();

            $table->text('notes')->nullable();

            $table->foreignId('tested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('tested_at')->nullable();

            $table->timestamps();
        });


        /*
        |--------------------------------------------------------------------------
        | Release Sign-off
        |--------------------------------------------------------------------------
        |
        | A dated record of who accepted a release, for which environment, and
        | what the readiness picture looked like at that moment.
        |
        */

        Schema::create('release_signoffs', function (Blueprint $table) {
            $table->id();

            $table->string('version', 50);

            $table->string('environment', 50);

            $table->string('summary', 500)->nullable();

            $table->unsignedInteger('cases_passed')->default(0);

            $table->unsignedInteger('cases_total')->default(0);

            $table->unsignedInteger('checks_failing')->default(0);

            $table->foreignId('signed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('signed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_signoffs');

        Schema::dropIfExists('uat_results');
    }
};
