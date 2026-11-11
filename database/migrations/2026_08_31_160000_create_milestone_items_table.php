<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The things a milestone is actually made of.
 *
 * A milestone has been a single row with a status that nothing ever moved off
 * 'todo', so "how far along is this team" had no answer. The Capstone 1
 * checklist is nineteen deliverables, and progress means how many of them are
 * done - which needs somewhere to put them.
 *
 * Four kinds, because the checklist has four kinds, and the kind decides what
 * closes the item:
 *
 *   form      a signed paper the CaPSA/TSA sights - the only kind a human ticks,
 *             and the only kind that writes completed_at here
 *   document  a draft the team uploads; closed by the upload existing
 *   approval  a CLF; closed by the adviser or consultant's verdict
 *   defense   closed by the panel's recorded outcome
 *   custom    whatever a CaPSA/TSA adds by hand, ticked like a form
 *
 * The last three are resolved from the records that already hold the answer
 * rather than copied here, so nothing has to be kept in step and nothing can
 * go stale - the same reason late and overdue are worked out from the dates
 * instead of being written down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestone_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('milestone_id');

            // Stable identity for an item that came from a template, so a
            // revised checklist can be applied again without losing what is
            // already ticked. Null for anything a CaPSA/TSA added by hand.
            $table->string('template_key', 64)->nullable();

            $table->string('title');
            $table->string('kind', 16)->default('form');
            $table->unsignedInteger('position')->default(0);

            // Falls back to the milestone's own due date when not set.
            $table->date('due_date')->nullable();

            // Only ever written for 'form' and 'custom'. The other kinds are
            // answered by the document, review or defense they point at.
            $table->timestamp('completed_at')->nullable();

            // What this item points at, for the kinds that close themselves.
            // Explicit columns rather than a JSON blob so they can be queried
            // and so a typo is visible instead of silently matching nothing.
            $table->string('chapter', 50)->nullable();
            $table->string('role', 32)->nullable();
            $table->string('defense_type', 64)->nullable();

            $table->timestamps();

            $table->foreign('milestone_id')->references('id')->on('milestones')->cascadeOnDelete();
            $table->index(['milestone_id', 'position']);
            $table->index('template_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestone_items');
    }
};
