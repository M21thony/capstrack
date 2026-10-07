<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExpertEvaluation;
use App\Models\StudentSchoolYear;
use App\Support\ExpertEvaluations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The leader answering the CaPSA's evaluation of one of the team's experts
 * after a passed defense.
 */
class ExpertEvaluationController extends Controller
{
    public function store(Request $request, ExpertEvaluations $evaluations): RedirectResponse
    {
        $data = $request->validate([
            'defense_id' => ['required', 'integer'],
            'expert_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(array_keys(ExpertEvaluation::ROLES))],
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'integer', 'between:1,5'],
        ], [
            'answers.required' => 'Answer every question from 1 to 5.',
            'answers.*.between' => 'Answer every question from 1 to 5.',
        ]);

        $student = Auth::user();
        $team = StudentSchoolYear::where('student_id', $student->student_id)
            ->whereNotNull('team_id')
            ->first()?->team;

        $back = route('student.experts', ['tab' => 'evaluations']);

        if (! $team) {
            return redirect($back)->with('evaluation_error', 'You need a team before you can evaluate experts.');
        }

        $refused = $evaluations->submit(
            $student,
            $team,
            (int) $data['defense_id'],
            (int) $data['expert_id'],
            $data['role'],
            $data['answers'],
        );

        if ($refused) {
            return redirect($back)->with('evaluation_error', $refused)->withInput();
        }

        return redirect($back)->with('evaluation_saved', 'Evaluation sent. Thank you!');
    }
}
