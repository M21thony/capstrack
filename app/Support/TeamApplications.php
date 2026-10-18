<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\TeamExpertAssignment;
use App\Services\RecipientResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * The teams asking an expert to be their adviser, consultant or grammarian.
 *
 * What the Team Applications tab of Teams lists, and what the To do's
 * "Read application" opens: the team, the document they applied with and
 * the expert's slots. Teams already accepted are on the Teams tab, so only
 * the waiting and the declined are kept here.
 */
class TeamApplications
{
    /** How many declined applications the tab keeps. */
    public const DECLINED_KEPT = 10;

    /** The seats a team applies for. */
    public const ROLES = ['adviser', 'consultant', 'grammarian'];

    public function __construct(private RecipientResolver $resolver) {}

    /**
     * @return array{
     *     waiting: Collection<int, TeamExpertAssignment>,
     *     declined: Collection<int, TeamExpertAssignment>,
     *     usedSlots: int,
     *     maxSlots: int,
     *     open: bool,
     *     details: array<int, array<string, mixed>>,
     *     titles: array<int, array<string, mixed>>
     * }
     */
    public function forExpert(CapstoneExpert $expert): array
    {
        $with = ['team.leader', 'team.members', 'expertRole.role', 'role', 'preProposal.duplicationCheck'];

        // A queue is worked from the front: whoever has waited longest first.
        $waiting = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'pending')
            ->with($with)
            ->oldest()
            ->get()
            ->filter(fn ($assignment) => $assignment->team)
            ->values();

        $declined = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'rejected')
            ->with($with)
            ->latest('updated_at')
            ->take(self::DECLINED_KEPT)
            ->get()
            ->filter(fn ($assignment) => $assignment->team)
            ->values();

        return [
            // Teams can only apply to someone who is an adviser, consultant or
            // grammarian; a CaPSA who is none of these has nothing to list.
            'open' => $waiting->isNotEmpty() || $declined->isNotEmpty()
                || $expert->roles()->whereIn('role_name', self::ROLES)->exists(),
            'waiting' => $waiting,
            'declined' => $declined,
            'usedSlots' => TeamExpertAssignment::where('expert_id', $expert->expert_id)->where('status', 'approved')->count(),
            'maxSlots' => (int) ($expert->max_teams ?? 0),
            'details' => $waiting->concat($declined)
                ->mapWithKeys(fn ($assignment) => [$assignment->assignment_id => $this->details($assignment)])
                ->all(),
            'titles' => $this->titlesToConfirm($expert),
        ];
    }

    /**
     * Everything the application window shows beside the document.
     *
     * @return array<string, mixed>
     */
    public function details(TeamExpertAssignment $assignment): array
    {
        $team = $assignment->team;
        $leader = $team?->leader;
        $check = $assignment->preProposal?->duplicationCheck;

        return [
            'id' => $assignment->assignment_id,
            'team' => $team?->team_name ?? 'Unknown',
            'project' => $assignment->preProposal?->title ?: ($team?->project_title ?: ''),
            // Applied with the pre-proposal their CaPSA approved: the expert
            // is reading the document that got through, not one the team chose.
            'recommended' => (bool) $assignment->preProposal,
            'role' => ucfirst($assignment->engaged_role_name ?: 'expert'),
            'leader' => $leader ? trim(($leader->f_name ?? '').' '.($leader->l_name ?? '')) : '',
            'leaderEmail' => $leader?->email ?? '',
            'members' => $team ? $team->members
                ->reject(fn ($member) => $member->student_id === $team->leader_id)
                ->map(fn ($member) => trim(($member->f_name ?? '').' '.($member->l_name ?? '')))
                ->filter()
                ->values()
                ->all() : [],
            'message' => trim((string) $assignment->message),
            // proposal_file is stored on the public disk by the student's
            // application, so it resolves through that disk explicitly.
            'document' => $assignment->proposal_file
                ? Storage::disk('public')->url($assignment->proposal_file)
                : ($assignment->preProposal?->duplication_check_id
                    ? route('instructor.assignments.pre_proposal', $assignment->assignment_id)
                    : null),
            'verdict' => $check?->verdict,
            'topScore' => $check?->top_score,
            'applied' => $assignment->created_at?->format('M j, Y g:i A') ?? '',
            'waited' => $assignment->created_at?->diffForHumans() ?? '',
            'stale' => (bool) $assignment->created_at?->lte(now()->subDays(TeamTodo::LATE_AFTER_DAYS)),
            'declinedOn' => $assignment->updated_at?->format('M j, Y') ?? '',
            'reason' => $assignment->rejection_reason,
            'status' => $assignment->status,
            'acceptUrl' => route('instructor.assignments.update', $assignment->assignment_id),
            'removeUrl' => route('instructor.assignments.remove', $assignment->assignment_id),
        ];
    }

    /**
     * New titles waiting for this expert to confirm as the team's adviser.
     *
     * @return array<int, array<string, mixed>>
     */
    private function titlesToConfirm(CapstoneExpert $expert): array
    {
        return CapstonePreProposal::where('adviser_status', TitleRevision::PENDING)
            ->whereNull('retired_at')
            ->with(['team', 'duplicationCheck'])
            ->get()
            ->filter(fn ($proposal) => $proposal->team
                && $this->resolver->resolveTeamRoleExpert((int) $proposal->team_id, 'adviser') === (int) $expert->expert_id)
            ->mapWithKeys(fn ($proposal) => [$proposal->id => [
                'id' => $proposal->id,
                'team' => $proposal->team->team_name,
                'title' => $proposal->title,
                'replaces' => $proposal->team->allPreProposals()->whereNotNull('retired_at')->pluck('title')->filter()->values()->all(),
                'verdict' => $proposal->duplicationCheck?->verdict,
                'topScore' => $proposal->duplicationCheck?->top_score,
                'document' => $proposal->duplicationCheck?->document_path
                    ? route('instructor.title-confirmations.document', $proposal)
                    : null,
                'sent' => $proposal->created_at?->format('M j, Y g:i A') ?? '',
                'updateUrl' => route('instructor.title-confirmations.update', $proposal),
            ]])
            ->all();
    }
}
