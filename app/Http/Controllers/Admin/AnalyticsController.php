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

        $completedTeams = Team::whereNotExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('milestones')
                ->whereColumn('milestones.team_id', 'team.team_id')
                ->where('milestones.status', '!=', 'completed');
        })->whereExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('milestones')
                ->whereColumn('milestones.team_id', 'team.team_id');
        })->count();
        $activeTeams = Team::where('team_status', 'Active')->count();
        $completionRate = $totalTeams > 0 ? round(($completedTeams / $totalTeams) * 100) : 0;

        $overdueTeams = Team::where('team_status', 'Active')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('milestones')
                    ->whereColumn('milestones.team_id', 'team.team_id')
                    ->where('milestones.status', 'overdue');
            })
            ->count();

        $selectedYear = request('year');
        $years = ArchivedDocument::selectRaw('DISTINCT academic_year')
            ->orderBy('academic_year', 'desc')
            ->pluck('academic_year');
        $yearlyQuery = ArchivedDocument::selectRaw('academic_year, COUNT(*) as total')
            ->groupBy('academic_year')
            ->orderBy('academic_year');
        if ($selectedYear) {
            $yearlyQuery->where('academic_year', $selectedYear);
        }
        $yearlyArchive = $yearlyQuery->pluck('total', 'academic_year');
        $maxYearlyCount = $yearlyArchive->max() ?: 1;

        $programDistribution = StudentSchoolYear::selectRaw('program, COUNT(DISTINCT student_id) as total')
            ->whereNotNull('program')
            ->groupBy('program')
            ->pluck('total', 'program');

        return view('capsa.analytics', [
            'totalArchived' => $totalArchived,
            'totalExperts' => $totalExperts,
            'totalStudents' => $totalStudents,
            'totalTeams' => $totalTeams,
            'completionRate' => $completionRate,
            'completedTeams' => $completedTeams,
            'activeTeams' => $activeTeams,
            'overdueTeams' => $overdueTeams,
            'yearlyArchive' => $yearlyArchive,
            'maxYearlyCount' => $maxYearlyCount,
            'years' => $years,
            'selectedYear' => $selectedYear,
            'programDistribution' => $programDistribution,
        ]);
    }
}
