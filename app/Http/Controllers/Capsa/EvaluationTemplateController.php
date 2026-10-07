<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\EvaluationTemplate;
use App\Models\ExpertEvaluation;
use App\Support\EvaluationResults;
use App\Support\ExpertEvaluations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Expert Evaluations: the questions a CaPSA asks team leaders about their
 * experts after each defense, and what the answers add up to.
 *
 * An evaluation covers one defense stage and the roles the CaPSA picks;
 * every question is answered 1 to 5 and the expert's rating is the average.
 * Results are anonymous - no team or student is ever read for them.
 */
class EvaluationTemplateController extends Controller
{
    /** Enough for a thorough form, few enough that a leader finishes it. */
    public const MAX_QUESTIONS = 20;

    /** GET /capsa/evaluations - the evaluation per stage, or the results. */
    public function index(Request $request, ExpertEvaluations $evaluations): View
    {
        $capsa = $this->capsa();
        $tab = $request->query('tab') === 'results' ? 'results' : 'questions';

        $templates = EvaluationTemplate::with('questions')->withCount('evaluations')->withAvg('evaluations', 'rating')
            ->where('capsa_id', $capsa->expert_id)
            ->latest('id')
            ->get();

        $stage = in_array($request->query('stage'), EvaluationTemplate::STAGES, true) ? $request->query('stage') : null;
        $answered = ExpertEvaluation::with(['expert', 'defense'])
            ->whereHas('template', fn ($q) => $q->where('capsa_id', $capsa->expert_id))
            ->when($stage, fn ($q) => $q->whereHas('defense', fn ($d) => $d->where('defense_type', $stage)))
            ->get();

        $active = $templates->where('is_active', true)
            ->sortBy(fn (EvaluationTemplate $t) => ExpertEvaluations::stageOrder($t->defense_type))->values();

        return view('capsa.evaluations', [
            'tab' => $tab,
            // Only what the CaPSA made: the defense is theirs to pick.
            'active' => $active,
            'turnedOff' => $templates->where('is_active', false)->values(),
            'resultStages' => collect(EvaluationTemplate::STAGES)->filter(fn ($s) => $templates->contains('defense_type', $s))->values()->all(),
            'upcoming' => $evaluations->upcomingWithoutEvaluation($capsa)->keyBy('stage'),
            'results' => EvaluationResults::byExpert($answered),
            'resultStage' => $stage,
            'totalAnswered' => $answered->count(),
            'overall' => $answered->isEmpty() ? null : round((float) $answered->avg('rating'), 1),
        ]);
    }

    /** GET /capsa/evaluations/new - a blank evaluation, or a copy of one. */
    public function create(Request $request): View
    {
        $copy = $request->integer('from')
            ? EvaluationTemplate::with('questions')->where('capsa_id', $this->capsa()->expert_id)->find($request->integer('from'))
            : null;
        $stage = in_array($request->query('stage'), EvaluationTemplate::STAGES, true) ? $request->query('stage') : ($copy?->defense_type ?? EvaluationTemplate::STAGES[0]);

        return view('capsa.evaluation-form', [
            'template' => null,
            'title' => $copy?->title ?? '',
            'stage' => $stage,
            'roles' => $copy?->roles ?? array_keys(ExpertEvaluation::ROLES),
            'questions' => $copy ? $copy->questions->pluck('question')->all() : ($request->boolean('standard') ? EvaluationTemplate::STANDARD_QUESTIONS : ['']),
        ]);
    }

    /**
     * POST /capsa/evaluations - a new evaluation. With `standard`, the five
     * standard questions for every role, made in one click.
     */
    public function store(Request $request): RedirectResponse
    {
        $capsa = $this->capsa();

        if ($request->boolean('standard')) {
            $request->merge([
                'title' => $request->input('defense_type').' expert evaluation',
                'roles' => array_keys(ExpertEvaluation::ROLES),
                'questions' => EvaluationTemplate::STANDARD_QUESTIONS,
            ]);
        }

        $data = $this->validated($request, $capsa);

        DB::transaction(function () use ($capsa, $data) {
            $template = EvaluationTemplate::create([
                'capsa_id' => $capsa->expert_id,
                'title' => $data['title'],
                'defense_type' => $data['defense_type'],
                'roles' => $data['roles'],
                'is_active' => true,
            ]);
            $this->saveQuestions($template, $data['questions']);
        });

        return redirect()->route('capsa.evaluations')
            ->with('evaluation_status', 'Evaluation for '.$data['defense_type'].' is ready. Team leaders answer it after they pass.');
    }

    /** GET /capsa/evaluations/{template}/edit */
    public function edit(EvaluationTemplate $template): View
    {
        $this->own($template);

        return view('capsa.evaluation-form', [
            'template' => $template,
            'title' => $template->title,
            'stage' => $template->defense_type,
            'roles' => $template->roles ?? [],
            'questions' => $template->questions->pluck('question')->all(),
        ]);
    }

    /**
     * PUT /capsa/evaluations/{template} - its title, roles and questions.
     * The stage stays: a new stage would reach defenses the evaluation was
     * never set up for. Evaluations already sent keep the wording they were
     * answered with.
     */
    public function update(Request $request, EvaluationTemplate $template): RedirectResponse
    {
        $this->own($template);
        $request->merge(['defense_type' => $template->defense_type]);
        $data = $this->validated($request, $this->capsa(), $template);

        DB::transaction(function () use ($template, $data) {
            $template->update(['title' => $data['title'], 'roles' => $data['roles']]);
            $template->questions()->delete();
            $this->saveQuestions($template, $data['questions']);
        });

        return redirect()->route('capsa.evaluations')->with('evaluation_status', 'Saved "'.$data['title'].'".');
    }

    /**
     * PATCH /capsa/evaluations/{template}/turn-off - nobody owes it any more.
     * What was answered stays in the results.
     */
    public function turnOff(EvaluationTemplate $template): RedirectResponse
    {
        $this->own($template);
        $template->update(['is_active' => false]);

        return redirect()->route('capsa.evaluations')->with('evaluation_status', 'Turned off "'.$template->title.'". Its answers are still in the results.');
    }

    /**
     * DELETE /capsa/evaluations/{template} - a turned-off evaluation, gone
     * from the list. One nobody answered is erased; one leaders answered is
     * only hidden, so the expert's rating and the results do not change.
     */
    public function destroy(EvaluationTemplate $template): RedirectResponse
    {
        $this->own($template);

        if ($template->is_active) {
            return redirect()->route('capsa.evaluations')
                ->withErrors(['evaluation' => 'Turn "'.$template->title.'" off before deleting it.']);
        }

        $answered = $template->evaluations()->count();

        if ($answered > 0) {
            $template->delete();
        } else {
            DB::transaction(function () use ($template) {
                $template->questions()->delete();
                $template->forceDelete();
            });
        }

        return redirect()->route('capsa.evaluations')->with('evaluation_status', 'Deleted "'.$template->title.'".'
            .($answered > 0 ? ' Its '.$answered.' '.($answered === 1 ? 'answer stays' : 'answers stay').' in the results.' : ''));
    }

    /**
     * @return array{title: string, defense_type: string, roles: array<int, string>, questions: array<int, string>}
     */
    private function validated(Request $request, CapstoneExpert $capsa, ?EvaluationTemplate $editing = null): array
    {
        // Blank rows are the editor's spare lines, not questions.
        $request->merge([
            'questions' => collect((array) $request->input('questions', []))
                ->map(fn ($q) => trim((string) $q))->filter()->values()->all(),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'defense_type' => ['required', Rule::in(EvaluationTemplate::STAGES)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(array_keys(ExpertEvaluation::ROLES))],
            'questions' => ['required', 'array', 'min:1', 'max:'.self::MAX_QUESTIONS],
            'questions.*' => ['string', 'max:300', 'distinct'],
        ], [
            'roles.required' => 'Pick who this evaluation is about.',
            'questions.required' => 'Write at least one question.',
            'questions.max' => 'An evaluation has at most '.self::MAX_QUESTIONS.' questions.',
            'questions.*.max' => 'Keep each question under 300 characters.',
            'questions.*.distinct' => 'Two questions are the same.',
        ]);

        $data['roles'] = array_values(array_intersect(array_keys(ExpertEvaluation::ROLES), $data['roles']));

        // One evaluation per role and stage, so a leader is never asked twice.
        $clash = EvaluationTemplate::active()
            ->where('capsa_id', $capsa->expert_id)
            ->where('defense_type', $data['defense_type'])
            ->when($editing, fn ($q) => $q->whereKeyNot($editing->id))
            ->get()
            ->first(fn (EvaluationTemplate $t) => array_intersect($t->roles ?? [], $data['roles']) !== []);

        if ($clash) {
            $taken = collect(array_intersect($clash->roles ?? [], $data['roles']))->map(fn ($r) => ExpertEvaluation::ROLES[$r])->implode(' and ');

            throw ValidationException::withMessages([
                'roles' => $taken.' already '.(str_contains($taken, ' and ') ? 'have' : 'has').' an evaluation for '.$data['defense_type'].': "'.$clash->title.'". Edit that one, or turn it off first.',
            ]);
        }

        return $data;
    }

    /** @param  array<int, string>  $questions */
    private function saveQuestions(EvaluationTemplate $template, array $questions): void
    {
        foreach (array_values($questions) as $i => $question) {
            $template->questions()->create(['question' => $question, 'sort_order' => $i]);
        }
    }

    private function own(EvaluationTemplate $template): void
    {
        abort_unless((int) $template->capsa_id === (int) $this->capsa()->expert_id, 404);
    }

    private function capsa(): CapstoneExpert
    {
        return auth('expert')->user();
    }
}
