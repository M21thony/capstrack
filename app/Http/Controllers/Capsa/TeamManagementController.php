<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\AdminProfileController;
use App\Http\Controllers\Concerns\ShowsTeamPage;
use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\Checklist;
use App\Models\DefenseSchedule;
use App\Models\ExpertRemovalRequest;
use App\Models\MemberLeaveRequest;
use App\Models\Team;
use App\Support\ChecklistTracker;
use App\Support\DefenseScores;
use App\Support\PreProposalPayload;
use App\Support\TeamChecklist;
use App\Support\TeamRows;
use App\Support\TeamTodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Everything a CaPSA does with the teams in her sections, on one page.
 *
 * Three tabs: To do (every decision waiting on her, in one list), Teams
 * (every team, with its progress as one strip) and Milestones (setting up
 * the checklist). A CaPSA who is also an adviser, consultant, grammarian or
 * panelist gets her expert work here too - one Teams page and one To do,
 * each item saying which hat it is for - rather than a second "My Teams".
 */
class TeamManagementController extends Controller
{
    use ShowsTeamPage;

    public const TABS = ['todo', 'teams', 'pre_proposals', 'verification', 'papers', 'milestones', 'milestone_list', 'leave_requests', 'rules'];

    /** The tabs along the top of the list. The first is the default. */
    public const HUB_TABS = ['todo', 'teams', 'milestone_list'];

    /** How the milestones list can be ordered. The first is the default. */
    public const MILESTONE_SORTS = ['order' => 'Checklist order', 'due' => 'Due date', 'percent' => 'Most complete', 'name' => 'Name'];

    /**
     * The tabs that became pages of their own.
     *
     * Their old URLs still arrive from bookmarks and from the notification
     * mails, so they land on the list rather than on nothing.
     */
    private const FOLDED_TABS = ['pre_proposals', 'verification', 'papers', 'rules'];

    public function __construct(
        private TeamRows $teamRows,
        private TeamTodo $todo,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();
        $capsaSections = $capsa->sections ?? [];

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'todo';
        $selectedSection = $request->query('section');

        if (in_array($request->query('tab'), self::FOLDED_TABS, true)) {
            return redirect()->route('capsa.team_management', array_filter(['section' => $selectedSection]));
        }

        $rows = $this->teamRows->forViewer($capsa, $selectedSection);
        $teamIds = $rows->map(fn ($row) => $row->team->team_id);
        $todo = $this->todo->order($this->todo->forCapsa($capsa, $rows)
            ->concat($this->todo->forExpert($capsa, $rows, 'capsa')));

        // The Milestones tracker is its own page: one section at a time, with
        // its own section tabs, so it ignores the page's section filter.
        $tracker = $tab === 'milestones' ? app(ChecklistTracker::class)->overview($capsaSections, $selectedSection) : null;

        return view('capsa.team_management', [
            'tab' => $tab,
            'portal' => 'capsa',
            'rows' => $rows,
            'todo' => $todo,
            'decided' => in_array($tab, ['todo', 'teams'], true)
                ? $this->todo->decidedByCapsa($capsa, $rows)->concat($this->todo->decidedByExpert($capsa))
                    ->sortByDesc(fn ($d) => $d->at->getTimestamp())->take(30)->values()
                : collect(),
            // Wears an expert's hat on at least one team as well.
            'twoHats' => $rows->contains(fn ($row) => $row->myRole)
                || $todo->contains(fn ($item) => $item->hat !== 'capsa'),
            // Each team's score at every defense, once its whole panel has graded.
            'defenseScores' => DefenseScores::forTeams($teamIds),
            'tracker' => $tracker,
            // The tabs that are still pages of their own bring their own data,
            // built only when they are the one open.
            'tabData' => match ($tab) {
                'leave_requests' => app(LeaveRequestController::class)->pageData($capsa),
                'milestone_list' => $this->milestoneList($request, $capsaSections),
                default => [],
            },
            // Team rules is a small menu under its own button on the list,
            // not a page, so the list always carries what it shows.
            'rules' => TeamRulesController::pageData($capsa),
            'selectedSection' => $selectedSection,
            'allSections' => $capsaSections,
            'sectionOptions' => collect(AdminProfileController::getAllAvailableSections())
                ->filter(fn ($s) => in_array($s, $capsaSections, true))
                ->values(),
            'counts' => [
                'todo' => $todo->count(),
                'teams' => $rows->count(),
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
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();

        return view('capsa.team-management.show', $this->teamPage(
            $request, $team, $this->teamRows->forViewer($capsa), $capsa, 'capsa',
        ));
    }

    /**
     * Everything the team page shows, for either portal.
     *
     * @param  Collection<int, object>  $rows
     */
    public function teamPage(Request $request, Team $team, Collection $rows, CapstoneExpert $viewer, string $portal): array
    {
        $position = $rows->search(fn ($row) => (int) $row->team->team_id === (int) $team->team_id);

        if ($position === false) {
            abort(404);
        }

        $row = $rows[$position];
        $milestones = $row->milestones?->milestones ?? collect();

        $selected = $request->integer('milestone');

        if (! $milestones->has($selected)) {
            $selected = $milestones->search(fn ($m) => $m->completed < $m->total);
            $selected = $selected === false ? 0 : $selected;
        }

        return [
            'portal' => $portal,
            'row' => $row,
            'trail' => self::trailFor($row, $portal),
            'defenseScores' => DefenseScores::forTeams(collect([$team->team_id])),
            'experts' => $this->expertCards($row),
            'milestones' => $milestones,
            'selectedIndex' => $selected,
            'selected' => $milestones->get($selected),
            'defenseSchedules' => DefenseSchedule::where('team_id', $team->team_id)
                ->orderBy('schedule_date')->get(),
            'panel' => $this->panelFor($team),
            'activity' => $this->activityFor($row),
            'waiting' => $this->todo->forRow($row, $milestones, $portal),
            'viewerId' => (int) $viewer->expert_id,
            // Previous and Next walk the list in the order it is shown, and
            // wrap around, so she can go through every team without going back.
            'previousTeam' => $rows->count() > 1 ? $rows[($position - 1 + $rows->count()) % $rows->count()]->team : null,
            'nextTeam' => $rows->count() > 1 ? $rows[($position + 1) % $rows->count()]->team : null,
        ];
    }

    /**
     * The whole list behind a team page's "See all": every paper sent to her,
     * or every update. The team page itself only shows what fits.
     */
    public function log(Team $team, string $log): View
    {
        $row = $this->teamRow($team, false);

        return view('capsa.team-management.log', $this->logPage($row, $log, 'capsa'));
    }

    public function logPage(object $row, string $log, string $portal): array
    {
        return [
            'portal' => $portal,
            'row' => $row,
            'trail' => self::trailFor($row, $portal, [[$log === 'papers' ? 'Papers' : 'Activity', null]]),
            'log' => $log,
            'activity' => $log === 'activity' ? $this->activityFor($row) : collect(),
        ];
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
     * The row for a team on her list.
     *
     * Authorisation is the same question as "is this team on her list?", so
     * it is answered by her list rather than by a second set of rules that
     * could drift from it. The pages where she acts as CaPSA - titles and
     * their similar studies - also need the team to be in her sections, not
     * only one she advises.
     */
    private function teamRow(Team $team, bool $asCapsa = true): object
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();

        return $this->teamRows->forViewer($capsa, null, true, ! $asCapsa)
            ->first(fn ($row) => (int) $row->team->team_id === (int) $team->team_id && (! $asCapsa || $row->inSections))
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
        return self::trailFor($row, 'capsa', $below);
    }

    /**
     * @param  array<int, array{0: string, 1: ?string}>  $below
     * @return array<int, array{0: string, 1: ?string}>
     */
    public static function trailFor(object $row, string $portal, array $below = []): array
    {
        $capsa = $portal === 'capsa';
        $teamUrl = $below === [] ? null : route($capsa ? 'capsa.team_management.show' : 'instructor.teams.show', $row->team->team_id);

        return array_merge([
            $capsa
                ? ['Team Management', route('capsa.team_management', ['tab' => 'teams'])]
                : ['My Teams', route('instructor.teams', ['tab' => 'teams'])],
            [$row->team->team_name, $teamUrl],
        ], $below);
    }

    /**
     * The milestones list, GitHub style: open or finished, in the order picked.
     *
     * Finished is closed or hidden from teams. A milestone with no date sorts
     * after every dated one, the way an undated one is the least pressing.
     */
    private function milestoneList(Request $request, array $capsaSections): array
    {
        $state = in_array($request->query('state'), ['finished', 'closed'], true) ? 'finished' : 'open';
        $sort = array_key_exists((string) $request->query('sort'), self::MILESTONE_SORTS) ? $request->query('sort') : 'order';

        $boards = collect(app(ChecklistTracker::class)->milestoneList($capsaSections))
            ->map(function (array $board) use ($state, $sort) {
                [$finished, $open] = $board['milestones']->partition(fn ($row) => $row->milestone->isClosed() || $row->milestone->archived_at);
                $shown = $state === 'finished' ? $finished : $open;

                $board['open_count'] = $open->count();
                $board['finished_count'] = $finished->count();
                $board['order'] = MilestonePageController::orderOf($board['checklist']);
                $board['shown'] = (match ($sort) {
                    'percent' => $shown->sortByDesc('percent'),
                    'name' => $shown->sortBy(fn ($row) => mb_strtolower($row->milestone->name)),
                    'due' => $shown->sortBy(fn ($row) => $row->due_on?->toDateString() ?? '9999-12-31'),
                    default => $shown,
                })->values();

                return $board;
            });

        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();
        $mine = Checklist::where('capsa_id', $capsa->expert_id)->pluck('program');

        return [
            'boards' => $boards,
            'state' => $state,
            'sort' => $sort,
            'sorts' => self::MILESTONE_SORTS,
            // The programs she handles with no checklist of her own yet, each
            // with the sections of it she has.
            'setups' => collect($capsa->handledPrograms())
                ->diff($mine)
                ->mapWithKeys(fn (string $program) => [$program => collect($capsaSections)
                    ->filter(fn ($section) => TeamChecklist::programOf($section) === $program)
                    ->values()
                    ->all()])
                ->all(),
        ];
    }
}
