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

// On the defense day, a team whose requirements are still open loses its
// date and waits under Ready to schedule for a new one.
Schedule::command('defenses:cancel-unready')->dailyAt('00:10');

// Every morning, each adviser/consultant with checklist documents still
// waiting on their review gets one email listing them.
Schedule::command('milestones:send-review-reminders')->dailyAt('07:00');

// Milestones and deliverables a CaPSA removed are kept a few days so Undo can
// bring them back, then deleted for good.
Schedule::command('checklists:purge-removed')->dailyAt('02:00');

// A few days before a checklist deadline, each team that has not met it hears
// about it once. Web only - there was never an email for this.
Schedule::command('milestones:send-due-reminders')->dailyAt('07:05');

// When a team finishes a phase, its leader is told to evaluate the experts -
// once per phase, so running it often only makes the email arrive sooner.
Schedule::command('evaluations:send-reminders')->hourly();

// A request to leave a team, or to remove a member, that the CaPSA has not
// decided in three days is declined on its own and the team is told why.
Schedule::command('leave-requests:expire')->hourly();
