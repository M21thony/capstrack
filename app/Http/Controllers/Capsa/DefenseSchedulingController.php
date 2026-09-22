<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Support\DefenseBoard;
use App\Support\DefenseScheduleExport;
use App\Support\DefenseScheduleWorkbook;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function index(Request $request): View|Response
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();
        $stage = $this->board->openingStage($capsa, $request->query('defense_type'));

        // Same address as the page itself, the way Analytics' own CSV
        // download works: ?export=xlsx asks for the file instead of the view.
        if ($request->query('export') === 'xlsx') {
            return $this->export($capsa, $stage);
        }

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
                'slots' => DefenseBoard::slots(DefenseBoard::minutesFor($stage)),
                'venues' => DefenseBoard::VENUES,
            ],
        ]);
    }

    /**
     * The schedule as a spreadsheet, in the department's own layout (see
     * DefenseScheduleWorkbook). Scoped the same way the page is: a CaPSA gets
     * her own sections, the Admin gets every team.
     */
    private function export(CapstoneExpert $capsa, string $stage): StreamedResponse
    {
        $rows = app(DefenseScheduleExport::class)->rows($capsa, $stage);
        $scope = $capsa->sections ? implode(', ', $capsa->sections) : 'All sections';
        $book = (new DefenseScheduleWorkbook)->build($rows, $stage, $scope);

        $filename = str_replace(' ', '_', $stage).'_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
