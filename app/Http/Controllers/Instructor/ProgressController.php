<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;

class ProgressController extends Controller
{
    /**
     * Progress lives on Team Overview now.
     *
     * The two pages listed the same teams and answered halves of the same
     * question, so an adviser looking at a team had to leave it to find out
     * how that team was doing. The route stays so a bookmark still lands
     * somewhere; everything it used to draw is on the team's own card.
     */
    public function index()
    {
        return redirect()->route('instructor.teams');
    }

    /** Kept for reference until the merged page has been through a term. */
    public function legacyIndex()
    {
        /** @var CapstoneExpert $expert */
        $expert = auth()->guard('expert')->user();

        $teamIds = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->pluck('team_id');

        $teams = Team::whereIn('team_id', $teamIds)
            ->with('leader', 'members')
            ->get()
            ->map(function ($team) {
                // Counted from the items, on the model, because Team
                // Overview asks the same question.
                $summary = $team->progressSummary();

                $studentSy = StudentSchoolYear::where('team_id', $team->team_id)->first();

                return (object) [
                    'team' => $team,
                    'progress' => $summary->percent,
                    'completed' => $summary->completed,
                    'total' => $summary->total,
                    'overdue' => $summary->overdue,
                    'section' => $studentSy?->program ?? 'N/A',
                ];
            });

        $teamsMonitored = $teams->count();
        $avgCompletion = $teamsMonitored > 0 ? round($teams->avg('progress')) : 0;
        $overdueMilestones = $teams->sum('overdue');
        $onTrack = $teams->where('overdue', 0)->count();

        $sections = StudentSchoolYear::whereNotNull('program')
            ->distinct('program')
            ->pluck('program');

        return view('instructor.progress', [
            'teams' => $teams,
            'teamsMonitored' => $teamsMonitored,
            'avgCompletion' => $avgCompletion,
            'overdueMilestones' => $overdueMilestones,
            'onTrack' => $onTrack,
            'sections' => $sections,
        ]);
    }
}
