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
        | Case Activity Timeline
        |--------------------------------------------------------------------------
        |
        | A human-readable, case-scoped narrative of what happened to one RFA.
        | This is distinct from the system-wide audit trail: the timeline is
        | written for case officers, the audit trail for administrators.
        |
        */

        Schema::create('rfa_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rfa_id')
                ->constrained('rfas')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            | created | details | assignment | workflow | conference
            | disposition | reopened | note
            */

            $table->string('type', 40)
                ->index();

            $table->string('title', 255);

            $table->text('description')
                ->nullable();

            /*
            | Field-level before/after values for the change being recorded.
            */

            $table->json('changes')
                ->nullable();

            $table->timestamps();

            $table->index([
                'rfa_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfa_activities');
    }
};
