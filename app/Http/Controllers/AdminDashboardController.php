<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\Team;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        $now = now();
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('n');

        $syStart = $currentMonth >= 6 ? $currentYear : $currentYear - 1;
        $syEnd = $syStart + 1;
        $currentSchoolYearLabel = 'SY '.$syStart.'–'.$syEnd;

        $totalStudents = Student::count();
        $totalExperts = CapstoneExpert::where('approved_status', true)->count();
        $activeTeams = Team::where('team_status', 'on-going')->count();
        $completedTeams = Team::where('team_status', 'complete')->count();
        $pendingApprovals = CapstoneExpert::where('approved_status', false)
            ->whereNull('rejection_reason')
            ->count();

        return view('admin.dashboard', compact(
            'totalStudents',
            'totalExperts',
            'activeTeams',
            'completedTeams',
            'pendingApprovals',
            'currentSchoolYearLabel',
        ));
    }
}
