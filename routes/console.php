<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Same-day consultation reminders — runs every morning so students/experts
// get emailed on the exact day of a booked session.
Schedule::command('consultations:send-reminders')->dailyAt('07:00');
