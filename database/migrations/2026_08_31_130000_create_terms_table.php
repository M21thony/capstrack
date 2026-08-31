<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The semester a team is doing capstone in.
     *
     * There was no term concept anywhere - the word "semester" appears
     * nowhere in the app - so there was nothing to filter Capstone 1 from
     * Capstone 2 by, and no way to tell this year's teams from last year's.
     *
     * student_s_y is named and shaped for exactly this (it carries
     * school_year, program and student_status) but every writer upserts on
     * student_id alone, so it holds one row per student forever: 20 rows, 20
     * students, school_year null on all of them. Reviving it is most of the
     * work; this table gives it something to point at.
     *
     * One term is active at a time, declared by an Admin or CAPSA rather
     * than inferred from dates - a school knows when its semester starts,
     * and guessing from defense windows breaks the moment two overlap.
     */
    public function up(): void
    {
        if (! Schema::hasTable('terms')) {
            Schema::create('terms', function (Blueprint $table) {
                $table->id('term_id');
                $table->string('school_year', 20);          // e.g. "2026-2027"
                $table->unsignedTinyInteger('semester');     // 1 or 2
                $table->unsignedTinyInteger('capstone_stage'); // Capstone 1 or 2
                $table->boolean('is_active')->default(false);
                $table->timestamps();

                $table->unique(['school_year', 'semester', 'capstone_stage']);
                $table->index('is_active');
            });
        }

        if (! Schema::hasColumn('student_s_y', 'term_id')) {
            Schema::table('student_s_y', function (Blueprint $table) {
                $table->unsignedBigInteger('term_id')->nullable()->after('student_id');
                $table->index('term_id');
            });
        }

        // Everything that exists today belongs to whatever term is running
        // now, so there is a first one to attach it to.
        $termId = DB::table('terms')->where('is_active', true)->value('term_id');

        if (! $termId) {
            $year = (int) now()->format('Y');
            $month = (int) now()->format('n');

            // A Philippine school year runs Aug-Jul, so months before August
            // still belong to the year before.
            $startYear = $month >= 8 ? $year : $year - 1;

            $termId = DB::table('terms')->insertGetId([
                'school_year' => $startYear.'-'.($startYear + 1),
                'semester' => $month >= 8 && $month <= 12 ? 1 : 2,
                'capstone_stage' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('student_s_y')->whereNull('term_id')->update(['term_id' => $termId]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('student_s_y', 'term_id')) {
            Schema::table('student_s_y', function (Blueprint $table) {
                $table->dropIndex(['term_id']);
                $table->dropColumn('term_id');
            });
        }

        Schema::dropIfExists('terms');
    }
};
