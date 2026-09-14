<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\DefensePanel;
use App\Models\TeamExpertAssignment;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function index()
    {
        $expert = Auth::guard('expert')->user();

        $approvedAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->with(['team', 'role', 'expertRole.role', 'team.leader', 'team.members'])
            ->get();

        $grouped = $approvedAssignments->groupBy(fn ($a) => $a->role->first()?->role_name ?? 'unknown');

        $expertRoles = $expert->roles->pluck('role_name');

        $tabs = collect(['all']);
        if ($expertRoles->contains('adviser')) {
            $tabs->push('adviser');
        }
        if ($expertRoles->contains('consultant')) {
            $tabs->push('consultant');
        }
        $tabs->push('panel');

        $totalTeams = $approvedAssignments->unique('team_id')->count();

        // The teams this expert sits on a defense panel for, one row per team.
        // A panel is not an application, so these come from the panels themselves.
        $panelSeats = DefensePanel::where('expert_id', $expert->expert_id)
            ->with('schedule.team')
            ->get()
            ->filter(fn ($p) => $p->schedule?->team)
            ->groupBy(fn ($p) => $p->schedule->team_id)
            ->map(fn ($seats) => (object) [
                'team' => $seats->first()->schedule->team,
                'role' => $seats->first()->panel_role,
                'scheduled' => $seats->filter(fn ($p) => $p->schedule->schedule_date)->pluck('schedule.defense_type')->unique()->values(),
            ])
            ->sortBy(fn ($s) => $s->team->team_name)
            ->values();

        // Progress used to be its own page, which meant an adviser looking at
        // a team had to leave it to find out how that team was doing. Same
        // teams, same cards - the numbers just come with them now. Keyed by
        // team so a team advised in two roles is only counted once.
        // Only for the teams this expert advises or consults for: a team's
        // checklist is its own, its CaPSA's and the two experts it chose, not
        // its grammarian's or its panel's.
        $progress = $approvedAssignments
            ->filter(fn ($a) => in_array($a->engaged_role_name, ['adviser', 'consultant'], true))
            ->pluck('team')->filter()->unique('team_id')
            ->mapWithKeys(fn ($team) => [$team->team_id => $team->progressSummary()]);

        return view('instructor.teams', [
            'grouped' => $grouped,
            'tabs' => $tabs,
            'totalTeams' => $totalTeams,
            'expert' => $expert,
            'approvedAssignments' => $approvedAssignments,
            'panelSeats' => $panelSeats,
            'progress' => $progress,
            // Shown only on the cards of teams this expert advises - the
            // adviser is who the department lets see a team's defense scores.
            'defenseScores' => \App\Support\DefenseScores::forTeams($approvedAssignments->pluck('team_id')),
            'avgCompletion' => $progress->isEmpty() ? 0 : (int) round($progress->avg('percent')),
            'overdueMilestones' => $progress->sum('overdue'),
            'onTrack' => $progress->where('overdue', 0)->count(),
        ]);
    }
}
