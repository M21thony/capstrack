<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('capstone_pre_proposals', function (Blueprint $table) {
            $table->id();
            // NOT unique - a team submits up to 3 of these (one per slot).
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('duplication_check_id');
            $table->unsignedTinyInteger('slot');
            $table->string('title', 150);
            $table->string('sdg_goals');
            $table->json('research_agenda');
            $table->text('problem_statement');
            $table->text('proposed_solution');
            $table->text('scope');
            $table->text('limitations');
            $table->string('sdm');
            // Informational only, auto-set from the linked check's verdict -
            // the actual gate is the CAPSA's approved_status/rejection_reason
            // below, not this. Mirrors the real form: the duplication mark
            // sits next to the human "Validated by" signature, it doesn't
            // replace it.
            $table->string('duplication_status')->nullable();
            // Same shape as capstone_experts (approved_status + rejection_reason)
            // collapsed at read time into pending/approved/rejected -
            // see AdminApprovalController for the exact convention this mirrors.
            $table->boolean('approved_status')->default(false);
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('team_id')->references('team_id')->on('team')->onDelete('cascade');
            $table->foreign('duplication_check_id')->references('id')->on('duplication_checks')->onDelete('cascade');
            $table->unique(['team_id', 'slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capstone_pre_proposals');
    }
};
