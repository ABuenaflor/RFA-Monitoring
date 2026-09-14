<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/*
|--------------------------------------------------------------------------
| Scheduled Work
|--------------------------------------------------------------------------
|
| Requires the scheduler to be running:
|
|     php artisan schedule:work
|
| or a Windows Task Scheduler entry calling `php artisan schedule:run`
| every minute.
|
*/

Schedule::command('rfa:scan-pct')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();
