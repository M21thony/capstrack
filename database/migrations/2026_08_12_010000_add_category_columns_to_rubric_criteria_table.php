<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets criteria be grouped under a named category (e.g. "Manuscript",
// "Topic Quality and Proposal Merit") with its own point budget, matching
// how the school's real rubrics are structured, without touching the
// existing flat "all score-levels across the rubric total 100" validation -
// category is purely a grouping/display label, not a new enforced total.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table) {
            $table->string('category')->nullable()->after('description');
            $table->decimal('category_max_points', 6, 2)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table) {
            $table->dropColumn(['category', 'category_max_points']);
        });
    }
};
