<?php

namespace App\Console\Commands;

use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\ExpertEvaluations;
use Illuminate\Console\Command;

/**
 * Tell each team leader when a finished phase leaves their experts waiting
 * to be evaluated - bell, desktop popup and email, once per phase.
 *
 * The portal also checks whenever the leader opens a page, so this is what
 * reaches a leader who has not signed in since the phase was finished.
 */
class SendEvaluationReminders extends Command
{
    protected $signature = 'evaluations:send-reminders';

    protected $description = 'Notify team leaders about finished phases whose experts they have not evaluated yet';

    public function handle(ExpertEvaluations $evaluations): int
    {
        $teamIds = StudentSchoolYear::whereNotNull('team_id')->distinct()->pluck('team_id');

        $told = 0;

        foreach (Team::whereIn('team_id', $teamIds)->whereNotNull('leader_id')->get() as $team) {
            $told += $evaluations->remind($team);
        }

        $this->info("Reminded leaders about {$told} finished phase(s).");

        return self::SUCCESS;
    }
}
