<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\EvaluationTemplate;
use App\Models\ExpertEvaluation;
use App\Support\EvaluationResults;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * My Evaluations: what team leaders answered about this expert after each
 * defense - the average, how many, per question and per response. Nobody is
 * named: the team and the student are never read for this page.
 */
class EvaluationController extends Controller
{
    /** GET /instructor/evaluations */
    public function index(Request $request): View
    {
        /** @var CapstoneExpert $expert */
        $expert = auth('expert')->user();
        $stage = in_array($request->query('stage'), EvaluationTemplate::STAGES, true) ? $request->query('stage') : null;

        $all = ExpertEvaluation::with('defense')->where('expert_id', $expert->expert_id)->get();
        $shown = $stage ? $all->filter(fn ($e) => $e->defense?->defense_type === $stage)->values() : $all;

        // Only the defenses this expert was actually evaluated after.
        $evaluatedStages = collect(EvaluationTemplate::STAGES)->filter(fn ($s) => $all->contains(fn ($e) => $e->defense?->defense_type === $s))->values();

        return view('instructor.evaluations', [
            'stages' => $evaluatedStages->all(),
            'stage' => $stage,
            'average' => $shown->isEmpty() ? null : round((float) $shown->avg('rating'), 1),
            'total' => $shown->count(),
            // How the five stars split, for the bar beside the average.
            'distribution' => collect(range(5, 1))->mapWithKeys(fn ($s) => [$s => $shown->filter(fn ($e) => (int) round($e->rating) === $s)->count()]),
            'byStage' => $evaluatedStages->mapWithKeys(fn ($s) => [$s => $all->filter(fn ($e) => $e->defense?->defense_type === $s)])
                ->map(fn ($rows) => ['average' => $rows->isEmpty() ? null : round((float) $rows->avg('rating'), 1), 'total' => $rows->count()]),
            'questions' => EvaluationResults::byQuestion($shown),
            'responses' => EvaluationResults::responses($shown),
        ]);
    }
}
