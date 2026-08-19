<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class GradingController extends Controller
{
    private const VERDICTS = ['approved', 'revision', 'rejected'];

    // Who a panelist can grade comes from defense_panels (the real,
    // admin-driven panel assignment), not a "panel" role — no such role
    // has ever been seeded, so gating on it always denied everyone.
    public function index(): View
    {
        /** @var CapstoneExpert $expert */
        $expert = auth()->guard('expert')->user();

        $assignments = DefensePanel::where('expert_id', $expert->expert_id)
            ->with(['schedule.team'])
            ->get()
            ->filter(fn ($panel) => $panel->schedule !== null)
            ->sortBy(fn ($panel) => $panel->schedule->team?->team_name ?? '');

        $defenseIds = $assignments->pluck('schedule.defense_id')->unique()->values();

        $myGrades = PanelGrade::where('expert_id', $expert->expert_id)
            ->whereIn('defense_id', $defenseIds)
            ->get()
            ->keyBy('defense_id');

        $rows = $assignments->map(function (DefensePanel $panel) use ($myGrades) {
            $schedule = $panel->schedule;
            $grade = $myGrades->get($schedule->defense_id);

            return (object) [
                'defense_id' => $schedule->defense_id,
                'team_name' => $schedule->team?->team_name ?? 'Unknown Team',
                'project_title' => $schedule->team?->project_title,
                'defense_type' => $schedule->defense_type,
                'panel_role' => $panel->panel_role,
                'schedule_date' => $schedule->schedule_date,
                'end_date' => $schedule->end_date,
                'venue' => $schedule->venue,
                'status' => $grade?->status ?? 'not_started',
                'total_score' => $grade?->total_score,
            ];
        })->values();

        return view('instructor.grading', ['rows' => $rows]);
    }

    public function show(DefenseSchedule $defense): View|RedirectResponse
    {
        $panel = $this->panelFor($defense);

        $rubric = $this->rubricFor($defense->defense_type);

        if (! $rubric) {
            return redirect()->route('instructor.grading')->with(
                'error',
                "No active rubric is configured for \"{$defense->defense_type}\" yet. Ask an admin to set one up under Grading Rubrics."
            );
        }

        $rubric->load(['criteria' => fn ($q) => $q->orderBy('sort_order')]);

        /** @var CapstoneExpert $expert */
        $expert = auth()->guard('expert')->user();

        $grade = PanelGrade::where('defense_id', $defense->defense_id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        $defense->load('team');

        return view('instructor.grading-show', [
            'defense' => $defense,
            'rubric' => $rubric,
            'grade' => $grade,
            'panelRole' => $panel->panel_role,
        ]);
    }

    public function save(Request $request, DefenseSchedule $defense): JsonResponse
    {
        return $this->persist($request, $defense, 'draft');
    }

    public function submit(Request $request, DefenseSchedule $defense): JsonResponse
    {
        return $this->persist($request, $defense, 'submitted');
    }

    private function persist(Request $request, DefenseSchedule $defense, string $status): JsonResponse
    {
        $this->panelFor($defense);

        $rubric = $this->rubricFor($defense->defense_type);

        if (! $rubric) {
            return response()->json([
                'success' => false,
                'message' => 'No active rubric is configured for this defense stage.',
            ], 422);
        }

        $rubric->load('criteria');

        // request()->validate() would try to redirect back on failure here -
        // this app's exception config only auto-renders JSON for api/*
        // routes (bootstrap/app.php's shouldRenderJsonWhen), and this is an
        // instructor/ route hit via fetch. Validate manually so a failure
        // returns JSON instead of a redirect the JS isn't expecting.
        $validator = Validator::make($request->all(), [
            'scores' => ['nullable', 'array'],
            'scores.*' => ['nullable', 'numeric'],
            'verdict' => [$status === 'submitted' ? 'required' : 'nullable', 'string', 'in:'.implode(',', self::VERDICTS)],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();
        $rawScores = $validated['scores'] ?? [];
        $cleanScores = [];
        $total = 0.0;

        foreach ($rubric->criteria as $criterion) {
            $given = $rawScores[$criterion->criterion_id] ?? null;

            if ($given === null || $given === '') {
                continue;
            }

            // Never trust the submitted score at face value — it must
            // match one of this criterion's own performance-level values,
            // otherwise a tampered request could hand a criterion more
            // than its ceiling allows.
            $allowedScores = collect($criterion->decodedScoreLevels())->pluck('score')->map(fn ($s) => (float) $s);

            if (! $allowedScores->contains(fn ($s) => abs($s - (float) $given) < 0.001)) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid score for \"{$criterion->criterion_name}\".",
                ], 422);
            }

            $cleanScores[$criterion->criterion_id] = (float) $given;
            $total += (float) $given;
        }

        if ($status === 'submitted' && count($cleanScores) < $rubric->criteria->count()) {
            return response()->json([
                'success' => false,
                'message' => 'Please score every criterion before submitting.',
            ], 422);
        }

        /** @var CapstoneExpert $expert */
        $expert = auth()->guard('expert')->user();

        $grade = PanelGrade::updateOrCreate(
            ['defense_id' => $defense->defense_id, 'expert_id' => $expert->expert_id],
            [
                'rubric_id' => $rubric->rubric_id,
                'scores' => $cleanScores,
                'total_score' => count($cleanScores) > 0 ? $total : null,
                'verdict' => $validated['verdict'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => $status,
                'submitted_at' => $status === 'submitted' ? now() : null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $status === 'submitted' ? 'Grade submitted successfully.' : 'Draft saved.',
            'data' => [
                'status' => $grade->status,
                'total_score' => $grade->total_score !== null ? (float) $grade->total_score : null,
            ],
        ]);
    }

    private function panelFor(DefenseSchedule $defense): DefensePanel
    {
        /** @var CapstoneExpert $expert */
        $expert = auth()->guard('expert')->user();

        $panel = DefensePanel::where('defense_id', $defense->defense_id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $panel) {
            abort(403, "You are not assigned to this team's panel.");
        }

        return $panel;
    }

    // defense_schedule.defense_type and grading_rubrics.rubric_type were
    // built by two different features that never coordinated their exact
    // wording (e.g. "Pre-proposal Defense" vs "Pre-Proposal Defense") —
    // matched case-insensitively rather than relying on them being
    // byte-identical.
    private function rubricFor(string $defenseType): ?GradingRubric
    {
        return GradingRubric::whereRaw('LOWER(rubric_type) = ?', [mb_strtolower($defenseType)])
            ->where('is_active', true)
            ->first();
    }
}
