<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A member leaving a team whose members are locked.
 *
 * Passing Title Defense locks a team: the people on it are the people who
 * earned the title. A leader can no longer let someone go, and a member can
 * no longer walk out on the 3-hour timer a leave request used to run on.
 * People still drop out, though, so a row here is how that reaches the
 * section's CaPSA - who asked, about whom, and why - and what they decided.
 *
 * Rows are never deleted. Withdrawn and declined requests stay, so a CaPSA
 * looking at a team later can see what was asked before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            // The member who would leave the team.
            $table->unsignedBigInteger('student_id');
            // The member themself for "leave", the leader for "remove".
            $table->unsignedBigInteger('requested_by');
            $table->string('type', 10);
            $table->text('reason');
            $table->string('status', 12)->default('pending');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_change_requests');
    }
};
