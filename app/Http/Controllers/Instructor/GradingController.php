<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\Team;
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

        // Get all panel assignments for this expert, with their schedules and teams
        $assignments = DefensePanel::where('expert_id', $expert->expert_id)
            ->with(['schedule.team'])
            ->get()
            // A panel is copied to every later stage when it is assigned; only
            // a defense with a date is one to grade.
            ->filter(fn ($panel) => $panel->schedule?->schedule_date !== null)
            ->sortBy(fn ($panel) => $panel->schedule->team?->team_name ?? '');

        // Group by team - for each team, find the "current" defense to grade
        // This is the latest attempt for the stage the team is currently at
        $teamsToGrade = $assignments->groupBy(fn ($panel) => $panel->schedule->team_id)->map(function ($panels) use ($expert) {
            // Get all schedules for this team that this expert is on
            $schedules = $panels->map(fn ($p) => $p->schedule)->filter()->unique('defense_id');

            // Find the current defense: the one with no outcome yet (in progress or scheduled)
            // If multiple, pick the one with the highest attempt for the current stage
            $current = $schedules->filter(fn ($s) => ! $s->hasOutcome())
                ->sortByDesc(fn ($s) => $s->attempt)
                ->first();

            // If all have outcomes, pick the latest one they haven't graded yet, or the latest
            if (! $current) {
                $current = $schedules->filter(function ($s) use ($expert) {
                    $grade = PanelGrade::where('defense_id', $s->defense_id)
                        ->where('expert_id', $expert->expert_id)
                        ->first();

                    return ! $grade || $grade->status !== 'submitted';
                })->sortByDesc(fn ($s) => $s->attempt)->first();
            }

            // If still none (all submitted and have outcomes), show the latest completed defense
            // so the expert can see their submitted grade and the outcome
            if (! $current) {
                $current = $schedules->sortByDesc(fn ($s) => $s->attempt)->first();
            }

            $grade = PanelGrade::where('defense_id', $current->defense_id)
                ->where('expert_id', $expert->expert_id)
                ->first();

            $panel = $panels->firstWhere('schedule.defense_id', $current->defense_id);

            // Count total attempts for this team at this defense type
            $attemptCount = $schedules->where('defense_type', $current->defense_type)->count();

            return (object) [
                'defense_id' => $current->defense_id,
                'team_id' => $current->team?->team_id ?? 0,
                'team_name' => $current->team?->team_name ?? 'Unknown Team',
                'project_title' => $current->team?->project_title,
                'defense_type' => $current->defense_type,
                'attempt' => max(1, (int) $current->attempt),
                'total_attempts' => $attemptCount,
                'outcome' => $current->hasOutcome() ? $current->outcomeLabel() : null,
                'outcome_tone' => DefenseOutcome::tone($current->outcome),
                'panel_role' => $panel->panel_role,
                'schedule_date' => $current->schedule_date,
                'end_date' => $current->end_date,
                'venue' => $current->venue,
                'status' => $grade?->status ?? 'not_started',
                'total_score' => $grade?->total_score,
            ];
        })->values();

        $defenseIds = $teamsToGrade->pluck('defense_id')->unique()->values();

        $myGrades = PanelGrade::where('expert_id', $expert->expert_id)
            ->whereIn('defense_id', $defenseIds)
            ->get()
            ->keyBy('defense_id');

        $rows = $teamsToGrade->map(function ($row) use ($myGrades) {
            $grade = $myGrades->get($row->defense_id);

            return (object) array_merge((array) $row, [
                'status' => $grade?->status ?? $row->status,
                'total_score' => $grade?->total_score ?? $row->total_score,
            ]);
        })
            // Still to grade first, then by date.
            ->sortBy(fn ($r) => ($r->status === 'submitted' ? '1' : '0').$r->schedule_date->format('YmdHi'))
            ->values();

        return view('instructor.grading', ['rows' => $rows]);
    }

    public function teamDefenses(Request $request): JsonResponse
    {
        $teamName = $request->query('team');

        if (! $teamName) {
            return response()->json(['success' => false, 'message' => 'Team name required'], 400);
        }

        /** @var CapstoneExpert $expert */
        $expert = auth()->guard('expert')->user();

        if (! $expert) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Find the team - case insensitive and trim
        $team = Team::whereRaw('TRIM(LOWER(team_name)) = ?', [strtolower(trim($teamName))])->first();

        if (! $team) {
            \Log::warning('Team not found for team-defenses', ['teamName' => $teamName, 'expert' => $expert->expert_id]);

            return response()->json(['success' => false, 'message' => 'Team not found'], 404);
        }

        // Check if expert is assigned to this team's panels
        $panelExists = DefensePanel::where('expert_id', $expert->expert_id)
            ->whereHas('schedule', fn ($q) => $q->where('team_id', $team->team_id))
            ->exists();

        if (! $panelExists) {
            \Log::warning('Expert not on panel for team', ['teamId' => $team->team_id, 'expert' => $expert->expert_id]);

            return response()->json(['success' => false, 'message' => 'Not authorized'], 403);
        }

        // Get all defense schedules for this team that this expert is on
        $defenses = DefenseSchedule::where('team_id', $team->team_id)
            ->whereHas('panels', fn ($q) => $q->where('expert_id', $expert->expert_id))
            ->with(['panels' => fn ($q) => $q->where('expert_id', $expert->expert_id), 'grades' => fn ($q) => $q->where('expert_id', $expert->expert_id)])
            ->orderBy('defense_type')
            ->orderBy('attempt')
            ->get();

        $defenseData = $defenses->map(function ($defense) use ($expert) {
            $panel = $defense->panels->firstWhere('expert_id', $expert->expert_id);
            $grade = $defense->grades->firstWhere('expert_id', $expert->expert_id);

            return [
                'defense_id' => $defense->defense_id,
                'defense_type' => $defense->defense_type,
                'attempt' => max(1, (int) $defense->attempt),
                'schedule_date' => $defense->schedule_date?->toIso8601String(),
                'venue' => $defense->venue,
                'status' => $grade?->status ?? 'not_started',
                'total_score' => $grade?->total_score,
                'panel_average' => $defense->panelAverage(),
                'outcome' => $defense->hasOutcome() ? $defense->outcomeLabel() : null,
                'panel_role' => $panel->panel_role ?? 'Panel Member',
                'url' => route('instructor.grading.show', $defense->defense_id),
            ];
        })->values();

        return response()->json(['success' => true, 'defenses' => $defenseData]);
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
        $panel = $this->panelFor($defense);

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
                // The chair is usually the last to score, so their own
                // submission is what completes the panel. Say so, and the
                // page keeps them here to record the decision instead of
                // sending them back to the team list to reopen this defense.
                'can_record_outcome' => $status === 'submitted'
                    && $panel->panel_role === 'Panel Chair'
                    && ! $defense->hasOutcome()
                    && $defense->allGradesIn(),
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
