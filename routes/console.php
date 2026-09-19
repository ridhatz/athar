<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deadline reminders. Needs the scheduler running: * * * * * php artisan schedule:run (see README).
Schedule::command('athar:notify-deadlines')->hourly()->withoutOverlapping();
