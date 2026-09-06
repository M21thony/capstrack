<?php

use App\Models\Term;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let student_s_y.school_year hold a school year, then put one in every row.
 *
 * The column was declared year(), which in MySQL holds a single four-digit
 * year and nothing else. A school year here is written "2026-2027" - that is
 * what terms.school_year holds, in varchar(20) - so every write of one was
 * truncated away. The live table held nineteen nulls and one "0000" while the
 * active term read 2026-2027, and the readers that filter on the column, the
 * classmate picker among them, matched nobody at all.
 *
 * Widening it first, then filling it: a row takes its own term's year, and a
 * row with no term takes the current one, which is the best that can be said
 * about it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_s_y', function (Blueprint $table) {
            $table->string('school_year', 20)->nullable()->change();
        });

        $current = Term::currentSchoolYear();

        foreach (DB::table('student_s_y')->get() as $row) {
            if (is_string($row->school_year) && preg_match('/^\d{4}-\d{4}$/', $row->school_year) === 1) {
                continue;
            }

            $year = $row->term_id
                ? (DB::table('terms')->where('term_id', $row->term_id)->value('school_year') ?: $current)
                : $current;

            DB::table('student_s_y')
                ->where('s_y_id', $row->s_y_id)
                ->update(['school_year' => $year]);
        }
    }

    public function down(): void
    {
        // Deliberately not reversed. Narrowing back to year() would truncate
        // every value written since, and the nulls it replaced were only ever
        // the symptom of the column being the wrong type.
    }
};
