<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfas', function (Blueprint $table) {
            $table->string('office', 50)
                ->nullable()
                ->index();

            $table->string('docket_no', 100)
                ->nullable()
                ->index();

            $table->text('company_address')
                ->nullable();

            $table->string('contact_no', 100)
                ->nullable();

            $table->unsignedInteger('total_employment')
                ->nullable();

            $table->string('industry', 255)
                ->nullable();

            $table->string('industry_code', 50)
                ->nullable();

            $table->string('size_of_enterprise', 50)
                ->nullable();

            $table->date('date_ta_nores')
                ->nullable();

            $table->date('date_initial_conference')
                ->nullable();

            $table->unsignedInteger('workers_involved')
                ->nullable();

            $table->unsignedInteger('male_workers')
                ->nullable();

            $table->unsignedInteger('female_workers')
                ->nullable();

            $table->string('filer_class', 100)
                ->nullable();

            $table->text('issues')
                ->nullable();

            $table->string('source_case_status', 50)
                ->nullable()
                ->index();

            $table->string('disposition_mode', 50)
                ->nullable();

            $table->date('date_both_parties_appeared')
                ->nullable();

            $table->string('interviewer_name', 150)
                ->nullable();

            $table->string('seado_name', 150)
                ->nullable();

            $table->decimal(
                'monetary_benefit',
                15,
                2
            )->nullable();

            $table->unsignedInteger('workers_benefited')
                ->nullable();

            $table->char('source_row_key', 64)
                ->nullable()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('rfas', function (Blueprint $table) {
            $table->dropUnique([
                'source_row_key',
            ]);

            $table->dropIndex([
                'source_case_status',
            ]);

            $table->dropIndex([
                'docket_no',
            ]);

            $table->dropIndex([
                'office',
            ]);

            $table->dropColumn([
                'office',
                'docket_no',
                'company_address',
                'contact_no',
                'total_employment',
                'industry',
                'industry_code',
                'size_of_enterprise',
                'date_ta_nores',
                'date_initial_conference',
                'workers_involved',
                'male_workers',
                'female_workers',
                'filer_class',
                'issues',
                'source_case_status',
                'disposition_mode',
                'date_both_parties_appeared',
                'interviewer_name',
                'seado_name',
                'monetary_benefit',
                'workers_benefited',
                'source_row_key',
            ]);
        });
    }
};
