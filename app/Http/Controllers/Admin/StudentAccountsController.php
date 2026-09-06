<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Account management for Students - a genuinely new surface, distinct from
// the existing admin/sections page (StudentSectionController), which is
// CAPSA's "browse the students in my assigned section(s)" tool, not
// Admin's "manage the account list" one.
class StudentAccountsController extends Controller
{
    /**
     * Year levels the capstone subject is taught in.
     *
     * The list is short on purpose: capstone is a 3rd and 4th year subject,
     * so a Year filter offering 1st and 2nd would be four options where two
     * of them can only ever return nothing.
     */
    public const YEARS = ['3rd', '4th'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $program = (string) $request->query('program', '');
        $year = (string) $request->query('year', '');
        $section = strtoupper(trim((string) $request->query('section', '')));

        // A letter, or nothing. Straight into a LIKE otherwise, and a student
        // typing % into the address bar would match the whole college.
        if (! preg_match('/^[A-Z]$/', $section)) {
            $section = '';
        }

        $students = Student::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('f_name', 'like', "%{$search}%")
                        ->orWhere('l_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                });
            })
            // course holds either the code or the full name depending on how
            // the record arrived, so both spellings have to be matched.
            ->when(isset(Student::PROGRAM_COURSES[$program]), function ($query) use ($program) {
                $query->where(function ($q) use ($program) {
                    $q->where('course', $program)
                        ->orWhere('course', Student::PROGRAM_COURSES[$program]);
                });
            })
            // year_section reads "4th · A" - the year is what it starts with,
            // the section is the letter it ends with.
            ->when(in_array($year, self::YEARS, true), fn ($q) => $q->where('year_section', 'like', $year.'%'))
            ->when($section !== '', fn ($q) => $q->where('year_section', 'like', '% '.$section))
            ->orderBy('l_name')
            ->paginate(30)
            ->withQueryString();

        // Only the letters somebody is actually enrolled in. The catalog runs
        // A to Z, and a filter listing twenty-six sections when the college
        // runs four is a longer list that says less.
        $sections = Student::query()
            ->whereNotNull('year_section')
            ->distinct()
            ->pluck('year_section')
            ->map(fn ($ys) => preg_match('/[A-Za-z]+$/', (string) $ys, $m) ? strtoupper($m[0]) : null)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return view('admin.students', [
            'students' => $students,
            'search' => $search,
            'program' => $program,
            'year' => $year,
            'section' => $section,
            'sections' => $sections,
            'years' => self::YEARS,
        ]);
    }

    /**
     * Manually add a single student account, bypassing the roster-upload +
     * Google sign-in gate (e.g. a late enrollee who needs access now).
     */
}
