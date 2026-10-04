<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The team leader's rating of each expert after each phase of the Progress
 * Tracker: one to five stars and a comment, once per expert, role and phase.
 *
 * expert_evaluation_reminders records which finished phases the leader has
 * already been told about, so the bell and the email go out once per phase
 * however often the check runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_evaluations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('expert_id');
            $table->unsignedBigInteger('checklist_milestone_id');
            // The role the team engaged the expert as: adviser, consultant or grammarian.
            $table->string('role', 20);
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            // The leader who sent it.
            $table->unsignedBigInteger('student_id');
            $table->timestamps();

            $table->unique(['team_id', 'expert_id', 'checklist_milestone_id', 'role'], 'expert_evaluations_once');
            $table->index('expert_id');
        });

        Schema::create('expert_evaluation_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('checklist_milestone_id');
            $table->timestamp('notified_at');

            $table->unique(['team_id', 'checklist_milestone_id'], 'expert_evaluation_reminders_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_evaluation_reminders');
        Schema::dropIfExists('expert_evaluations');
    }
};
