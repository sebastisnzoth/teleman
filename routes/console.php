<?php

use App\Services\CronMonitorService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your console commands and
| scheduled tasks.
|
*/

// Process scheduled campaigns every minute
Schedule::command('campaigns:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Record a scheduler heartbeat so the monitor reflects activity regardless of process manager.
Schedule::call(function (): void {
    app(CronMonitorService::class)->recordScheduleHeartbeat();
})
    ->name('cron-monitor:heartbeat')
    ->everyMinute()
    ->withoutOverlapping();

// Fix stale campaigns every 5 minutes
Schedule::command('campaigns:fix-stale')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Process sequence steps every minute
Schedule::job(new \App\Jobs\ProcessSequenceStepsJob())
    ->everyMinute()
    ->withoutOverlapping();

// Charge monthly for phone numbers (1st of every month at midnight)
Schedule::command('phone-numbers:charge-monthly')
    ->monthlyOn(1, '00:00')
    ->withoutOverlapping()
    ->runInBackground();

// Clean up old logs (optional)
Schedule::command('queue:prune-batches')
    ->daily()
    ->runInBackground();

// Clean failed jobs older than 48 hours (optional)
Schedule::command('queue:prune-failed --hours=48')
    ->daily()
    ->runInBackground();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
