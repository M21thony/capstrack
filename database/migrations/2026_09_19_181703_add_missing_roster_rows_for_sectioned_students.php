<?php

use App\Models\Student;
use App\Models\Term;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Put back on the class list every student a CaPSA/TSA can see in a section.
     *
     * Sign-in admits a student only through student_rosters. When that rule
     * was tightened, the students who had signed in before any list was
     * uploaded were assumed to be on it already, and not all were: they kept
     * their account and their section, so their CaPSA/TSA saw them in the list,
     * while the sign-in page told them they were not on it.
     *
     * A student with a section is one a CaPSA/TSA has placed. Removing somebody
     * clears their section as well as their roster row, so those stay out.
     * A course that maps to no program cannot be in anyone's section list,
     * and is left alone too.
     */
    public function up(): void
    {
        $listed = DB::table('student_rosters')
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn (string $email) => Str::lower(trim($email)))
            ->flip();

        $schoolYear = Term::currentSchoolYear();

        DB::table('students')
            ->whereNotNull('year_section')
            ->where('year_section', '!=', '')
            ->whereNotNull('email')
            ->orderBy('student_id')
            ->get(['student_id', 'email', 'f_name', 'l_name', 'student_number', 'course', 'year_section'])
            ->each(function (object $student) use ($listed, $schoolYear) {
                $email = Str::lower(trim($student->email));
                $program = Student::mapCourseToProgram((string) $student->course);

                if ($email === '' || $program === null || $listed->has($email)) {
                    return;
                }

                DB::table('student_rosters')->insert([
                    'name' => trim($student->f_name.' '.$student->l_name) ?: $email,
                    'email' => $email,
                    'student_number' => $student->student_number,
                    'section' => $student->year_section,
                    'program' => $program,
                    'school_year' => $schoolYear,
                    'imported_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Nothing to undo safely: the rows added here cannot be told apart from
     * ones a CaPSA/TSA has since edited or relied on, and taking them away would
     * lock those students out again.
     */
    public function down(): void {}
};
