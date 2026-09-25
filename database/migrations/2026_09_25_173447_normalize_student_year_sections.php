<?php

use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A class list with a combined "Year & Section" column stored what it held,
 * so the same section was on file as "4th · A" and as "4th Year · BSCS-4A".
 * Classmates are matched on students.year_section, so the two never saw each
 * other, and the sidebar read the long one as "BSCS - 4BSCS-4A". Every row
 * becomes the short form the rest of the app writes; the class list's own
 * copy on student_rosters too, since signing in copies it onto the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('students')->whereNotNull('year_section')->orderBy('student_id')
            ->select(['student_id', 'year_section'])
            ->each(function (object $row): void {
                $normal = Student::normalizeYearSection($row->year_section);

                if ($normal !== $row->year_section) {
                    DB::table('students')->where('student_id', $row->student_id)->update(['year_section' => $normal]);
                }
            });

        DB::table('student_rosters')->where('section', 'like', '%·%')->orderBy('id')
            ->select(['id', 'section'])
            ->each(function (object $row): void {
                $normal = Student::normalizeYearSection($row->section);

                if ($normal !== null && $normal !== $row->section) {
                    DB::table('student_rosters')->where('id', $row->id)->update(['section' => $normal]);
                }
            });
    }

    /** Which rows held the long form is not recorded, so there is nothing to put back. */
    public function down(): void {}
};
