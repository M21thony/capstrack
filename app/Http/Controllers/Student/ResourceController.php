<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AdminDriveFolder;
use App\Models\CapstoneExpert;
use App\Models\Resource;
use App\Models\Student;
use App\Models\StudentSchoolYear;

class ResourceController extends Controller
{
    public function index()
    {
        /** @var Student $student */
        $student = auth()->user();

        // The enrolment row carries a program, but it is null on every row
        // in practice, and the filter below used to be skipped entirely when
        // it was - which showed every student every program's resources. The
        // student's own course answers the same question, so it is the
        // fallback, and a student we cannot place sees only the resources
        // meant for every program rather than all of them.
        $studentSy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $program = $studentSy?->program ?: Student::mapCourseToProgram($student->course ?? '');

        // Their own CaPSA's uploads, for their own program. The page used to
        // show every resource in the system: the program filter was skipped
        // whenever the program could not be read - which is every row in
        // practice - and there was no filter on the uploader at all.
        //
        // The section is computed rather than stored, so the CaPSAs covering
        // it are matched in PHP. There are a handful of them.
        $section = Student::computeSectionLabel($student->course, $student->year_section);

        $capsaIds = $section
            ? CapstoneExpert::whereHas('roles', fn ($q) => $q->where('role_name', 'capstone_adviser'))
                ->get(['expert_id', 'sections'])
                ->filter(fn ($capsa) => in_array($section, $capsa->sections ?? [], true))
                ->pluck('expert_id')
                ->all()
            : [];

        // No CaPSA covering this student means no resources - deliberately,
        // not everything. An empty list read as "no filter" is how this page
        // came to show every program's material to everybody.
        $resources = Resource::orderByDesc('uploaded_at')
            ->whereIn('uploaded_by', $capsaIds)
            ->where(fn ($q) => $q
                ->when($program, fn ($w) => $w->where('resource_for', $program))
                ->orWhereNull('resource_for'))
            ->get();

        $folders = AdminDriveFolder::orderBy('name')->get();

        return view('student.resources', [
            'resources' => $resources,
            'folders' => $folders,
        ]);
    }
}
