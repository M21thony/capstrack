<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared grading: one panelist grades for others on the panel, who join that
 * grade - not everyone brings a laptop to a defense. A joined panelist's row
 * holds a copy of the grader's, so averages and reports read it as theirs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel_grades', function (Blueprint $table) {
            // The panelist whose grade this one copies; null for one's own.
            $table->unsignedBigInteger('graded_by_expert_id')->nullable()->after('expert_id')->index();
            // When it was joined, and by whom - the grader, or anyone on the panel.
            $table->timestamp('joined_at')->nullable()->after('graded_by_expert_id');
            $table->unsignedBigInteger('joined_set_by')->nullable()->after('joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('panel_grades', function (Blueprint $table) {
            $table->dropIndex(['graded_by_expert_id']);
            $table->dropColumn(['graded_by_expert_id', 'joined_at', 'joined_set_by']);
        });
    }
};
