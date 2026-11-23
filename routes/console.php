<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| `funds:refresh` runs hourly to pull the latest data from the AJ Bell CDN.
| `withoutOverlapping` guards against a slow run bleeding into the next tick.
| `runInBackground` means subsequent scheduler entries (if any) aren't blocked
| while this command finishes.
|
| Only fires while Laravel's scheduler is running — `php artisan schedule:work`
| locally, or a crontab calling `schedule:run` every minute in production.
|
*/

Schedule::command('funds:refresh')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
