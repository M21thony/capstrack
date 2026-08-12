<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defense_panels', function (Blueprint $table) {
            $table->id('defense_panel_id');
            $table->unsignedBigInteger('defense_id');
            $table->unsignedBigInteger('expert_id');
            $table->enum('panel_role', [
                'Panel Chair',
                'Panel Member 1',
                'Panel Member 2',
            ])->nullable();
            $table->dateTime('created_at')->nullable();

            $table->foreign('defense_id')->references('defense_id')->on('defense_schedule')->onDelete('cascade');
            // The approved ERD's FK line for expert_id points at
            // expert_roles.expert_id, but expert_roles is a plain pivot
            // table (expert_id, role_id) with no unique index on expert_id
            // alone, so that target isn't actually a valid FK reference -
            // almost certainly a mis-dragged relationship line in
            // Workbench. Every other expert FK in this codebase
            // (team_expert_assignments, expert_expertise) targets
            // capstone_experts.expert_id, so this does the same.
            $table->foreign('expert_id')->references('expert_id')->on('capstone_experts')->onDelete('cascade');
            $table->unique(['defense_id', 'expert_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defense_panels');
    }
};
