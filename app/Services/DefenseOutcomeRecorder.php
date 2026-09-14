<?php

namespace App\Services;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\Notification;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;

/**
 * Records what a panel decided, and does what that decision means.
 *
 * An outcome is not just a label filed away - each one has a consequence the
 * team then lives with. A redefense has to actually exist as the next
 * attempt, and being sent back for a new title has to reopen the
 * pre-proposals so the team can put one up. Doing that here keeps the
 * consequence next to the decision, rather than leaving a CAPSA to remember
 * to create the follow-up by hand.
 */
class DefenseOutcomeRecorder
{
    /**
     * @return array{schedule: DefenseSchedule, redefense: ?DefenseSchedule, reopened_proposals: bool}
     */
    public function record(
        DefenseSchedule $defense,
        string $outcome,
        CapstoneExpert $chair,
        ?string $remarks = null,
    ): array {
        $defense->update([
            'outcome' => $outcome,
            'outcome_remarks' => $remarks,
            'outcome_recorded_by' => $chair->expert_id,
            'outcome_recorded_at' => now(),
        ]);

        $redefense = null;
        $reopened = false;

        if (DefenseOutcome::requiresRedefense($outcome)) {
            $redefense = $this->openNextAttempt($defense);
        }

        if (DefenseOutcome::requiresNewTitle($outcome) && $defense->team) {
            $reopened = $this->reopenPreProposals($defense->team);
        }

        $this->notifyTeam($defense, $outcome);

        // Capstone 1 is over for this team: its members are locked from here.
        if ($defense->defense_type === DefenseOutcome::TITLE_DEFENSE && DefenseOutcome::isPass($outcome) && $defense->team) {
            (new TeamLock)->lock($defense->team);
        }

        return [
            'schedule' => $defense->fresh(),
            'redefense' => $redefense,
            'reopened_proposals' => $reopened,
        ];
    }

    /**
     * The next try at the same stage.
     *
     * The same panel hears it - a team's panel stays theirs to the end of
     * Capstone 2. Only the date is left blank: it comes from Generate
     * schedule on Defense Scheduling, like any other defense.
     */
    private function openNextAttempt(DefenseSchedule $defense): DefenseSchedule
    {
        $next = (int) DefenseSchedule::where('team_id', $defense->team_id)
            ->where('defense_type', $defense->defense_type)
            ->max('attempt') + 1;

        $redefense = DefenseSchedule::create([
            'team_id' => $defense->team_id,
            'defense_type' => $defense->defense_type,
            'attempt' => $next,
        ]);

        foreach ($defense->panels()->get() as $panel) {
            DefensePanel::create([
                'defense_id' => $redefense->defense_id,
                'expert_id' => $panel->expert_id,
                'panel_role' => $panel->panel_role,
                'created_at' => now(),
            ]);
        }

        return $redefense;
    }

    /**
     * Hand the team's pre-proposals back so they can put up a new title.
     *
     * Unsubmitting rather than deleting: the titles that were turned in stay
     * as a record of what was tried, and the team adds a fresh one alongside
     * them. Their approvals are cleared, since what was approved is exactly
     * what the panel has just sent back.
     */
    private function reopenPreProposals(Team $team): bool
    {
        $proposals = $team->preProposals();

        if (! $proposals->exists()) {
            return false;
        }

        $proposals->update([
            'submitted_at' => null,
            'approved_status' => false,
        ]);

        $team->update([
            'selected_pre_proposal_id' => null,
            'project_title' => null,
        ]);

        return true;
    }

    private function notifyTeam(DefenseSchedule $defense, string $outcome): void
    {
        $team = $defense->team;

        if (! $team) {
            return;
        }

        $label = DefenseOutcome::label($outcome, $defense->defense_type);
        $memberIds = StudentSchoolYear::where('team_id', $team->team_id)->pluck('student_id');

        foreach ($memberIds as $studentId) {
            Notification::create([
                'student_id' => $studentId,
                'type' => 'defense_outcome',
                'title' => $defense->defense_type.' result',
                'message' => 'Your panel decided: <strong>'.e($label).'</strong>. '
                    .e(DefenseOutcome::description($outcome, $defense->defense_type)),
                'link' => route('student.defense-scores'),
            ]);
        }
    }
}
