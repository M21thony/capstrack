<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CapstoneExpert;
use App\Models\StudentSchoolYear;
use App\Models\TeamExpertAssignment;
use Illuminate\Support\Facades\Auth;

class ExpertController extends Controller
{
    public function index()
    {
        $student = Auth::user();
        $team = StudentSchoolYear::where('student_id', $student->student_id)
            ->whereNotNull('team_id')
            ->first()?->team;

        $experts = CapstoneExpert::where('approved_status', true)
            ->whereHas('roles', function ($q) {
                $q->where('role_name', '!=', 'capstone_adviser');
            })
            ->with(['roles' => function ($q) {
                $q->where('role_name', '!=', 'capstone_adviser');
            }, 'expertise', 'teamAssignments' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->paginate(9)
            ->through(function ($expert) {
                $expert->slots_used = $expert->teamAssignments->count();
                unset($expert->teamAssignments);

                return $expert;
            });

        $assignments = collect();
        $isLeader = false;
        $roleAssignments = [
            'adviser' => null,
            'consultant' => null,
            'grammarian' => null,
        ];

        if ($team) {
            $isLeader = $team->leader_id === $student->student_id;
            $assignments = TeamExpertAssignment::where('team_id', $team->team_id)
                ->with(['expert.roles', 'expert.expertise'])
                ->get();

            foreach ($assignments->where('status', 'approved') as $a) {
                $roleName = $a->expert?->roles?->first()?->role_name;
                if ($roleName && array_key_exists($roleName, $roleAssignments)) {
                    $roleAssignments[$roleName] = $a;
                }
            }
        }

        $stats = [
            'total_experts' => $experts->total(),
            'team_approved' => $assignments->where('status', 'approved')->count(),
            'team_pending' => $assignments->where('status', 'pending')->count(),
        ];

        $lockedRoles = $assignments->whereIn('status', ['pending', 'approved'])
            ->map(function ($a) {
                return $a->expert?->roles?->first()?->role_name;
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        return view('student.experts', [
            'experts' => $experts,
            'assignments' => $assignments,
            'team' => $team,
            'isLeader' => $isLeader,
            'teamConfirmed' => $team?->is_confirmed ?? false,
            'stats' => $stats,
            'roleAssignments' => $roleAssignments,
            'lockedRoles' => $lockedRoles,
        ]);
    }
}