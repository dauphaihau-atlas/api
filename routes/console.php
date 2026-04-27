<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:cancel-stale-imports --minutes=120')->everyThirtyMinutes();
Schedule::command('app:prune-password-resets --hours=24')->daily();
Schedule::command('app:prune-telescope --hours=48')->daily();
Schedule::command('app:cache-warm')->dailyAt('03:00');
Schedule::command('app:prune-imports --days=30')->weekly();
Schedule::command('app:prune-activity-logs --days=90')->monthly();
