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
        | Import Batches
        |--------------------------------------------------------------------------
        |
        | Metadata for a CSV upload: which file, which user, and what the run
        | produced. The records themselves are already tagged with the same
        | uuid in rfas.import_batch_uuid, so history stays joinable for
        | batches imported before this table existed.
        |
        */

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();

            $table->uuid('uuid')->unique();

            $table->string('file_name', 255)
                ->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unsignedInteger('rows_processed')->default(0);

            $table->unsignedInteger('rows_imported')->default(0);

            $table->unsignedInteger('rows_created')->default(0);

            $table->unsignedInteger('rows_updated')->default(0);

            $table->unsignedInteger('duplicates_skipped')->default(0);

            $table->timestamps();
        });


        /*
        |--------------------------------------------------------------------------
        | System Settings
        |--------------------------------------------------------------------------
        |
        | Only keys defined in App\Support\SystemSettings may be stored, so
        | configuration stays a controlled surface rather than a free-form
        | key-value store.
        |
        */

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();

            $table->text('value')->nullable();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });


        /*
        |--------------------------------------------------------------------------
        | Database Backups
        |--------------------------------------------------------------------------
        |
        | A register of dump files written to storage/app/backups. Restoring is
        | deliberately NOT automated — the register records what exists and the
        | screen documents the restore procedure.
        |
        */

        Schema::create('database_backups', function (Blueprint $table) {
            $table->id();

            $table->string('filename', 255)->unique();

            $table->string('database_name', 100)->nullable();

            $table->string('driver', 30)->nullable();

            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->unsignedInteger('table_count')->default(0);

            $table->unsignedInteger('row_count')->default(0);

            /*
            | completed | failed
            */

            $table->string('status', 20)->default('completed');

            $table->string('notes', 500)->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_backups');

        Schema::dropIfExists('system_settings');

        Schema::dropIfExists('import_batches');
    }
};
