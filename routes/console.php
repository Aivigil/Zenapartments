<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled jobs
Schedule::command('reminders:dispatch')
    ->dailyAt('09:00')
    ->timezone('Asia/Karachi')
    ->withoutOverlapping()
    ->onOneServer();

// Future:
// Schedule::command('statements:monthly')->monthlyOn(1, '06:00');
// Schedule::command('reconciliation:nightly')->dailyAt('02:00');
// Schedule::command('crm:reconcile')->dailyAt('03:00');
