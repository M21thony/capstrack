<?php

namespace App\Support;

use App\Models\DefenseSchedule;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

/**
 * When a team's title may change, read off its Pre-proposal Defense.
 *
 *   Passed                       - the title stands as approved.
 *   Passed with revision         - the leader may revise the wording, until
 *                                  the Signed Final Project Title Form is in.
 *   Redefense with a new title   - the old titles are set aside; the team
 *                                  turns in new ones and keeps its experts.
 */
class TitleRevision
{
    public const FINAL_TITLE_FORM = 'final_title_form';

    /** The team's most recent Pre-proposal Defense that has a result. */
    public static function latestDecision(Team $team): ?DefenseSchedule
    {
        return DefenseSchedule::where('team_id', $team->team_id)
            ->where('defense_type', DefenseOutcome::PRE_PROPOSAL)
            ->whereNotNull('outcome')
            ->orderByDesc('attempt')
            ->first();
    }

    /** Whether the team has uploaded its Signed Final Project Title Form since this moment. */
    public static function finalTitleFormIn(Team $team, mixed $since = null): bool
    {
        return DB::table('capstone_documents as d')
            ->join('deliverables as dl', 'dl.id', '=', 'd.deliverable_id')
            ->join('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->where('sy.team_id', $team->team_id)
            ->where('dl.template_key', self::FINAL_TITLE_FORM)
            ->when($since, fn ($q) => $q->where('d.created_at', '>=', $since))
            ->exists();
    }

    /** Why the title cannot be revised, or null when it can. */
    public static function whyNot(Team $team): ?string
    {
        $decision = self::latestDecision($team);

        return match (true) {
            ! $decision => 'A title can be revised only after your panel passes it with revision.',
            $decision->outcome === DefenseOutcome::PASSED => 'Your panel passed this title as it is, so it cannot be changed.',
            $decision->outcome !== DefenseOutcome::PASSED_WITH_REVISION => 'A title can be revised only after your panel passes it with revision.',
            ! $team->project_title => 'Your team has no approved title to revise yet.',
            self::finalTitleFormIn($team, $decision->outcome_recorded_at) => 'Your Signed Final Project Title Form is in, so the title is final.',
            default => null,
        };
    }

    public static function canRevise(Team $team): bool
    {
        return self::whyNot($team) === null;
    }

    /** Whether the panel sent the team back for a new title it has not settled yet. */
    public static function needsNewTitle(Team $team): bool
    {
        return self::latestDecision($team)?->outcome === DefenseOutcome::REDEFENSE_NEW_TITLE
            && ! $team->selected_pre_proposal_id;
    }
}
