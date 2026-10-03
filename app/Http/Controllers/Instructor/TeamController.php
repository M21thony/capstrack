<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Capsa\TeamManagementController;
use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\Team;
use App\Support\DefenseScores;
use App\Support\TeamRows;
use App\Support\TeamTodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * My Teams: an expert's one hub, the same To do and Teams a CaPSA has.
 *
 * Invitations (Team Assignments), papers sent to them (Document Review),
 * titles to approve and score sheets due all land in To do; the teams they
 * advise, consult for, check the grammar of or sit on a panel for are the
 * Teams list, each opening the same team page a CaPSA sees.
 */
class TeamController extends Controller
{
    public function __construct(
        private TeamRows $teamRows,
        private TeamTodo $todo,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        /** @var CapstoneExpert $expert */
        $expert = auth('expert')->user();

        // A CaPSA who is also an expert has one Teams page, not two.
        if ($expert->is_capsa) {
            return redirect()->route('capsa.team_management', ['tab' => $request->query('tab') === 'todo' ? 'todo' : 'teams']);
        }

        $tab = $request->query('tab') === 'teams' ? 'teams' : 'todo';
        $rows = $this->teamRows->forViewer($expert, null, false, true);
        $todo = $this->todo->forExpert($expert, $rows, 'expert');

        return view('instructor.teams', [
            'tab' => $tab,
            'portal' => 'expert',
            'rows' => $rows,
            'todo' => $todo,
            'decided' => $this->todo->decidedByExpert($expert),
            'twoHats' => false,
            'defenseScores' => DefenseScores::forTeams($rows->map(fn ($row) => $row->team->team_id)),
            'expert' => $expert,
            'totalTeams' => $rows->count(),
            'counts' => ['todo' => $todo->count(), 'teams' => $rows->count()],
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
