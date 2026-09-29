<?php

namespace App\Support;

use App\Models\CapstoneDocument;
use App\Models\CapstonePreProposal;
use App\Models\DefenseSchedule;
use App\Models\DeliverableCheck;
use App\Models\Team;
use App\Models\TitleChangeRequest;

/**
 * When a team's title may change, read off its Pre-proposal Defense.
 *
 *   Passed                       - the title stands as approved.
 *   Passed with revision         - the leader may revise the wording, until
 *                                  the Signed Final Project Title Form is in.
 *   Redefense with a new title   - the old titles are set aside; the team
 *                                  turns in new ones and keeps its experts.
 *                                  The CaPSA approves the new title, then the
 *                                  adviser confirms it before it is the team's.
 *
 * Once passed, the leader can also ask the adviser to change part of the
 * title (a TitleChangeRequest); only an approved one replaces it.
 */
class TitleRevision
{
    public const FINAL_TITLE_FORM = 'final_title_form';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    /** Put an approved new title in front of the team's adviser. */
    public static function askAdviser(CapstonePreProposal $proposal): void
    {
        $proposal->update(['adviser_status' => self::PENDING, 'adviser_note' => null, 'adviser_decided_at' => null]);
    }

    /** The new title the team's adviser is still to confirm, if any. */
    public static function awaitingAdviser(Team $team): ?CapstonePreProposal
    {
        return $team->preProposals()->where('adviser_status', self::PENDING)->first();
    }

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
        return CapstoneDocument::ofTeam($team->team_id)
            ->whereHas('deliverable', fn ($q) => $q->where('template_key', self::FINAL_TITLE_FORM))
            ->when($since, fn ($q) => $q->where('capstone_documents.created_at', '>=', $since))
            ->exists();
    }

    /**
     * Whether the CaPSA has checked the team's Signed Final Project Title
     * Form as complete. From then the title is final.
     */
    public static function finalTitleVerified(Team $team): bool
    {
        return DeliverableCheck::where('team_id', $team->team_id)
            ->where('status', DeliverableCheck::COMPLETE)
            ->whereHas('deliverable', fn ($q) => $q->where('template_key', self::FINAL_TITLE_FORM))
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

    /**
     * Why the team cannot ask its adviser to change part of its title, or
     * null when it can. Open once the Pre-proposal Defense is passed and until
     * the CaPSA verifies the Signed Final Project Title Form - except while
     * the leader may still reword it freely (passed with revision, final
     * title form not in) or a request is already with the adviser.
     */
    public static function whyNotRequestChange(Team $team): ?string
    {
        $decision = self::latestDecision($team);

        return match (true) {
            ! $decision || ! in_array($decision->outcome, [DefenseOutcome::PASSED, DefenseOutcome::PASSED_WITH_REVISION], true) => 'You can ask to change your title once your panel has passed it.',
            ! $team->project_title => 'Your team has no approved title to change yet.',
            self::finalTitleVerified($team) => 'Your CaPSA has verified your Signed Final Project Title Form, so the title is final.',
            self::canRevise($team) => 'You can still reword your title yourself until the Signed Final Project Title Form is in.',
            self::pendingChange($team) !== null => 'Your adviser is still deciding on the change you asked for.',
            default => null,
        };
    }

    /** The title change the team's adviser is still to decide on, if any. */
    public static function pendingChange(Team $team): ?TitleChangeRequest
    {
        return TitleChangeRequest::where('team_id', $team->team_id)->where('status', TitleChangeRequest::PENDING)->latest('id')->first();
    }

    /** The most recent title change the adviser decided on, if any. */
    public static function lastDecidedChange(Team $team): ?TitleChangeRequest
    {
        return TitleChangeRequest::where('team_id', $team->team_id)->where('status', '!=', TitleChangeRequest::PENDING)->latest('id')->first();
    }

    /** How much of a title a correction may change: a fifth of it, never less than a few letters. */
    public const CORRECTION_SHARE = 0.2;

    public const CORRECTION_MIN = 5;

    /**
     * Whether $to only corrects $from - a misspelling, a wrong letter case,
     * punctuation, a word or two - rather than being a different title.
     *
     * A leader may always fix the approved title's spelling (Update Team).
     * Anything bigger is a new title, which goes through the CaPSA, or a
     * revision, which the panel has to allow (whyNot()).
     */
    public static function isCorrection(string $from, string $to): bool
    {
        $normalize = fn (string $title) => trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', mb_strtolower($title))));
        $a = $normalize($from);
        $b = $normalize($to);

        if ($a === $b) {
            return true;
        }

        $allowed = max(self::CORRECTION_MIN, (int) ceil(self::CORRECTION_SHARE * max(strlen($a), strlen($b))));

        return levenshtein($a, $b) <= $allowed;
    }

    /** Whether the panel sent the team back for a new title it has not settled yet. */
    public static function needsNewTitle(Team $team): bool
    {
        return self::latestDecision($team)?->outcome === DefenseOutcome::REDEFENSE_NEW_TITLE
            && ! $team->selected_pre_proposal_id;
    }
}
