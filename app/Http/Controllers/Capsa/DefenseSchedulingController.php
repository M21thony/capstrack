<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\ExpertAwayDate;
use App\Support\DefenseBoard;
use App\Support\DefenseScheduleExport;
use App\Support\DefenseScheduleWorkbook;
use App\Support\LetterheadPdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defenses: one page for a team's panel and its defense dates. A team moves
 * along four steps - its panel made final, then what still holds it back,
 * then ready, then scheduled - for whichever stage is in view. Other
 * sections' defenses are on the calendar too, since their rooms and
 * panelists are taken.
 *
 * The page's actions call AdminPanelAssignmentController's and
 * DefenseCaseController's endpoints.
 */
class DefenseSchedulingController extends Controller
{
    /** Where a team is, for the stage in view - the page's steps. */
    private const STEPS = ['panel', 'ready', 'scheduled'];

    public function __construct(private DefenseBoard $board) {}

    /** Opens on the teams waiting for a date. */
    public function index(Request $request): View|Response
    {
        return $this->page($request, 'ready');
    }

    /** The old Panel Assignment address: opens on the panels. */
    public function panels(Request $request): View|Response
    {
        return $this->page($request, 'panel');
    }

    private function page(Request $request, string $step): View|Response
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();
        $stage = $this->board->openingStage($capsa, $request->query('defense_type'));

        // Same address as the page itself, the way Analytics' own CSV
        // download works: ?export=xlsx or ?export=pdf asks for the file
        // instead of the view (&inline=1 for the PDF window's preview).
        if (in_array($request->query('export'), ['xlsx', 'pdf'], true)) {
            return $this->export($capsa, $stage, $request->query('export'), $request->boolean('inline'));
        }

        $rows = $this->board->rows($capsa, $stage);
        $other = $this->board->otherBookings(array_column($rows, 'id'));

        return view('capsa.defenses', [
            'state' => [
                'step' => in_array($request->query('step'), self::STEPS, true) ? $request->query('step') : $step,
                'today' => today()->toDateString(),
                'stage' => $stage,
                'stages' => DefenseBoard::STAGES,
                'teams' => $rows,
                'experts' => $this->board->panelists()->values(),
                'strengths' => CapstoneExpert::STRENGTHS,
                'names' => DefenseBoard::names($rows, $other),
                'sections' => $capsa->sections ?? [],
                'other' => $other,
                'windows' => $this->board->windows($capsa->windowProgram()),
                'has_rubric' => DefenseBoard::hasRubric($stage, $capsa->handledPrograms()),
                'rubric_missing_programs' => DefenseBoard::programsWithoutRubric($stage, $capsa->handledPrograms()),
                'slots' => DefenseBoard::slots(DefenseBoard::minutesFor($stage, $capsa->windowProgram())),
                'venues' => DefenseBoard::VENUES,
                'away' => $this->board->awayDates(),
                'away_reasons' => ExpertAwayDate::REASONS,
                'progress' => $this->board->stageProgress($capsa),
            ],
        ]);
    }

    /**
     * The schedule as a spreadsheet, in the department's own layout (see
     * DefenseScheduleWorkbook), or as the same rows on the school's
     * letterhead in a PDF - the one Export shows first. Scoped the same way
     * the page is: a CaPSA/TSA gets her own sections, the Admin gets every team.
     */
    private function export(CapstoneExpert $capsa, string $stage, string $format, bool $inline): Response
    {
        $rows = app(DefenseScheduleExport::class)->rows($capsa, $stage);
        $scope = $capsa->sections ? implode(', ', $capsa->sections) : 'All sections';
        $window = $this->board->windows($capsa->windowProgram())[$stage] ?? null;
        $name = str_replace(' ', '_', $stage).'_'.now()->format('Y-m-d');

        if ($format === 'pdf') {
            // Legal paper: eleven columns, three of them lists of names.
            return LetterheadPdf::response('capsa.defenses-pdf', compact('rows', 'stage', 'scope', 'window'), $name.'.pdf', $inline, 'legal');
        }

        $book = (new DefenseScheduleWorkbook)->build($rows, $stage, $scope, $window);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $name.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
