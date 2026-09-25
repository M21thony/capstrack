<?php

use App\Models\Student;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Students added one at a time were saved with their program code ("BSIT")
 * while the class-list upload saved the full course name. Classmates are
 * matched on students.course, so the two groups never saw each other - they
 * could not find or invite one another into a team. Every code becomes its
 * full name, the form the rest of the table already uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Student::PROGRAM_COURSES as $code => $fullName) {
            DB::table('students')
                ->whereRaw('UPPER(TRIM(course)) = ?', [strtoupper($code)])
                ->update(['course' => $fullName]);
        }
    }

    /** Which rows held a code is not recorded, so there is nothing to put back. */
    public function down(): void {}
};
