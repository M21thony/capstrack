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
 * The leader answering the CaPSA/TSA's evaluation of one of the team's experts
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

    /**
     * Every expert of one passed defense in one go: each goes through the
     * same submit() as store(). One refused does not hold the others back -
     * it stays owed, and its answers come back to fix.
     */
    public function storeAll(Request $request, ExpertEvaluations $evaluations): RedirectResponse
    {
        $data = $request->validate([
            'defense_id' => ['required', 'integer'],
            'evaluations' => ['required', 'array'],
            'evaluations.*.expert_id' => ['required', 'integer'],
            'evaluations.*.role' => ['required', Rule::in(array_keys(ExpertEvaluation::ROLES))],
            'evaluations.*.answers' => ['required', 'array'],
            'evaluations.*.answers.*' => ['required', 'integer', 'between:1,5'],
        ], [
            'evaluations.*.answers.required' => 'Answer every question from 1 to 5.',
            'evaluations.*.answers.*.between' => 'Answer every question from 1 to 5.',
        ]);

        $student = Auth::user();
        $team = StudentSchoolYear::where('student_id', $student->student_id)
            ->whereNotNull('team_id')
            ->first()?->team;

        $back = route('student.experts', ['tab' => 'evaluations']);

        if (! $team) {
            return redirect($back)->with('evaluation_error', 'You need a team before you can evaluate experts.');
        }

        $sent = 0;
        $refusals = [];
        foreach ($data['evaluations'] as $row) {
            $refused = $evaluations->submit($student, $team, (int) $data['defense_id'], (int) $row['expert_id'], $row['role'], $row['answers']);
            $refused ? $refusals[] = $refused : $sent++;
        }

        if ($refusals) {
            $note = $sent ? $sent.' sent. ' : '';

            return redirect($back)->with('evaluation_error', $note.implode(' ', array_unique($refusals)))->withInput();
        }

        return redirect($back)->with('evaluation_saved', ($sent === 1 ? 'Evaluation' : $sent.' evaluations').' sent. Thank you!');
    }
}
