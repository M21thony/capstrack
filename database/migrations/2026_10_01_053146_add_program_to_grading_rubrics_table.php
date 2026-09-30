<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A rubric can be written for one program (BSIT, BSCS, ...). Left empty, it
 * is the stage's rubric for every program, which is what every rubric made
 * before this was - so nothing already in use changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grading_rubrics', function (Blueprint $table) {
            $table->string('program', 8)->nullable()->after('rubric_type');
        });
    }

    public function down(): void
    {
        Schema::table('grading_rubrics', function (Blueprint $table) {
            $table->dropColumn('program');
        });
    }
};
