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
        | Holidays & Events
        |--------------------------------------------------------------------------
        |
        | Dates the PCT clock skips when it counts working days: regular and
        | special holidays, office closures, and any event an administrator
        | declares a non-working day.
        |
        */

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();

            $table->date('date')
                ->unique();

            $table->string('name', 150);

            /*
            | holiday | event
            */

            $table->string('type', 20)
                ->default('holiday')
                ->index();

            $table->text('notes')
                ->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
