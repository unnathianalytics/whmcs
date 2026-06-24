<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily expiry reminders + auto-expiry. idea.md §6: runs at 08:00, queued mail prevents timeout.
Schedule::command('reminders:send')->dailyAt('08:00');
