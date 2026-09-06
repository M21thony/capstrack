<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\Milestone;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;

class ProgressController extends Controller
{
    public function index()
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
                // Counted from the items. milestones.status never moved off
                // 'todo', so completed and overdue were always zero here and
                // every team showed no progress whatever they had done.
                $milestones = Milestone::where('team_id', $team->team_id)->with('items')->get();
                $total = 0;
                $completed = 0;
                $overdue = 0;

                foreach ($milestones as $milestone) {
                    $milestoneProgress = $milestone->progress();
                    $total += $milestoneProgress->total;
                    $completed += $milestoneProgress->completed;
                    $overdue += $milestoneProgress->overdue;
                }

                $progress = $total > 0 ? round(($completed / $total) * 100) : 0;

                $studentSy = StudentSchoolYear::where('team_id', $team->team_id)->first();

                return (object) [
                    'team' => $team,
                    'progress' => $progress,
                    'completed' => $completed,
                    'total' => $total,
                    'overdue' => $overdue,
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
