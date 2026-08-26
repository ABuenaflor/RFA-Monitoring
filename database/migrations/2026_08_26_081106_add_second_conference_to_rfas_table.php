<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfas', function (Blueprint $table) {
            $table
                ->date('date_second_conference')
                ->nullable()
                ->after('date_initial_conference');

            $table
                ->index(
                    'date_second_conference',
                    'rfas_second_conference_idx'
                );
        });
    }

    public function down(): void
    {
        Schema::table('rfas', function (Blueprint $table) {
            $table->dropIndex(
                'rfas_second_conference_idx'
            );

            $table->dropColumn(
                'date_second_conference'
            );
        });
    }
};