<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\Milestone;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\DefenseProgress;
use App\Support\MilestoneGrid;
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
    public const TABS = ['teams', 'pre_proposals', 'verification', 'papers', 'milestones'];

    /** Shown where a value is genuinely absent rather than empty. */
    private const DASH = '—';

    public function __construct(
        private SectionScope $sections,
        private DefenseProgress $progress,
    ) {}

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
        $defenseSchedules = DefenseSchedule::whereIn('team_id', $teamIds)->get()->groupBy('team_id');
        $engagedRoles = $this->engagedRoles($teamIds);
        $papers = $this->paperChecks($capsa->expert_id, $teamIds);

        $rows = $teams->map(fn (Team $team) => $this->summarise(
            $team,
            $scoped['teamSections'][$team->team_id] ?? SectionScope::UNASSIGNED,
            $scoped['teamMembers'][$team->team_id] ?? collect(),
            $milestones[$team->team_id] ?? null,
            $engagedRoles[$team->team_id] ?? collect(),
            $papers[$team->team_id] ?? collect(),
            $defenseSchedules[$team->team_id] ?? collect(),
        ));

        return view('capsa.team_management', [
            'tab' => $tab,
            'rows' => $rows,
            // Only the Milestones tab needs the items themselves; every other
            // tab is served by the counts already in $rows.
            // The Milestones tab is a grid: deliverables across, teams down.
            'grid' => $tab === 'milestones' ? $this->milestoneGrid($teams, $rows, $capsaSections) : null,
            'templates' => \App\Support\MilestoneTemplate::all(),
            // How many teams a new checklist would actually reach. The grid
            // lists every team a CaPSA oversees, including ones still forming,
            // but only a confirmed team can be given one - so the two numbers
            // differ and the modal has to say which it means.
            'sectionTeamCount' => $tab === 'milestones' && $selectedSection
                ? $this->confirmedTeamsIn($selectedSection)
                : null,
            // Every section the CaPSA runs, with the number of confirmed teams
            // in each. The New checklist form asks for the section itself
            // rather than borrowing whatever the page happens to be filtered
            // to - a CaPSA who lands on the tab and clicks the button should
            // be able to finish, not be sent back out to set a filter first.
            'sectionChoices' => $tab === 'milestones'
                ? collect($capsaSections)->mapWithKeys(
                    fn ($section) => [$section => $this->confirmedTeamsIn($section)]
                )->all()
                : [],
            'selectedSection' => $selectedSection,
            'allSections' => $capsaSections,
            'sectionOptions' => collect(AdminProfileController::getAllAvailableSections())
                ->filter(fn ($s) => in_array($s, $capsaSections, true))
                ->values(),
            'counts' => [
                'teams' => $rows->count(),
                // What the CaPSA still has to tick herself. The other kinds
                // close themselves, so counting them here would ask her to
                // chase something she cannot act on.
                'milestones' => $tab === 'milestones' ? 0 : null,
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
        // Scoped to the term that is running - StudentSchoolYear does that by
        // default - so last semester's teams do not reappear alongside this
        // semester's, and a team retaking Capstone 1 is here on its new
        // enrolment rather than its old one.
        $syRecords = StudentSchoolYear::whereNotNull('team_id')
            ->with('student')
            ->get()
            ->groupBy('team_id');

        $candidates = Team::with(['leader', 'preProposals.duplicationCheck'])
            ->whereIn('team_id', $syRecords->keys())
            ->orderByDesc('created_at')
            ->get();

        return $this->sections->filterTeams($candidates, $syRecords, $capsaSections, $selectedSection);
    }

    /** Confirmed teams in a section - the ones a checklist can be given to. */
    private function confirmedTeamsIn(string $section): int
    {
        return Team::where('is_confirmed', true)
            ->get()
            ->filter(fn (Team $team) => $this->sections->sectionFor($team) === $section)
            ->count();
    }

    /**
     * The Milestones tab: every team in the CaPSA's sections against every
     * deliverable on their checklist.
     *
     * It is both where a checklist gets built and where the papers get ticked
     * off, because a CaPSA working down a section is doing the two together.
     */
    private function milestoneGrid(Collection $teams, Collection $rows, array $capsaSections): object
    {
        // Only teams that actually sit in one of the CaPSA's sections.
        //
        // The other tabs deliberately surface a team whose section could not
        // be worked out - a half-formed team is exactly what an oversight page
        // should show. A checklist is different: it is given to a section, and
        // a team that belongs to none can never be given one, so listing them
        // here is a page full of rows nothing can be done to.
        $scoped = $rows->filter(
            fn ($row) => $row->section !== SectionScope::UNASSIGNED
                && $this->sections->covers($capsaSections, $row->section)
        )->values();

        $milestones = Milestone::whereIn('team_id', $scoped->pluck('team.team_id'))
            ->with('items')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        // One checklist per team is the shape this grid reads. If a team
        // somehow has several, the earliest is the one shown.
        $primary = $milestones->groupBy('team_id')->map->first()->values();

        return MilestoneGrid::build($primary, $scoped);
    }

    /** @return array<int, object{total: int, completed: int, overdue: int, percent: int}> */
    private function milestoneProgress(Collection $teamIds): array
    {
        if ($teamIds->isEmpty()) {
            return [];
        }

        // Counted from the items, not from milestones.status. That column was
        // written once as 'todo' when a milestone was created and never moved
        // again, so 'completed' and 'overdue' matched nothing and this column
        // showed a dash for every team however far along they were.
        return Milestone::whereIn('team_id', $teamIds)
            ->with('items')
            ->get()
            ->groupBy('team_id')
            ->map(function ($rows) {
                $total = 0;
                $completed = 0;
                $overdue = 0;

                foreach ($rows as $milestone) {
                    $progress = $milestone->progress();
                    $total += $progress->total;
                    $completed += $progress->completed;
                    $overdue += $progress->overdue;
                }

                return (object) [
                    'total' => $total,
                    'completed' => $completed,
                    'overdue' => $overdue,
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
        Collection $defenseSchedules,
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

        // Where they stand at each defense stage. Failing is running out of
        // window rather than a verdict, so it is worked out from the dates
        // and the recorded outcomes - which also means the CAPSA extending a
        // deadline un-fails everyone it covers, with nothing to reset.
        $stages = $this->progress->allStages($team, $defenseSchedules);

        return (object) [
            'team' => $team,
            'section' => $section,
            'members' => $members,
            'stages' => $stages,
            'currentStage' => $this->progress->currentStage($team, $defenseSchedules),
            'failedStage' => collect($stages)->filter(fn ($s) => $s === DefenseProgress::FAILED)->keys()->first(),
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
            'pre_proposals' => \App\Support\PreProposalPayload::forTeam($team),
            'selected_pre_proposal_id' => $team->selected_pre_proposal_id,
        ];

        // The modal shows a Verify button only when there is somewhere to
        // post it; a verified team is read-only.
        return $team->is_confirmed
            ? $payload + ['verified' => true]
            : $payload + ['verify_url' => route('admin.verify_teams.verify', $team->team_id)];
    }
}
