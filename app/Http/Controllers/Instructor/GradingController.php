<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Rubric;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;

class GradingController extends Controller
{
    public function index()
    {
        /** @var CapstoneExpert $expert */
        $expert = auth()->guard('expert')->user();

        $panelRole = Role::where('role_name', 'panel')->first();

        if (! $panelRole || ! $expert->roles()->where('roles.role_id', $panelRole->role_id)->exists()) {
            return view('instructor.grading', [
                'isPanel' => false,
                'teams' => collect(),
                'rubrics' => collect(),
            ]);
        }

        $teamIds = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->pluck('team_id');

        $teams = Team::whereIn('team_id', $teamIds)
            ->with('leader')
            ->get()
            ->map(function ($team) {
                $studentSy = StudentSchoolYear::where('team_id', $team->team_id)->first();

                return (object) [
                    'id' => $team->team_id,
                    'name' => $team->team_name,
                    'project_title' => $team->project_title,
                    'section' => $studentSy?->program ?? 'N/A',
                    'leader' => $team->leader,
                ];
            });

        $rubrics = Rubric::with('criteria')->orderByDesc('created_at')->get();

        return view('instructor.grading', [
            'isPanel' => true,
            'teams' => $teams,
            'rubrics' => $rubrics,
        ]);
    }
}
