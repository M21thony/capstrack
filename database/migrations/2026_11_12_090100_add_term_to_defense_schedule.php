<?php

use App\Models\Term;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A defense remembers the semester it was set in. Every defense there is today
 * belongs to the running one - and an install left with no running semester
 * gets one first, so there is something to belong to.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Term::active()) {
            Term::firstOrCreate(['school_year' => Term::currentSchoolYear(), 'semester' => 1], ['capstone_stage' => 1])->activate();
            Term::forgetActive();
        }

        $termId = Term::activeId();

        DB::table('student_s_y')->whereNull('term_id')->update(['term_id' => $termId]);

        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->unsignedBigInteger('term_id')->nullable();
        });

        DB::table('defense_schedule')->update(['term_id' => $termId]);
    }

    public function down(): void
    {
        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->dropColumn('term_id');
        });
    }
};
