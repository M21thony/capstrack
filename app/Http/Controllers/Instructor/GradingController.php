<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Services\DefenseOutcomeRecorder;
use App\Support\DefenseOutcome;
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
            // A panel is copied to every later stage when it is assigned; only
            // a defense with a date is one to grade.
            ->filter(fn ($panel) => $panel->schedule?->schedule_date !== null)
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
                'attempt' => max(1, (int) $schedule->attempt),
                'outcome' => $schedule->hasOutcome() ? $schedule->outcomeLabel() : null,
                'panel_role' => $panel->panel_role,
                'schedule_date' => $schedule->schedule_date,
                'end_date' => $schedule->end_date,
                'venue' => $schedule->venue,
                'status' => $grade?->status ?? 'not_started',
                'total_score' => $grade?->total_score,
            ];
        })
            // Still to grade first, then by date.
            ->sortBy(fn ($r) => ($r->status === 'submitted' ? '1' : '0').$r->schedule_date->format('YmdHi'))
            ->values();

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

        $defense->load('panels', 'outcomeRecordedBy');

        return view('instructor.grading-show', [
            'defense' => $defense,
            'rubric' => $rubric,
            'grade' => $grade,
            'panelRole' => $panel->panel_role,
            // The panel confers and then gives the team one result, so the
            // verdict is recorded once, by the chair - not collected from
            // each panelist and tallied. Everyone else just scores.
            'isChair' => $panel->panel_role === 'Panel Chair',
            'outcomeOptions' => DefenseOutcome::forStage($defense->defense_type),
            'gradesIn' => $defense->submittedGrades()->count(),
            'panelSize' => $defense->panelSize(),
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
            // Optional now, and kept only as a panelist's own note to
            // themselves before the briefing. The decision that counts is the
            // panel's, recorded once by the chair - see recordOutcome().
            'verdict' => ['nullable', 'string', 'in:'.implode(',', self::VERDICTS)],
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

    /**
     * Record what the panel decided.
     *
     * Chair only, and only once every panelist has scored: the outcome is
     * the conclusion of the briefing, and a briefing cannot have happened
     * while someone still has not marked the rubric.
     */
    public function recordOutcome(Request $request, DefenseSchedule $defense, DefenseOutcomeRecorder $recorder): JsonResponse
    {
        $panel = $this->panelFor($defense);

        if ($panel->panel_role !== 'Panel Chair') {
            return response()->json([
                'success' => false,
                'message' => 'Only the Panel Chair records the panel\'s decision.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'outcome' => ['required', 'string'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $validated = $validator->validated();

        // The options depend on the stage - only a pre-proposal can be sent
        // back for a whole new title.
        if (! DefenseOutcome::allowedForStage($defense->defense_type, $validated['outcome'])) {
            return response()->json([
                'success' => false,
                'message' => 'That is not a possible outcome for a '.$defense->defense_type.'.',
            ], 422);
        }

        if ($defense->hasOutcome()) {
            return response()->json([
                'success' => false,
                'message' => 'This defense already has a recorded outcome.',
            ], 422);
        }

        if (! $defense->allGradesIn()) {
            return response()->json([
                'success' => false,
                'message' => 'Every panelist has to submit their scores before the outcome can be recorded.',
            ], 422);
        }

        /** @var CapstoneExpert $chair */
        $chair = auth()->guard('expert')->user();

        $result = $recorder->record($defense, $validated['outcome'], $chair, $validated['remarks'] ?? null);

        $message = 'Outcome recorded: '.DefenseOutcome::label($validated['outcome'], $defense->defense_type).'.';

        if ($result['redefense']) {
            $message .= ' A redefense has been opened for this team - the CAPSA schedules it from Panel Assignment.';
        }

        if ($result['reopened_proposals']) {
            $message .= ' Their pre-proposals have been reopened so they can submit a new title.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'outcome' => $result['schedule']->outcome,
                'outcome_label' => $result['schedule']->outcomeLabel(),
                'redefense_attempt' => $result['redefense']?->attempt,
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
        // An archived rubric is out of use, whatever its active flag says.
        return GradingRubric::whereRaw('LOWER(rubric_type) = ?', [mb_strtolower($defenseType)])
            ->where('is_active', true)
            ->where('is_archived', false)
            ->latest('rubric_id')
            ->first();
    }
}
