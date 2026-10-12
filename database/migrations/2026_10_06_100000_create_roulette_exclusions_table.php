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
        | SEADO Roulette Exclusions
        |--------------------------------------------------------------------------
        |
        | SEADOs the SEADO Assignment roulette must skip (on leave, on detail,
        | at capacity). Keyed by the upper-cased name, because imported cases
        | identify a SEADO by name only.
        |
        */

        Schema::create('roulette_exclusions', function (Blueprint $table) {
            $table->id();

            $table->string('seado_key', 150)
                ->unique();

            $table->string('seado_name', 150);

            $table->string('reason', 255)
                ->nullable();

            $table->foreignId('excluded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roulette_exclusions');
    }
};
