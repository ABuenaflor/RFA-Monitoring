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
        | PCT Email Deliveries
        |--------------------------------------------------------------------------
        |
        | One row per PCT email alert already sent, so the daily scan emails
        | each officer about a case once per alert level (nearing, due
        | today, breached) instead of every time it runs.
        |
        | Kept separate from app_notifications because in-app alerts are
        | deleted when their condition clears, and "nearing" has no in-app
        | alert at all.
        |
        */

        Schema::create('pct_email_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('rfa_id')
                ->constrained('rfas')
                ->cascadeOnDelete();

            /*
            | stage_one | stage_two | disposition
            */

            $table->string('stage', 20);

            /*
            | nearing | due_today | breached
            */

            $table->string('level', 20);

            $table->timestamp('sent_at');

            $table->timestamps();

            $table->unique(
                ['user_id', 'rfa_id', 'stage', 'level'],
                'pct_email_deliveries_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pct_email_deliveries');
    }
};
