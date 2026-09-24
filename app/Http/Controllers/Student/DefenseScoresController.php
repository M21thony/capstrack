<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DefenseSchedule;
use App\Models\PanelGrade;
use App\Models\StudentSchoolYear;
use App\Support\DefenseProgress;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Every defense the team has sat whose result is out, with each panelist's
 * copy of the rubric: what they gave for every criterion, and their remarks.
 */
class DefenseScoresController extends Controller
{
    public function index(): View
    {
        $teamId = StudentSchoolYear::where('student_id', Auth::id())->whereNotNull('team_id')->value('team_id');
        $order = array_flip(DefenseProgress::STAGES);

        // Only once the panel has recorded its decision: until then the
        // scores are still the panel's to confer over.
        $defenses = $teamId
            ? DefenseSchedule::where('team_id', $teamId)
                ->whereNotNull('outcome')
                // Abstentions too, so the sheet can say who abstained rather
                // than leave that panelist's column looking unfinished.
                ->with(['panels.expert', 'grades' => fn ($q) => $q->whereIn('status', [PanelGrade::SUBMITTED, PanelGrade::ABSTAINED])->with('rubric.criteria')])
                ->get()
                ->sortBy(fn ($d) => sprintf('%02d-%02d', $order[$d->defense_type] ?? 99, $d->attempt))
                ->values()
            : collect();

        return view('student.defense-scores', ['defenses' => $defenses]);
    }
}
