<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A team asking its CaPSA to let go of an expert who already took them on.
 *
 * The leader used to remove an approved adviser, consultant or grammarian on
 * their own. Changing a team's experts now needs the CaPSA's permission, so a
 * row here holds who asked, about which expert, why, and what was decided.
 * Rows are kept after a decision as the record of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expert_removal_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('expert_id');
            $table->unsignedBigInteger('requested_by');
            $table->text('reason');
            $table->string('status', 12)->default('pending');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['assignment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expert_removal_requests');
    }
};
