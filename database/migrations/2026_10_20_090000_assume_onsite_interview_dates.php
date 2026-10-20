<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

/*
| One-time backfill: on-site cases take their Date Filed as the Date
| Assigned to Interviewer, and as the Date Interviewed when the office CSV
| left it out (rfa:apply-onsite-dates). Every later save and import applies
| the same rule.
*/

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('rfa:apply-onsite-dates');
    }

    public function down(): void
    {
        // The dates are ordinary case data now and are kept.
    }
};
