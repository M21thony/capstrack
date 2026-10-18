<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * The team tabs: everyone, those on a team this term, those not.
     *
     * @var list<string>
     */
    public const TEAM_FILTERS = ['with', 'without'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $program = (string) $request->query('program', '');
        $year = (string) $request->query('year', '');
        $section = strtoupper(trim((string) $request->query('section', '')));
        $team = (string) $request->query('team', '');

        // A letter, or nothing. Straight into a LIKE otherwise, and a student
        // typing % into the address bar would match the whole college.
        if (! preg_match('/^[A-Z]$/', $section)) {
            $section = '';
        }

        if (! in_array($team, self::TEAM_FILTERS, true)) {
            $team = '';
        }

        // Every filter but the team one, so each tab can say how many it
        // holds before it is picked.
        $filtered = Student::query()
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
            ->when($section !== '', fn ($q) => $q->where('year_section', 'like', '% '.$section));

        // "On a team" is this term's row having one (StudentSchoolYear's own
        // scope), the same thing the Team column shows.
        $inTeam = fn ($query) => $query->whereHas('studentSchoolYear.team');

        $allCount = (clone $filtered)->count();
        $withTeamCount = $inTeam(clone $filtered)->count();

        $students = (clone $filtered)
            ->when($team === 'with', $inTeam)
            ->when($team === 'without', fn ($q) => $q->whereDoesntHave('studentSchoolYear.team'))
            // This term's row only (StudentSchoolYear's own scope), so the Team
            // column shows the team they are on now, not one from a retake.
            ->with('studentSchoolYear.team')
            // One list, no pages: the table scrolls inside its own box.
            ->orderBy('l_name')
            ->get();

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
            'team' => $team,
            'teamCounts' => [
                '' => $allCount,
                'with' => $withTeamCount,
                'without' => $allCount - $withTeamCount,
            ],
        ]);
    }

    /**
     * Remove a student's account for good.
     *
     * Deleting the account takes them off their team (Student::booted). The
     * team is left whole: a leader's team passes to the member who joined
     * first, and a team with nobody left goes too, rather than staying on
     * every list with 0 members.
     */
    public function destroy(Student $student): JsonResponse
    {
        $name = trim($student->f_name.' '.$student->l_name) ?: $student->email;
        $teamId = StudentSchoolYear::withoutGlobalScope(StudentSchoolYear::ACTIVE_TERM_SCOPE)
            ->where('student_id', $student->student_id)
            ->whereNotNull('team_id')
            ->value('team_id');

        $teamNote = DB::transaction(function () use ($student, $teamId) {
            $student->delete();

            $team = $teamId ? Team::find($teamId) : null;

            if (! $team) {
                return '';
            }

            $left = StudentSchoolYear::withoutGlobalScope(StudentSchoolYear::ACTIVE_TERM_SCOPE)
                ->where('team_id', $team->team_id)
                ->orderBy('s_y_id')
                ->pluck('student_id');

            if ($left->isEmpty()) {
                $team->delete();

                return ' '.$team->team_name.' had no one else, so it was removed too.';
            }

            if ((int) $team->leader_id === (int) $student->student_id) {
                $team->update(['leader_id' => $left->first()]);
                $leader = Student::find($left->first());

                return ' '.trim(($leader?->f_name ?? '').' '.($leader?->l_name ?? '')).' now leads '.$team->team_name.'.';
            }

            return '';
        });

        return response()->json([
            'success' => true,
            'message' => $name.' was removed.'.$teamNote,
        ]);
    }
}
