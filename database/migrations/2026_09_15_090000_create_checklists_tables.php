<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One checklist per CaPSA/TSA per program, instead of a copy per team.
 *
 * Every team used to get its own milestones row and its own nineteen
 * milestone_items, so moving one deliverable meant changing every team's copy
 * - on MyISAM, where a failure halfway cannot be rolled back and leaves teams
 * with different checklists. A checklist is now written once:
 *
 *   checklists            a CaPSA/TSA's list for one program (BSIT, BSIS, ...)
 *   checklist_milestones  its numbered groups, in order
 *   deliverables          what a team hands in or passes, in order
 *   deliverable_checks    the CaPSA/TSA's verdict on one team's signed paper
 *
 * Archiving sets archived_at and can be undone; removing is a soft delete,
 * purged for good after a few days, so Undo can still bring it back.
 *
 * The old milestones and milestone_items tables stay until every copy has
 * been moved over by checklists:consolidate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('capsa_id');
            $table->string('program', 8);
            $table->string('title');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['capsa_id', 'program']);
        });

        Schema::create('checklist_milestones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('checklist_id');
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('checklist_id')->references('id')->on('checklists')->cascadeOnDelete();
            $table->index(['checklist_id', 'position']);
        });

        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('checklist_milestone_id');

            // Stable identity for a line that came from the department checklist.
            $table->string('template_key', 64)->nullable();

            $table->string('title');
            $table->string('kind', 16)->default('form');

            // What a self-closing line points at: who signs a CLF, which
            // chapter an upload is, which defense the panel records.
            $table->string('role', 32)->nullable();
            $table->string('chapter', 50)->nullable();
            $table->string('defense_type', 64)->nullable();

            $table->date('due_date')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('checklist_milestone_id')->references('id')->on('checklist_milestones')->cascadeOnDelete();
            $table->index(['checklist_milestone_id', 'position']);
            $table->index('template_key');
        });

        Schema::create('deliverable_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deliverable_id');
            $table->unsignedBigInteger('team_id');

            // complete, or sent_back with a note saying what to fix.
            $table->string('status', 16);
            $table->text('note')->nullable();

            // The upload that was looked at, when there was one.
            $table->unsignedBigInteger('file_id')->nullable();
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->foreign('deliverable_id')->references('id')->on('deliverables')->cascadeOnDelete();
            $table->unique(['deliverable_id', 'team_id']);
            $table->index('team_id');
        });

        Schema::table('capstone_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('deliverable_id')->nullable()->after('milestone_item_id');
            $table->index('deliverable_id');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_documents', function (Blueprint $table) {
            $table->dropIndex(['deliverable_id']);
            $table->dropColumn('deliverable_id');
        });

        Schema::dropIfExists('deliverable_checks');
        Schema::dropIfExists('deliverables');
        Schema::dropIfExists('checklist_milestones');
        Schema::dropIfExists('checklists');
    }
};
