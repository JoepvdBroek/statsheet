<?php

use App\Console\Commands\ScheduleWeeklyReviews;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Each owner's Weekly Review of the Week just ended, queued in the 06:00 hour of their Monday.
 * This needs the scheduler (`php artisan schedule:run` every minute) and a queue worker (`php artisan queue:work`) running.
 */
Schedule::command(ScheduleWeeklyReviews::class)->hourly();
