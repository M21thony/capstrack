<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\TeamExpertAssignment;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function index()
    {
        $expert = Auth::guard('expert')->user();

        $approvedAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->with(['team', 'role', 'team.leader', 'team.members'])
            ->get();

        $grouped = $approvedAssignments->groupBy(fn ($a) => $a->role?->role_name ?? 'unknown');

        $expertRoles = $expert->roles->pluck('role_name');

        $tabs = collect(['all']);
        if ($expertRoles->contains('adviser')) {
            $tabs->push('adviser');
        }
        if ($expertRoles->contains('consultant')) {
            $tabs->push('consultant');
        }
        $tabs->push('panel');

        $totalTeams = $approvedAssignments->count();

        return view('instructor.teams', [
            'grouped' => $grouped,
            'tabs' => $tabs,
            'totalTeams' => $totalTeams,
            'expert' => $expert,
            'approvedAssignments' => $approvedAssignments,
        ]);
    }
}
