<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\Milestone;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\SectionScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Everything a CaPSA does with the teams in her sections, on one page.
 *
 * These jobs used to be scattered: pre-proposal review and verification sat
 * together under "Team Verification" (a name that only described half of
 * it), while nothing showed how a section's teams were actually getting on.
 * They are all facts about the same teams, so they are tabs on one page.
 *
 * Note the axis. This is section oversight - every team in the sections she
 * covers, whoever advises them. It is deliberately NOT "My Expert Duties",
 * which is the separate question of teams that engaged HER as adviser,
 * consultant or grammarian.
 */
class TeamManagementController extends Controller
{
    public const TABS = ['teams', 'pre_proposals', 'verification', 'papers'];

    /** Shown where a value is genuinely absent rather than empty. */
    private const DASH = '—';

    public function __construct(private SectionScope $sections) {}

    public function index(Request $request): View
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();
        $capsaSections = $capsa->sections ?? [];

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'teams';
        $selectedSection = $request->query('section');

        // One pass over every team in her sections, then each tab is a view
        // onto that same set rather than its own query - which is what keeps
        // the counts in the tab strip honest.
        $scoped = $this->scopedTeams($capsaSections, $selectedSection);
        $teams = $scoped['teams'];
        $teamIds = $teams->pluck('team_id');

        $milestones = $this->milestoneProgress($teamIds);
        $engagedRoles = $this->engagedRoles($teamIds);
        $papers = $this->paperChecks($capsa->expert_id, $teamIds);

        $rows = $teams->map(fn (Team $team) => $this->summarise(
            $team,
            $scoped['teamSections'][$team->team_id] ?? SectionScope::UNASSIGNED,
            $scoped['teamMembers'][$team->team_id] ?? collect(),
            $milestones[$team->team_id] ?? null,
            $engagedRoles[$team->team_id] ?? collect(),
            $papers[$team->team_id] ?? collect(),
        ));

        return view('capsa.team_management', [
            'tab' => $tab,
            'rows' => $rows,
            'selectedSection' => $selectedSection,
            'allSections' => $capsaSections,
            'sectionOptions' => collect(AdminProfileController::getAllAvailableSections())
                ->filter(fn ($s) => in_array($s, $capsaSections, true))
                ->values(),
            'counts' => [
                'teams' => $rows->count(),
                'pre_proposals' => $rows->sum(fn ($r) => $r->pendingProposalCount),
                'verification' => $rows->where('awaitingVerification', true)->count(),
                'papers' => $rows->sum(fn ($r) => $r->papersToCheck),
            ],
        ]);
    }

    /**
     * Every team in the CaPSA's sections.
     *
     * Unlike Team Verification, this is not limited to teams already marked
     * complete: a team that has stalled while still forming is exactly the
     * kind of thing an oversight page exists to surface.
     */
    private function scopedTeams(array $capsaSections, ?string $selectedSection): array
    {
        $candidates = Team::with(['leader', 'preProposals.duplicationCheck'])
            ->orderByDesc('created_at')
            ->get();

        $syRecords = StudentSchoolYear::whereIn('team_id', $candidates->pluck('team_id'))
            ->with('student')
            ->get()
            ->groupBy('team_id');

        return $this->sections->filterTeams($candidates, $syRecords, $capsaSections, $selectedSection);
    }

    /** @return array<int, object{total: int, completed: int, overdue: int, percent: int}> */
    private function milestoneProgress(Collection $teamIds): array
    {
        if ($teamIds->isEmpty()) {
            return [];
        }

        return Milestone::whereIn('team_id', $teamIds)
            ->get()
            ->groupBy('team_id')
            ->map(function ($rows) {
                $total = $rows->count();
                $completed = $rows->where('status', 'completed')->count();

                return (object) [
                    'total' => $total,
                    'completed' => $completed,
                    'overdue' => $rows->where('status', 'overdue')->count(),
                    'percent' => $total > 0 ? (int) round($completed / $total * 100) : 0,
                ];
            })
            ->all();
    }

    /**
     * Which roles each team has actually filled.
     *
     * Read from the assignment's expert_role_id, so an expert who is both an
     * adviser and a consultant is counted as whichever the team engaged them
     * as - not both, and not a guess.
     *
     * @return array<int, Collection<string, string>>
     */
    private function engagedRoles(Collection $teamIds): array
    {
        if ($teamIds->isEmpty()) {
            return [];
        }

        return TeamExpertAssignment::whereIn('team_id', $teamIds)
            ->where('status', 'approved')
            ->with(['expert', 'expertRole.role', 'role'])
            ->get()
            ->groupBy('team_id')
            ->map(function ($assignments) {
                $filled = collect();

                foreach ($assignments as $assignment) {
                    $role = $assignment->engaged_role_name;

                    if ($role) {
                        $filled->put($role, $assignment->expert?->name ?? 'Unknown');
                    }
                }

                return $filled;
            })
            ->all();
    }

    /**
     * Papers sent to this CaPSA for the format check.
     *
     * Scoped to role = subject_adviser on purpose. Her reviews as an adviser
     * or consultant belong to My Expert Duties -> Document Review; this is
     * the CaPSA-hat queue, which is about how a paper is laid out (margins,
     * spacing, formatting), not what it says.
     *
     * @return array<int, Collection<int, object>>
     */
    private function paperChecks(int $capsaId, Collection $teamIds): array
    {
        if ($teamIds->isEmpty()) {
            return [];
        }

        return DB::table('capstone_document_review as r')
            ->join('capstone_documents as d', 'd.file_id', '=', 'r.file_id')
            ->join('student_s_y as sy', 'sy.student_id', '=', 'd.student_id')
            ->where('r.expert_id', $capsaId)
            ->where('r.role', 'subject_adviser')
            ->whereIn('sy.team_id', $teamIds)
            ->orderByDesc('d.created_at')
            ->get([
                'r.review_id',
                'r.review_status',
                'r.reviewed_at',
                'd.file_name',
                'd.chapter',
                'd.file_version',
                'd.created_at as submitted_at',
                'sy.team_id',
            ])
            ->groupBy('team_id')
            ->all();
    }

    /** One row on the Teams tab: everything the CaPSA needs at a glance. */
    private function summarise(
        Team $team,
        string $section,
        Collection $members,
        ?object $milestones,
        Collection $engagedRoles,
        Collection $papers,
    ): object {
        $submitted = $team->hasSubmittedPreProposals();

        // Only titles the team has actually turned in. A draft they are still
        // working on is not the CaPSA's to read, and counting it would put a
        // number on her Pre-Proposals tab that she cannot act on.
        $proposals = $team->preProposals->whereNotNull('submitted_at')->values();
        $approved = $proposals->where('approved_status', true);
        $pending = $proposals->filter(fn ($p) => ! $p->approved_status && ! $p->rejection_reason);

        // Named states rather than a bare "has a title / hasn't". "Two
        // approved and nobody has picked" is the one that otherwise looks
        // like an unexplained dead end on the verification tab.
        $titleState = match (true) {
            (bool) $team->project_title => 'settled',
            $approved->count() >= 2 => 'awaiting_team_choice',
            $approved->count() === 1 => 'settling',
            $proposals->isEmpty() => 'none_submitted',
            default => 'awaiting_review',
        };

        $initials = collect(explode(' ', $team->team_name))
            ->map(fn ($w) => strtoupper(substr($w, 0, 1)))
            ->take(2)->implode('');

        // Keyed on team_id rather than row position, so a team keeps the same
        // colour as the CaPSA moves between tabs.
        $palette = ['#c9a82c,#8a6e10', '#16a34a,#0891b2', '#1d4ed8,#7c3aed', '#b45309,#dc2626', '#0891b2,#16a34a'];
        $gradient = $palette[$team->team_id % count($palette)];

        $tags = is_array($team->tags) ? array_slice(array_filter(array_map('trim', $team->tags)), 0, 3) : [];

        $leaderName = $team->leader
            ? trim(($team->leader->f_name ?? '').' '.($team->leader->l_name ?? ''))
            : self::DASH;

        return (object) [
            'team' => $team,
            'section' => $section,
            'members' => $members,
            'initials' => $initials,
            'gradient' => $gradient,
            'tags' => $tags,
            'leaderName' => $leaderName,
            // Built here, not in Blade: every tab opens the same review modal,
            // and an @include cannot hand variables back to the view that
            // included it, so a shared partial could not carry this.
            'reviewPayload' => $this->reviewPayload($team, $section, $members, $initials, $gradient, $tags, $leaderName),
            'milestones' => $milestones,
            'engagedRoles' => $engagedRoles,
            'papers' => $papers,
            'papersToCheck' => $papers->where('review_status', '!=', 'Reviewed')->count(),
            'proposals' => $proposals,
            'pendingProposalCount' => $pending->count(),
            'approvedProposalCount' => $approved->count(),
            'titleState' => $titleState,
            'awaitingVerification' => $submitted && ! $team->is_confirmed,
            'canVerify' => $submitted
                && ! $team->is_confirmed
                && (bool) $team->selected_pre_proposal_id,
        ];
    }

    /**
     * The payload openVerifyModal() expects - see
     * resources/views/capsa/partials/team-review-modal.blade.php, which is
     * the same modal Team Verification has always used.
     */
    private function reviewPayload(
        Team $team,
        string $section,
        Collection $members,
        string $initials,
        string $gradient,
        array $tags,
        string $leaderName,
    ): array {
        $payload = [
            'team_id' => $team->team_id,
            'team_name' => $team->team_name,
            'initials' => $initials,
            'gradient' => $gradient,
            'project_title' => $team->project_title ?: self::DASH,
            'tags' => $tags,
            'section' => $section,
            'leader' => $leaderName,
            'members' => $members->map(fn ($m) => trim(($m->f_name ?? '').' '.($m->l_name ?? '')))->filter()->values(),
            'member_count' => $members->count(),
            'pre_proposals' => $team->preProposals->map(fn ($p) => [
                'id' => $p->id,
                'slot' => $p->slot,
                'title' => $p->title,
                'status' => $p->status,
                'rejection_reason' => $p->rejection_reason,
                'sdg_goals' => $p->sdg_goals,
                'research_agenda' => $p->research_agenda,
                'problem_statement' => $p->problem_statement,
                'proposed_solution' => $p->proposed_solution,
                'scope' => $p->scope,
                'limitations' => $p->limitations,
                'sdm' => $p->sdm,
                'duplication' => $p->duplicationCheck ? [
                    'verdict' => $p->duplicationCheck->verdict,
                    'top_score' => $p->duplicationCheck->top_score,
                    'matches' => array_slice($p->duplicationCheck->matches ?? [], 0, 3),
                ] : null,
                'document_url' => $p->duplicationCheck ? route('admin.verify_teams.pre_proposals.document', $p->id) : null,
                'approve_url' => route('admin.verify_teams.pre_proposals.approve', $p->id),
                'reject_url' => route('admin.verify_teams.pre_proposals.reject', $p->id),
            ])->values(),
            'selected_pre_proposal_id' => $team->selected_pre_proposal_id,
        ];

        // The modal shows a Verify button only when there is somewhere to
        // post it; a verified team is read-only.
        return $team->is_confirmed
            ? $payload + ['verified' => true]
            : $payload + ['verify_url' => route('admin.verify_teams.verify', $team->team_id)];
    }
}
