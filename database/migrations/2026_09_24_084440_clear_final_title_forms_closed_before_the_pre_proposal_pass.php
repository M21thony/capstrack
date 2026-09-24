<?php

use App\Support\DefenseOutcome;
use App\Support\TitleRevision;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Team Bulbasaur's Signed Final Project Title Form was checked done before
 * the team passed its Pre-proposal Defense. The form is signed on the title
 * the panel passed, so for every team that has not passed yet, the check is
 * cleared and any file sent for the form is unlinked from it (the file itself
 * stays with the team's documents). The team hands it in again after the pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        $formIds = DB::table('deliverables')->where('template_key', TitleRevision::FINAL_TITLE_FORM)->pluck('id');

        if ($formIds->isEmpty()) {
            return;
        }

        $passed = DB::table('defense_schedule')
            ->where('defense_type', DefenseOutcome::PRE_PROPOSAL)
            ->whereIn('outcome', [DefenseOutcome::PASSED, DefenseOutcome::PASSED_WITH_REVISION])
            ->pluck('team_id');

        DB::transaction(function () use ($formIds, $passed) {
            DB::table('deliverable_checks')
                ->whereIn('deliverable_id', $formIds)
                ->whereNotIn('team_id', $passed)
                ->delete();

            DB::table('capstone_documents')
                ->whereIn('deliverable_id', $formIds)
                ->whereNotIn('student_id', DB::table('student_s_y')->whereIn('team_id', $passed)->select('student_id'))
                ->update(['deliverable_id' => null]);
        });
    }

    /** A cleared check is not put back. */
    public function down(): void {}
};
