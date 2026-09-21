<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\MilestoneProgress;
use App\Support\SectionScope;
use App\Support\TeamChecklist;

class AnalyticsController extends Controller
{
    public function index(SectionScope $sections, TeamChecklist $checklists)
    {
        $totalArchived = ArchivedDocument::count();
        $totalExperts = CapstoneExpert::where('approved_status', true)->count();
        $totalStudents = Student::count();

        $teams = Team::all();
        $totalTeams = $teams->count();

        // These four figures used to be counted off the `milestones` table,
        // which nothing has written to since the checklist tables replaced it
        // - one row in the whole database. They now come from the same place
        // Team Management reads, so the two screens agree.
        $records = StudentSchoolYear::whereIn('team_id', $teams->pluck('team_id'))
            ->with('student')->get()->groupBy('team_id');

        $progress = [];

        foreach ($teams->groupBy(fn (Team $team) => $sections->sectionFor($team, $records->get($team->team_id))) as $section => $group) {
            $checklist = $checklists->forSection((string) $section);

            if (! $checklist) {
                continue;
            }

            foreach (MilestoneProgress::forTeams($checklist, $group->pluck('team_id')) as $teamId => $summary) {
                $progress[$teamId] = $summary;
            }
        }

        $completedTeams = count(array_filter($progress, fn ($p) => $p->total > 0 && $p->completed === $p->total));

        // A team whose section has no checklist has nothing to be a percentage
        // of. Counting it as 0% would drag the rate down with a number nobody
        // ever set, so it is left out and reported separately instead.
        $trackedTeams = count($progress);
        $untrackedTeams = $totalTeams - $trackedTeams;
        $completionRate = $trackedTeams > 0 ? (int) round($completedTeams / $trackedTeams * 100) : null;

        $activeTeams = $teams->where('team_status', 'Active')->count();

        $overdueTeams = $teams->where('team_status', 'Active')
            ->filter(fn (Team $team) => ($progress[$team->team_id]->overdue ?? 0) > 0)
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
            'trackedTeams' => $trackedTeams,
            'untrackedTeams' => $untrackedTeams,
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
