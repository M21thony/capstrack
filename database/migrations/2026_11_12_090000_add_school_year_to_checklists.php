<?php

use App\Models\Term;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Milestones per batch: a checklist belongs to the school year its students
 * started in, so a new batch does not open on last year's deadlines. Every
 * list there is today belongs to the running year.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklists', function (Blueprint $table) {
            $table->string('school_year', 9)->nullable()->after('program');
        });

        DB::table('checklists')->update(['school_year' => Term::currentSchoolYear()]);
    }

    public function down(): void
    {
        Schema::table('checklists', function (Blueprint $table) {
            $table->dropColumn('school_year');
        });
    }
};
