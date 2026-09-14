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
        | Operational Notifications
        |--------------------------------------------------------------------------
        |
        | Named app_notifications so it never collides with Laravel's own
        | notifications table. These are in-system work items: PCT deadlines,
        | assignments, and workflow events.
        |
        */

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('rfa_id')
                ->nullable()
                ->constrained('rfas')
                ->cascadeOnDelete();

            /*
            | pct_stage_one | pct_stage_two | pct_disposition
            | assignment | workflow | system
            */

            $table->string('category', 40)
                ->index();

            /*
            | info | warning | critical
            */

            $table->string('severity', 20)
                ->index();

            $table->string('title', 255);

            $table->text('message')
                ->nullable();

            $table->string('action_url', 500)
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Deduplication
            |--------------------------------------------------------------------------
            |
            | A stable key for the condition being reported, so re-running the
            | PCT scan refreshes rather than duplicates a user's queue.
            |
            */

            $table->string('dedupe_key', 191);

            $table->timestamp('read_at')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'user_id',
                'dedupe_key',
            ]);

            $table->index([
                'user_id',
                'read_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
