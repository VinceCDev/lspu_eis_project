<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run by `php artisan schedule:work` (the `scheduler` service in docker-compose) or by cron:
//   * * * * * cd /var/www/html && php artisan schedule:run
// dead-import recovery, stale/daily dashboard-summary rebuilds, old upload cleanup.
Schedule::command('imports:reap')->everyMinute()->withoutOverlapping(5);
Schedule::command('reporting:refresh')->everyMinute()->withoutOverlapping(5);
Schedule::command('imports:reap --purge')->dailyAt('03:30');
// Alumni Location map: geocode new locations in the background so the Dashboard never does it while a user waits.
Schedule::command('map:geocode-locations --limit=20')->hourly()->withoutOverlapping(30);
