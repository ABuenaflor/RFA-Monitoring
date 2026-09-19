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
        | System Audit Trail
        |--------------------------------------------------------------------------
        |
        | Answers "who changed what, when, and from where" across the whole
        | system. Deliberately append-only: the application never updates or
        | deletes a row here.
        |
        */

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
            | Kept alongside the foreign key so the trail still reads correctly
            | after an account is deleted.
            */

            $table->string('user_name', 150)
                ->nullable();

            /*
            | created | updated | deleted | login | logout | login_failed
            | import | permission_denied
            */

            $table->string('event', 40)
                ->index();

            $table->string('auditable_type', 150)
                ->nullable();

            $table->unsignedBigInteger('auditable_id')
                ->nullable();

            $table->string('record_label', 255)
                ->nullable();

            $table->string('change_summary', 500)
                ->nullable();

            $table->json('changes')
                ->nullable();

            $table->string('ip_address', 45)
                ->nullable();

            $table->string('user_agent', 500)
                ->nullable();

            $table->timestamps();

            $table->index([
                'auditable_type',
                'auditable_id',
            ]);

            $table->index([
                'user_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
