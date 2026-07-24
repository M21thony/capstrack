<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index()
    {
        $totalArchived = ArchivedDocument::count();
        $totalExperts = CapstoneExpert::where('approved_status', true)->count();
        $totalStudents = Student::count();
        $totalTeams = Team::count();

        $completedTeams = Team::whereIn('team_status', ['Done', 'complete', 'closed'])->count();
        $activeTeams = Team::where('team_status', 'Active')->count();
        $completionRate = $totalTeams > 0 ? round(($completedTeams / $totalTeams) * 100) : 0;

        $overdueTeams = Team::where('team_status', 'on-going')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('milestones')
                    ->whereColumn('milestones.team_id', 'team.team_id')
                    ->where('milestones.status', 'overdue');
            })
            ->count();

        $yearlyArchive = ArchivedDocument::selectRaw('academic_year, COUNT(*) as total')
            ->groupBy('academic_year')
            ->orderBy('academic_year')
            ->pluck('total', 'academic_year');

        $programDistribution = StudentSchoolYear::selectRaw('program, COUNT(DISTINCT student_id) as total')
            ->whereNotNull('program')
            ->groupBy('program')
            ->pluck('total', 'program');

        return view('admin.analytics', [
            'totalArchived' => $totalArchived,
            'totalExperts' => $totalExperts,
            'totalStudents' => $totalStudents,
            'totalTeams' => $totalTeams,
            'completionRate' => $completionRate,
            'completedTeams' => $completedTeams,
            'activeTeams' => $activeTeams,
            'overdueTeams' => $overdueTeams,
            'yearlyArchive' => $yearlyArchive,
            'programDistribution' => $programDistribution,
        ]);
    }
}
