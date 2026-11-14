<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\StudentSchoolYear;
use App\Models\Team;

/**
 * A team is verified the moment it has one final title.
 *
 * Verifying used to be a button the CaPSA/TSA pressed after the title was
 * already settled. It checked nothing she had not just decided, and until
 * she came back to press it the team could not ask for an adviser. So the
 * title settling is the verification: whoever settles it - the CaPSA/TSA
 * deciding the last title, or the team choosing between two - calls this.
 */
class TeamVerification
{
    /**
     * Verify the team if its title is settled and it is not verified yet.
     *
     * @param  int|null  $settledBy  the expert or student whose action settled the title
     * @param  bool  $tellTheTeam  false when the caller says so in a message of its
     *                             own, so the team gets one notice and one email
     */
    public static function onceTitleIsSettled(Team $team, ?int $settledBy = null, bool $tellTheTeam = true): bool
    {
        if ($team->is_confirmed || ! $team->selected_pre_proposal_id) {
            return false;
        }

        $team->update(['is_confirmed' => true]);

        if ($settledBy) {
            ActivityLog::create([
                'student_id' => $settledBy,
                'type' => 'team_verified',
                'description' => 'Verified team <strong>'.e($team->team_name).'</strong>',
                'metadata' => ['team_id' => $team->team_id, 'verified_by' => $settledBy],
                'created_at' => now(),
            ]);
        }

        if (! $tellTheTeam) {
            return true;
        }

        $memberIds = StudentSchoolYear::where('team_id', $team->team_id)->pluck('student_id');

        foreach ($memberIds as $memberId) {
            Notification::create([
                'student_id' => $memberId,
                'type' => 'team_verified',
                'title' => 'Team Verified',
                'message' => 'Your team <strong>'.e($team->team_name).'</strong> is verified now that its title is settled. You can ask for an adviser.',
                'link' => route('student.team'),
            ]);
        }

        return true;
    }
}
