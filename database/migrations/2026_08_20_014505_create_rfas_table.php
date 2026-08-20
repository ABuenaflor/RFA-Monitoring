<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfas', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | RFA Identification
            |--------------------------------------------------------------------------
            */

            $table->string('reference_no', 100)->unique();

            /*
            |--------------------------------------------------------------------------
            | Party Information
            |--------------------------------------------------------------------------
            */

            $table->string('requesting_party')->nullable();

            $table->string('responding_party')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Detailed Workflow Status
            |--------------------------------------------------------------------------
            */

            $table->string('status', 50)
                ->default('newly_filed')
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Dashboard Monitoring Bucket
            |--------------------------------------------------------------------------
            |
            | pending
            | ongoing
            | disposed
            |
            */

            $table->string('monitoring_bucket', 20)
                ->default('pending')
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Filing
            |--------------------------------------------------------------------------
            */

            $table->string('mode_of_filing', 20)
                ->nullable();

            $table->date('date_filed')
                ->nullable()
                ->index();

            /*
            |--------------------------------------------------------------------------
            | Interviewer Processing
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('interviewer_id')
                ->nullable();

            $table->date('date_assigned_interviewer')
                ->nullable();

            $table->date('date_interview')
                ->nullable();

            $table->date('date_validated')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Labor Relations
            |--------------------------------------------------------------------------
            */

            $table->date('date_turned_over_lr')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | SEADO Processing
            |--------------------------------------------------------------------------
            */

            $table->unsignedBigInteger('seado_id')
                ->nullable();

            $table->date('date_assigned_seado')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Disposition
            |--------------------------------------------------------------------------
            */

            $table->string('disposition_status', 50)
                ->nullable();

            $table->date('date_disposed')
                ->nullable();

            /*
            |--------------------------------------------------------------------------
            | Import Tracking
            |--------------------------------------------------------------------------
            */

            $table->uuid('import_batch_uuid')
                ->nullable()
                ->index();

            $table->json('import_payload')
                ->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Composite Indexes
            |--------------------------------------------------------------------------
            */

            $table->index([
                'monitoring_bucket',
                'date_filed',
            ]);

            $table->index([
                'status',
                'date_filed',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfas');
    }
};