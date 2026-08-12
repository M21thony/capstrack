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
        Schema::create('panel_grades', function (Blueprint $table) {
            $table->id('grade_id');
            $table->unsignedBigInteger('defense_id');
            $table->unsignedBigInteger('expert_id');
            $table->unsignedBigInteger('rubric_id');
            // { criterion_id: score } for whichever performance level the
            // panelist picked on each criterion.
            $table->json('scores')->nullable();
            $table->decimal('total_score', 6, 2)->nullable();
            $table->string('verdict')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('defense_id')->references('defense_id')->on('defense_schedule')->cascadeOnDelete();
            $table->foreign('expert_id')->references('expert_id')->on('capstone_experts')->cascadeOnDelete();
            $table->foreign('rubric_id')->references('rubric_id')->on('grading_rubrics')->cascadeOnDelete();

            // One panelist grades a given team's defense stage exactly once
            // (edited via save/submit, not duplicated).
            $table->unique(['defense_id', 'expert_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('panel_grades');
    }
};
