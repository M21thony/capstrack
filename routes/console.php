<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Same-day consultation reminders — runs right after midnight so
// students/experts get emailed as early as possible on the exact day of a
// booked session, not near/at the session's own start time.
Schedule::command('consultations:send-reminders')->dailyAt('00:05');

// Every morning, each adviser/consultant with checklist documents still
// waiting on their review gets one email listing them.
Schedule::command('milestones:send-review-reminders')->dailyAt('07:00');
