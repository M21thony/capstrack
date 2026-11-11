<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A change request names who the team wants instead. When the CaPSA/TSA approves
 * it, the application to that expert goes out at once - if they still have
 * room - and they accept or decline it like any other.
 *
 * replacement_outcome records what happened then: 'sent', or 'unavailable'
 * when the replacement could no longer take the team.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expert_removal_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('replacement_expert_id')->nullable()->after('reason');
            $table->unsignedBigInteger('replacement_expert_role_id')->nullable()->after('replacement_expert_id');
            $table->text('replacement_message')->nullable()->after('replacement_expert_role_id');
            $table->string('replacement_proposal_file')->nullable()->after('replacement_message');
            $table->unsignedBigInteger('replacement_assignment_id')->nullable()->after('replacement_proposal_file');
            $table->string('replacement_outcome', 20)->nullable()->after('replacement_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::table('expert_removal_requests', function (Blueprint $table) {
            $table->dropColumn([
                'replacement_expert_id', 'replacement_expert_role_id', 'replacement_message',
                'replacement_proposal_file', 'replacement_assignment_id', 'replacement_outcome',
            ]);
        });
    }
};
