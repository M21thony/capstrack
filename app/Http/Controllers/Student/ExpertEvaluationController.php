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
 * The leader rating one of the team's experts for a finished phase.
 */
class ExpertEvaluationController extends Controller
{
    public function store(Request $request, ExpertEvaluations $evaluations): RedirectResponse
    {
        $data = $request->validate([
            'milestone_id' => ['required', 'integer'],
            'expert_id' => ['required', 'integer'],
            'role' => ['required', Rule::in(array_keys(ExpertEvaluation::ROLES))],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'rating.required' => 'Pick from 1 to 5 stars.',
            'rating.between' => 'Pick from 1 to 5 stars.',
            'comment.required' => 'Add a short comment about this expert.',
            'comment.min' => 'Add a short comment about this expert.',
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
            (int) $data['milestone_id'],
            (int) $data['expert_id'],
            $data['role'],
            (int) $data['rating'],
            $data['comment'],
        );

        if ($refused) {
            return redirect($back)->with('evaluation_error', $refused);
        }

        return redirect($back)->with('evaluation_saved', 'Evaluation sent. Thank you!');
    }
}
