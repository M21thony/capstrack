<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each rating keeps the rubric it was made with, so editing a rubric later
 * never changes the criteria under scores already given. Empty on ratings
 * from before this: those were all made with the department's rubric.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_rating_forms', function (Blueprint $table) {
            $table->json('rubric')->nullable()->after('days_to_rate');
        });
    }

    public function down(): void
    {
        Schema::table('team_rating_forms', function (Blueprint $table) {
            $table->dropColumn('rubric');
        });
    }
};
