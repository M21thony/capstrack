<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefenseSchedule;
use App\Models\ExpertRemovalRequest;
use App\Models\MemberLeaveRequest;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\ChecklistTracker;
use App\Support\DefenseProgress;
use App\Support\DefenseScores;
use App\Support\MilestoneProgress;
use App\Support\PreProposalPayload;
use App\Support\SectionScope;
use App\Support\TeamChecklist;
use Illuminate\Http\RedirectResponse;
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
    public const TABS = ['teams', 'pre_proposals', 'verification', 'papers', 'milestones', 'leave_requests', 'rules'];

    /**
     * The tabs that became pages of their own.
     *
     * Their old URLs still arrive from bookmarks and from the notification
     * mails, so they land on the list rather than on nothing.
     */
    private const FOLDED_TABS = ['teams', 'pre_proposals', 'verification', 'papers', 'rules'];

    /** Shown where a value is genuinely absent rather than empty. */
    private const DASH = '—';

    public function __construct(
        private SectionScope $sections,
        private DefenseProgress $progress,
        private TeamChecklist $checklists,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();
        $capsaSections = $capsa->sections ?? [];

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'teams';
        $selectedSection = $request->query('section');

        if (in_array($request->query('tab'), self::FOLDED_TABS, true)) {
            return redirect()->route('capsa.team_management', array_filter(['section' => $selectedSection]));
        }

        $rows = $this->buildRows($capsa, $selectedSection);
        $teamIds = $rows->map(fn ($row) => $row->team->team_id);

        // The Milestones tab is its own tracker: one section at a time, with
        // its own section tabs, so it ignores the page's section filter.
        // Built on every tab, not only that one: the count of papers waiting
        // is the badge that says anything arrived at all, and computing it
        // only once she was already looking at them told her nothing.
        $tracker = app(ChecklistTracker::class)->overview($capsaSections, $selectedSection);

        return view('capsa.team_management', [
            'tab' => $tab,
            'rows' => $rows,
            // Each team's score at every defense, once its whole panel has graded.
            'defenseScores' => DefenseScores::forTeams($teamIds),
            'tracker' => $tab === 'milestones' ? $tracker : null,
            // The tab that is still a page of its own brings its own data,
            // built only when it is the one open.
            'tabData' => match ($tab) {
                'leave_requests' => app(LeaveRequestController::class)->pageData($capsa),
                default => [],
            },
            // Team Settings is a small menu under its own button on the list,
            // not a page, so the list always carries what it shows.
            'rules' => TeamRulesController::pageData(),
            'selectedSection' => $selectedSection,
            'allSections' => $capsaSections,
            'sectionOptions' => collect(AdminProfileController::getAllAvailableSections())
                ->filter(fn ($s) => in_array($s, $capsaSections, true))
                ->values(),
            'counts' => [
                'teams' => $rows->count(),
                // Signed papers waiting for her check, the one thing on the
                // tracker that is hers to act on.
                'milestones' => collect($tracker['sections'])->sum('to_check'),
                'pre_proposals' => $rows->sum(fn ($r) => $r->pendingProposalCount),
                'verification' => $rows->where('awaitingVerification', true)->count(),
                'papers' => $rows->sum(fn ($r) => $r->papersToCheck),
                'leave_requests' => MemberLeaveRequest::pendingCountFor($capsa)
                    + ExpertRemovalRequest::pendingCountFor($capsa),
            ],
        ]);
    }

    /**
     * One team, everything about it.
     *
     * The milestone shown is the CaPSA's pick, or the first one that is not
     * finished - the one she is most likely to have come here for.
     */
    public function show(Request $request, Team $team): View
    {
        $row = $this->teamRow($team);
        $milestones = $row->milestones?->milestones ?? collect();

        $selected = $request->integer('milestone');

        if (! $milestones->has($selected)) {
            $selected = $milestones->search(fn ($m) => $m->completed < $m->total);
            $selected = $selected === false ? 0 : $selected;
        }

        return view('capsa.team-management.show', [
            'row' => $row,
            'trail' => $this->trail($row),
            'defenseScores' => DefenseScores::forTeams(collect([$team->team_id])),
            'experts' => $this->expertCards($row),
            'milestones' => $milestones,
            'selectedIndex' => $selected,
            'selected' => $milestones->get($selected),
            'defenseSchedules' => DefenseSchedule::where('team_id', $team->team_id)
                ->orderBy('schedule_date')->get(),
        ]);
    }

    /** Every title this team turned in, one line each. */
    public function preProposals(Team $team): View
    {
        $row = $this->teamRow($team);

        return view('capsa.team-management.pre-proposals', [
            'row' => $row,
            'trail' => $this->trail($row, [['Pre-proposals', null]]),
            'titles' => PreProposalPayload::forTeam($row->team),
        ]);
    }

    /** One title: the decision, the form answers, and the similarity report. */
    public function title(Team $team, CapstonePreProposal $preProposal): View
    {
        $row = $this->teamRow($team);
        [$titles, $index] = $this->locateTitle($row, $preProposal);

        return view('capsa.team-management.title', [
            'row' => $row,
            'trail' => $this->trail($row, [
                ['Pre-proposals', route('capsa.team_management.pre_proposals', $team->team_id)],
                ['Title #'.($index + 1), null],
            ]),
            'titles' => $titles,
            'title' => $titles[$index],
            'index' => $index,
            'matches' => $this->matchesFor($titles[$index]),
        ]);
    }

    /** One archive study the check matched, with Previous and Next. */
    public function similar(Team $team, CapstonePreProposal $preProposal, int $rank): View
    {
        $row = $this->teamRow($team);
        [$titles, $index] = $this->locateTitle($row, $preProposal);
        $matches = $this->matchesFor($titles[$index]);

        // Ranks are what the page shows, so they count from 1.
        if ($rank < 1 || $rank > count($matches)) {
            abort(404);
        }

        return view('capsa.team-management.similar', [
            'row' => $row,
            'trail' => $this->trail($row, [
                ['Pre-proposals', route('capsa.team_management.pre_proposals', $team->team_id)],
                ['Title #'.($index + 1), route('capsa.team_management.title', [$team->team_id, $preProposal->id])],
                ['Similar study #'.$rank, null],
            ]),
            'title' => $titles[$index],
            'preProposal' => $preProposal,
            'matches' => $matches,
            'rank' => $rank,
            'match' => $matches[$rank - 1],
        ]);
    }

    /**
     * The row for a team the CaPSA actually oversees.
     *
     * Authorisation is the same question as "is this team on her list?", so
     * it is answered by her list rather than by a second set of rules that
     * could drift from it. A team outside her sections simply is not there.
     */
    private function teamRow(Team $team): object
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();

        return $this->buildRows($capsa, null)
            ->first(fn ($row) => (int) $row->team->team_id === (int) $team->team_id)
            ?? abort(404);
    }

    /**
     * The title's place in the team's list.
     *
     * Found in the list the Pre-proposals page shows, so a title belonging to
     * another team - or a draft that was never turned in - is not found here
     * and gives the same "not found" as a team she does not oversee.
     *
     * @return array{0: array<int, array>, 1: int}
     */
    private function locateTitle(object $row, CapstonePreProposal $preProposal): array
    {
        $titles = PreProposalPayload::forTeam($row->team);
        $index = collect($titles)->search(fn ($t) => (int) $t['id'] === (int) $preProposal->id);

        if ($index === false) {
            abort(404);
        }

        return [$titles, $index];
    }

    /** The ranked archive matches for a title, capped at the ten shown. */
    private function matchesFor(array $title): array
    {
        return collect($title['duplication']['matches'] ?? [])->take(10)->values()->all();
    }

    /**
     * The breadcrumb trail, as [label, url] pairs ending on the current page.
     *
     * @param  array<int, array{0: string, 1: ?string}>  $below
     * @return array<int, array{0: string, 1: ?string}>
     */
    private function trail(object $row, array $below = []): array
    {
        $teamUrl = $below === [] ? null : route('capsa.team_management.show', $row->team->team_id);

        return array_merge([
            ['Team Management', route('capsa.team_management')],
            [$row->team->team_name, $teamUrl],
        ], $below);
    }

    /**
     * The three roles a team engages, always all three and always in order,
     * so an empty one is visibly empty rather than missing.
     *
     * @return array<int, object>
     */
    private function expertCards(object $row): array
    {
        return collect(['adviser', 'consultant', 'grammarian'])
            ->map(function (string $role) use ($row) {
                $expert = $row->engagedRoles->get($role);

                return (object) [
                    'role' => $role,
                    'expert' => $expert,
                    'degree' => $expert?->degreeLabel(),
                    'expertise' => $expert
                        ? $expert->expertise->pluck('expertise_field')->filter()->values()
                        : collect(),
                ];
            })
            ->all();
    }

    /**
     * Every team the CaPSA oversees, each summarised into one row.
     *
     * One pass, a handful of queries, and every page below the list reads the
     * same rows - which is what lets a team page answer "is this team mine?"
     * without a second set of rules about who may see what.
     *
     * @return Collection<int, object>
     */
    private function buildRows(CapstoneExpert $capsa, ?string $selectedSection): Collection
    {
        $scoped = $this->scopedTeams($capsa->sections ?? [], $selectedSection);
        $teams = $scoped['teams'];
        $teamIds = $teams->pluck('team_id');

        $milestones = $this->milestoneProgress($teams, $scoped['teamSections']);
        $defenseSchedules = DefenseSchedule::whereIn('team_id', $teamIds)->get()->groupBy('team_id');
        $engagedRoles = $this->engagedRoles($teamIds);
        $papers = $this->paperChecks($capsa->expert_id, $teamIds);

        return $teams->map(fn (Team $team) => $this->summarise(
            $team,
            $scoped['teamSections'][$team->team_id] ?? SectionScope::UNASSIGNED,
            $scoped['teamMembers'][$team->team_id] ?? collect(),
            $milestones[$team->team_id] ?? null,
            $engagedRoles[$team->team_id] ?? collect(),
            $papers[$team->team_id] ?? collect(),
            $defenseSchedules[$team->team_id] ?? collect(),
        ));
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

        $candidates = Team::with(['leader', 'preProposals.duplicationCheck', 'allPreProposals.duplicationCheck'])
            ->whereIn('team_id', $syRecords->keys())
            ->orderByDesc('created_at')
            ->get();

        return $this->sections->filterTeams($candidates, $syRecords, $capsaSections, $selectedSection);
    }

    /**
     * Each team's progress on its section's checklist, worked out a section at
     * a time so the whole page costs a handful of queries.
     *
     * The whole resolution is kept, not just the totals: the list shows the
     * bar, the progress grid shows every milestone, and the team page shows
     * every item - all off this one pass.
     *
     * @param  array<int, string>  $teamSections
     * @return array<int, object>
     */
    private function milestoneProgress(Collection $teams, array $teamSections): array
    {
        $out = [];

        foreach ($teams->groupBy(fn (Team $team) => $teamSections[$team->team_id] ?? '') as $section => $group) {
            $checklist = $this->checklists->forSection((string) $section);

            if (! $checklist) {
                continue;
            }

            foreach (MilestoneProgress::forTeams($checklist, $group->pluck('team_id')) as $teamId => $progress) {
                $out[$teamId] = $progress;
            }
        }

        return $out;
    }

    /**
     * Which roles each team has actually filled.
     *
     * Read from the assignment's expert_role_id, so an expert who is both an
     * adviser and a consultant is counted as whichever the team engaged them
     * as - not both, and not a guess.
     *
     * The expert themself, not just their name: the team page shows their
     * degree, their fields and their email, and that is one eager-load here
     * rather than a second lookup a level down.
     *
     * @return array<int, Collection<string, CapstoneExpert>>
     */
    private function engagedRoles(Collection $teamIds): array
    {
        if ($teamIds->isEmpty()) {
            return [];
        }

        return TeamExpertAssignment::whereIn('team_id', $teamIds)
            ->where('status', 'approved')
            ->with(['expert.expertise', 'expertRole.role', 'role'])
            ->get()
            ->groupBy('team_id')
            ->map(function ($assignments) {
                $filled = collect();

                foreach ($assignments as $assignment) {
                    $role = $assignment->engaged_role_name;

                    if ($role && $assignment->expert) {
                        $filled->put($role, $assignment->expert);
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
            $pending->isEmpty() => 'all_rejected',
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
}
