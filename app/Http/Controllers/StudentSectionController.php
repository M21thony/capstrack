<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\SectionCatalog;
use App\Support\SectionScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentSectionController extends Controller
{
    public function index(Request $request): View
    {
        $admin = auth('expert')->user();
        $adminSections = $admin->sections ?? [];

        // Only one of theirs. Picking a section used to skip the ownership
        // check below entirely, so any CaPSA could read another's students by
        // choosing their section from a list that offered all eleven of them.
        $selectedSection = $request->query('section');

        if ($selectedSection && ! in_array($selectedSection, $adminSections, true)) {
            $selectedSection = null;
        }

        $allStudents = Student::whereNotNull('course')
            ->whereNotNull('year_section')
            ->orderBy('l_name')
            ->orderBy('f_name')
            ->get();

        $syRecords = StudentSchoolYear::with('team')
            ->whereIn('student_id', $allStudents->pluck('student_id'))
            ->get()
            ->keyBy('student_id');

        // Each team's own section - its leader's. A 4th-year team can have a
        // member here while it belongs to another section, and that student's
        // row says so rather than looking like one of this CaPSA's teams.
        $scope = app(SectionScope::class);
        $teamIds = $syRecords->pluck('team_id')->filter()->unique();
        $teamRecords = StudentSchoolYear::with('student')->whereIn('team_id', $teamIds)->get()->groupBy('team_id');
        $teamSections = Team::whereIn('team_id', $teamIds)->get()
            ->mapWithKeys(fn (Team $team) => [$team->team_id => $scope->sectionFor($team, $teamRecords->get($team->team_id, collect()))]);

        $students = collect();
        $sectionCounts = [];

        foreach ($allStudents as $student) {
            $sectionLabel = Student::computeSectionLabel($student->course, $student->year_section);
            if (! $sectionLabel) {
                continue;
            }

            // Theirs either way: one of their sections when one is chosen,
            // all of them when none is.
            if (! in_array($sectionLabel, $adminSections, true)) {
                continue;
            }

            // Counted before the section filter, so each section's pill can
            // say how many it holds while another one is picked.
            $sectionCounts[$sectionLabel] = ($sectionCounts[$sectionLabel] ?? 0) + 1;

            if ($selectedSection && $sectionLabel !== $selectedSection) {
                continue;
            }

            $program = Student::mapCourseToProgram($student->course);
            $student->sectionLabel = $sectionLabel;
            $student->programAbbr = $program;
            $student->currentSy = $syRecords->get($student->student_id);
            $teamSection = $student->currentSy?->team_id ? ($teamSections[$student->currentSy->team_id] ?? '') : '';
            // Set only when the team belongs to a section other than the student's own.
            $student->teamSection = $teamSection !== '' && $teamSection !== $sectionLabel ? $teamSection : null;
            $student->teamHandledHere = $student->teamSection !== null && in_array($student->teamSection, $adminSections, true);
            $students->push($student);
        }

        $students = $students->values();

        // The sections this CaPSA handles, which is what "All my sections"
        // is the top of. It used to list every section in the college, so the
        // dropdown was mostly other people's work.
        $allSections = SectionCatalog::tidy($adminSections);

        $debug = null;
        if ($request->query('debug') == '1') {
            $sample = $allStudents->take(50)->map(fn ($s) => [
                'student_id' => $s->student_id,
                'course' => $s->course,
                'year_section' => $s->year_section,
                'program' => Student::mapCourseToProgram($s->course ?? ''),
                'computed_label' => Student::computeSectionLabel($s->course, $s->year_section),
            ]);

            $debug = [
                'student_count' => $allStudents->count(),
                'adminSections' => $adminSections,
                'sample' => $sample,
            ];
        }

        return view('capsa.sections', compact('students', 'adminSections', 'allSections', 'debug', 'sectionCounts', 'selectedSection'));
    }
}
