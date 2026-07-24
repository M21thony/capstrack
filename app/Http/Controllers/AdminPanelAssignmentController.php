<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\View\View;

class AdminPanelAssignmentController extends Controller
{
    public function index(): View
    {
        $teams = Team::where('is_confirmed', true)
            ->where('team_status', '!=', 'closed')
            ->orderBy('team_name')
            ->get();

        $teamIds = $teams->pluck('team_id');

        $sections = StudentSchoolYear::whereIn('team_id', $teamIds)
            ->whereNotNull('program')
            ->with('student')
            ->get()
            ->keyBy('team_id');

        $teamSections = [];
        foreach ($teams as $team) {
            $sy = $sections->get($team->team_id);
            if ($sy && $sy->student) {
                $sectionLabel = Student::computeSectionLabel($sy->student->course, $sy->student->year_section);
                $teamSections[$team->team_id] = $sectionLabel ?? $sy->program ?? '';
            } else {
                $teamSections[$team->team_id] = '';
            }
        }

        $adviserRole = Role::where('role_name', 'adviser')->first();
        $consultantRole = Role::where('role_name', 'consultant')->first();

        $expertRoleIds = array_filter([
            $adviserRole?->role_id,
            $consultantRole?->role_id,
        ]);

        $experts = CapstoneExpert::where('approved_status', true)
            ->whereHas('roles', function ($q) use ($expertRoleIds) {
                $q->whereIn('roles.role_id', $expertRoleIds);
            })
            ->with(['roles' => function ($q) use ($expertRoleIds) {
                $q->whereIn('roles.role_id', $expertRoleIds);
            }])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $allSections = array_values(array_unique(array_filter(array_map('trim', $teamSections))));
        sort($allSections);

        return view('admin.panel_assignment', compact('teams', 'experts', 'teamSections', 'allSections'));
    }
}
