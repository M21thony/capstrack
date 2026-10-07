<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expert evaluations move from one per Progress Tracker phase to one per
 * passed defense, answered on the CaPSA's questions.
 *
 * An evaluation now belongs to a defense and the template it was answered
 * on, keeps its answers (each question's wording with its 1-5 score, so a
 * later edit to the template cannot change what was said), and its rating
 * is their average - a decimal, not a whole star. The per-phase columns
 * stay, nullable, so the evaluations already sent keep counting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expert_evaluations', function (Blueprint $table) {
            $table->unsignedBigInteger('defense_id')->nullable()->after('expert_id');
            $table->unsignedBigInteger('evaluation_template_id')->nullable()->after('defense_id');
            $table->json('answers')->nullable()->after('role');
        });

        Schema::table('expert_evaluations', function (Blueprint $table) {
            $table->unsignedBigInteger('checklist_milestone_id')->nullable()->change();
            $table->text('comment')->nullable()->change();
            $table->decimal('rating', 3, 2)->change();
            $table->dropUnique('expert_evaluations_once');
            $table->unique(['team_id', 'expert_id', 'defense_id', 'role'], 'expert_evaluations_per_defense');
        });

        Schema::table('expert_evaluation_reminders', function (Blueprint $table) {
            $table->unsignedBigInteger('defense_id')->nullable()->after('team_id');
        });

        Schema::table('expert_evaluation_reminders', function (Blueprint $table) {
            $table->unsignedBigInteger('checklist_milestone_id')->nullable()->change();
            $table->dropUnique('expert_evaluation_reminders_once');
            $table->unique(['team_id', 'defense_id'], 'expert_evaluation_reminders_per_defense');
        });
    }

    public function down(): void
    {
        Schema::table('expert_evaluation_reminders', function (Blueprint $table) {
            $table->dropUnique('expert_evaluation_reminders_per_defense');
            $table->dropColumn('defense_id');
            $table->unique(['team_id', 'checklist_milestone_id'], 'expert_evaluation_reminders_once');
        });

        Schema::table('expert_evaluations', function (Blueprint $table) {
            $table->dropUnique('expert_evaluations_per_defense');
            $table->dropColumn(['defense_id', 'evaluation_template_id', 'answers']);
            $table->unique(['team_id', 'expert_id', 'checklist_milestone_id', 'role'], 'expert_evaluations_once');
        });
    }
};
