<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Capsa\TeamManagementController;
use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\Team;
use App\Support\DefenseScores;
use App\Support\TeamApplications;
use App\Support\TeamRows;
use App\Support\TeamTodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Teams: an expert's one hub, the same To do, Team Applications and Teams a
 * CaPSA has.
 *
 * To do is the shortcut to everything waiting on them: team applications,
 * papers sent to them (Document Review), titles to confirm, defenses waiting
 * for their go and score sheets due. Team Applications is the whole list of
 * teams asking for them; Teams is every team they advise, consult for, check
 * the grammar of or sit on a panel for, each opening the same team page a
 * CaPSA sees.
 */
class TeamController extends Controller
{
    /** The tabs along the top. The first is the default. */
    public const TABS = ['todo', 'applications', 'teams'];

    public function __construct(
        private TeamRows $teamRows,
        private TeamTodo $todo,
        private TeamApplications $applications,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        /** @var CapstoneExpert $expert */
        $expert = auth('expert')->user();

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'todo';

        // A CaPSA who is also an expert has one Teams page, not two: the tab
        // asked for, or her teams.
        if ($expert->is_capsa) {
            return redirect()->route('capsa.team_management', [
                'tab' => in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'teams',
            ]);
        }

        $rows = $this->teamRows->forViewer($expert, null, false, true);
        $todo = $this->todo->forExpert($expert, $rows, 'expert');
        $applications = $this->applications->forExpert($expert);

        return view('instructor.teams', [
            'tab' => $tab,
            'portal' => 'expert',
            'rows' => $rows,
            'todo' => $todo,
            'decided' => $this->todo->decidedByExpert($expert, $rows, 'expert'),
            'twoHats' => false,
            'defenseScores' => DefenseScores::forTeams($rows->map(fn ($row) => $row->team->team_id)),
            'applications' => $applications,
            'expert' => $expert,
            'totalTeams' => $rows->count(),
            'counts' => ['todo' => TeamTodo::waiting($todo)->count(), 'applications' => $applications['waiting']->count(), 'teams' => $rows->count()],
        ]);
    }

    /** One team, the same page a CaPSA sees, from the expert's seat. */
    public function show(Request $request, Team $team): View|RedirectResponse
    {
        /** @var CapstoneExpert $expert */
        $expert = auth('expert')->user();

        if ($expert->is_capsa) {
            return redirect()->route('capsa.team_management.show', [$team->team_id] + $request->query());
        }

        $rows = $this->teamRows->forViewer($expert, null, false, true);

        return view('capsa.team-management.show', app(TeamManagementController::class)
            ->teamPage($request, $team, $rows, $expert, 'expert') + ['totalTeams' => $rows->count()]);
    }

    /** Everything behind a team page's "See all". */
    public function log(Team $team, string $log): View
    {
        /** @var CapstoneExpert $expert */
        $expert = auth('expert')->user();
        $rows = $this->teamRows->forViewer($expert, null, false, true);
        $row = $rows->first(fn ($row) => (int) $row->team->team_id === (int) $team->team_id) ?? abort(404);

        return view('capsa.team-management.log', app(TeamManagementController::class)
            ->logPage($row, $log, 'expert') + ['totalTeams' => $rows->count()]);
    }
}
