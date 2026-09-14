<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Support\DefenseBoard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Defense Scheduling: which of the CaPSA's teams can be scheduled for a
 * stage, which cannot and why, and what is already on the calendar - other
 * sections' defenses included, since their rooms and panelists are taken too.
 *
 * The page's actions (the window, Generate schedule, moving one defense) call
 * AdminPanelAssignmentController's scheduling endpoints.
 */
class DefenseSchedulingController extends Controller
{
    public function __construct(private DefenseBoard $board) {}

    public function index(Request $request): View
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();
        $stage = DefenseBoard::stageOrDefault($request->query('defense_type'));
        $rows = $this->board->rows($capsa, $stage);
        $other = $this->board->otherBookings(array_column($rows, 'id'));

        return view('capsa.defense_scheduling', [
            'state' => [
                'stage' => $stage,
                'stages' => DefenseBoard::STAGES,
                'teams' => $rows,
                'experts' => $this->board->panelists()->values(),
                'names' => DefenseBoard::names($rows, $other),
                'sections' => $capsa->sections ?? [],
                'other' => $other,
                'windows' => $this->board->windows(),
                'has_rubric' => DefenseBoard::hasRubric($stage),
                'slots' => DefenseBoard::TIME_SLOTS,
                'venues' => DefenseBoard::VENUES,
            ],
        ]);
    }
}
