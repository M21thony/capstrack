<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Services\SharedGrading;
use App\Support\TeamRows;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Team Management's Grades tab: the panel grades on paper, and the section's
 * CaPSA types each sheet's total and keeps the signed sheets as one PDF.
 *
 * A total goes in once per record - one per panelist graded on their own, one
 * per shared grade (the grader's row, copied onto everyone joined to it). A
 * seat its panelist turned in themselves is never overwritten. The Panel Chair
 * still records the decision once every grade is in.
 */
class GradesController extends Controller
{
    public function __construct(private SharedGrading $shared, private TeamRows $teamRows) {}

    /** POST /capsa/grades/{defense} - save the typed totals, as drafts or submitted. */
    public function encode(Request $request, DefenseSchedule $defense): JsonResponse
    {
        $capsa = $this->guard($defense);

        if ($defense->hasOutcome()) {
            return response()->json(['success' => false, 'message' => 'The panel has already recorded its decision for this defense.'], 422);
        }

        $rubric = GradingRubric::forStage($defense->defense_type, $defense->team?->program());

        if (! $rubric) {
            return response()->json(['success' => false, 'message' => 'No active rubric is configured for this defense stage.'], 422);
        }

        $max = (float) $rubric->total_points;
        $validator = Validator::make($request->all(), [
            'totals' => ['required', 'array'],
            'totals.*' => ['nullable', 'numeric', 'min:0', 'max:'.$max],
            'submit' => ['boolean'],
        ], [
            'totals.*.max' => 'A total cannot be more than the rubric\'s '.$max.'.',
            'totals.*.numeric' => 'Totals have to be numbers.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $totals = $validator->validated()['totals'];
        $submit = $request->boolean('submit');
        $open = collect(self::records($defense))->where('own', false);

        if ($submit && $open->contains(fn ($r) => ($totals[$r['id']] ?? null) === null)) {
            return response()->json(['success' => false, 'message' => 'Type every total before submitting.'], 422);
        }

        $saved = 0;

        foreach ($open as $record) {
            $value = $totals[$record['id']] ?? null;

            // A blank in a draft leaves what is there.
            if ($value === null) {
                continue;
            }

            $grade = PanelGrade::updateOrCreate(
                ['defense_id' => $defense->defense_id, 'expert_id' => $record['id']],
                [
                    'graded_by_expert_id' => null,
                    'joined_at' => null,
                    'joined_set_by' => null,
                    'rubric_id' => $rubric->rubric_id,
                    'scores' => [],
                    'total_score' => (float) $value,
                    'verdict' => null,
                    'remarks' => PanelGrade::FROM_PAPER.$capsa->name.' (CaPSA).',
                    'status' => $submit ? PanelGrade::SUBMITTED : PanelGrade::DRAFT,
                    'submitted_at' => $submit ? now() : null,
                ]
            );
            // Everyone joined to this grader gets the same total.
            $this->shared->sync($grade);
            $saved++;
        }

        return response()->json([
            'success' => true,
            'message' => $submit ? 'Grades submitted for '.($defense->team?->team_name ?? 'the team').'.' : 'Drafts saved.',
            'data' => ['saved' => $saved],
        ]);
    }

    /** POST /capsa/grades/{defense}/sheet - attach (or replace) the signed sheets PDF. */
    public function uploadSheet(Request $request, DefenseSchedule $defense): JsonResponse
    {
        $this->guard($defense);

        $validator = Validator::make($request->all(), [
            'sheet' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'sheet.mimes' => 'The signed sheets have to be one PDF.',
            'sheet.max' => 'The PDF cannot be bigger than 20 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $old = $defense->signed_sheets_path;
        $defense->update(['signed_sheets_path' => Storage::disk('local')->putFile('signed-sheets', $request->file('sheet'))]);

        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return response()->json([
            'success' => true,
            'message' => 'Signed sheets attached.',
            'data' => ['url' => route('capsa.grades.sheet', $defense->defense_id)],
        ]);
    }

    /** GET /capsa/grades/{defense}/sheet - the signed sheets PDF. */
    public function sheet(DefenseSchedule $defense): Response
    {
        $this->guard($defense);
        abort_unless($defense->signed_sheets_path && Storage::disk('local')->exists($defense->signed_sheets_path), 404);

        return Storage::disk('local')->response($defense->signed_sheets_path, null, ['Content-Type' => 'application/pdf']);
    }

    /**
     * One record per sheet to type: a shared grade (its grader's row, naming
     * everyone it counts for) or a panelist on their own. `own` when the
     * panelist turned it in themselves, which CaPSA sees but does not type.
     *
     * @return list<array<string, mixed>>
     */
    public static function records(DefenseSchedule $defense): array
    {
        $panels = $defense->panels()->with('expert')->get()
            ->sortBy(fn ($p) => ($p->panel_role === 'Panel Chair' ? '0' : '1').$p->panel_role)
            ->keyBy(fn ($p) => (int) $p->expert_id);
        $grades = $defense->grades()->get()->keyBy(fn (PanelGrade $g) => (int) $g->expert_id);
        $records = [];

        foreach ($panels as $id => $panel) {
            $grade = $grades->get($id);

            if ($grade?->isJoined()) {
                continue;
            }

            $seats = collect([$panel])->merge(
                $grades->filter(fn (PanelGrade $g) => (int) $g->graded_by_expert_id === $id)->keys()
                    ->map(fn ($j) => $panels->get($j))->filter()
            );

            $records[] = [
                'id' => $id,
                'roles' => $seats->pluck('panel_role')->values()->all(),
                'names' => $seats->map(fn ($p) => $p->expert?->name ?? $p->panel_role)->values()->all(),
                'own' => $grade && ! $grade->isFromPaper()
                    && in_array($grade->status, [PanelGrade::SUBMITTED, PanelGrade::ABSTAINED], true),
                'abstained' => (bool) $grade?->isAbstained(),
                'status' => $grade?->status,
                'total' => $grade?->total_score !== null ? (float) $grade->total_score : null,
            ];
        }

        return $records;
    }

    /** The defense's team has to be in one of this CaPSA's sections. */
    private function guard(DefenseSchedule $defense): CapstoneExpert
    {
        /** @var CapstoneExpert $capsa */
        $capsa = auth('expert')->user();

        $mine = $this->teamRows->forViewer($capsa, null, true, false)
            ->contains(fn ($row) => $row->inSections && (int) $row->team->team_id === (int) $defense->team_id);

        abort_unless($mine, 403, 'This team is not in your sections.');

        return $capsa;
    }
}
