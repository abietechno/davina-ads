<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ad data sync (needs the server cron: * * * * * php artisan schedule:run)
Schedule::command('ads:sync --days=7 --level=all')
    ->hourly()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();

// Re-sync 30 days nightly to pick up conversions that Meta/Google attribute late
Schedule::command('ads:sync --days=30 --level=all')
    ->dailyAt('03:10')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping();
