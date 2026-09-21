<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\DefenseWindow;
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
        $stage = $this->board->openingStage($capsa, $request->query('defense_type'));
        $rows = $this->board->rows($capsa, $stage);
        $other = $this->board->otherBookings(array_column($rows, 'id'));
        $windows = $this->board->windows();

        return view('capsa.defense_scheduling', [
            'state' => [
                'stage' => $stage,
                'stages' => DefenseBoard::STAGES,
                'teams' => $rows,
                'experts' => $this->board->panelists()->values(),
                'names' => DefenseBoard::names($rows, $other),
                'sections' => $capsa->sections ?? [],
                'other' => $other,
                'windows' => $windows,
                'has_rubric' => DefenseBoard::hasRubric($stage),
                // What the Change dialog offers: this stage's own times, from its window.
                'slots' => $windows[$stage]['slots'],
                'session_minutes' => $windows[$stage]['duration'],
                // The defense day, for previewing what a length will do to it.
                'day' => ['start' => DefenseWindow::DAY_START, 'end' => DefenseWindow::DAY_END],
                'venues' => DefenseBoard::VENUES,
            ],
        ]);
    }
}
