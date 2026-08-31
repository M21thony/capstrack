<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record the panel's decision on the defense itself.
     *
     * panel_grades.verdict is per panelist, which does not match how a
     * defense actually ends: the panel confers and then tells the team one
     * result. Three independent verdicts left the real outcome to be guessed
     * at by tallying them, with no rule anywhere for what a split meant.
     *
     * The decision now sits on the defense, recorded once by the Panel Chair
     * after the briefing. Each panelist still scores the rubric on their own -
     * that is their assessment, and the team's mark is the average of it -
     * but the verdict is the panel's, and there is exactly one.
     *
     * Nullable throughout: a defense that has not happened yet has no
     * outcome, and the existing 36 schedules have none to backfill.
     */
    public function up(): void
    {
        if (! Schema::hasTable('defense_schedule') || Schema::hasColumn('defense_schedule', 'outcome')) {
            return;
        }

        Schema::table('defense_schedule', function (Blueprint $table) {
            // Deliberately a string, not an enum: the options differ per
            // defense stage (only a pre-proposal can be sent back for a new
            // title), so the valid set is a per-stage question that
            // App\Support\DefenseOutcome answers, not one the column can.
            $table->string('outcome', 40)->nullable()->after('venue');
            $table->text('outcome_remarks')->nullable()->after('outcome');
            $table->unsignedBigInteger('outcome_recorded_by')->nullable()->after('outcome_remarks');
            $table->timestamp('outcome_recorded_at')->nullable()->after('outcome_recorded_by');

            $table->index('outcome');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('defense_schedule') || ! Schema::hasColumn('defense_schedule', 'outcome')) {
            return;
        }

        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->dropIndex(['outcome']);
            $table->dropColumn(['outcome', 'outcome_remarks', 'outcome_recorded_by', 'outcome_recorded_at']);
        });
    }
};
