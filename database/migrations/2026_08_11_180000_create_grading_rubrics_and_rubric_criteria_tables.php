<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The grading-rubrics-CRUD feature (GradingRubric/RubricCriterion models,
 * AdminGradingRubricController) shipped without a real migration - its own
 * test suite provisions these two tables ad hoc in setUp() instead. This
 * migration reproduces that exact schema for real environments, since a
 * fresh `migrate` never created them otherwise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_rubrics', function (Blueprint $table) {
            $table->id('rubric_id');
            $table->string('rubric_name');
            $table->string('rubric_type');
            $table->text('description')->nullable();
            $table->decimal('total_points', 6, 2)->default(100.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id('criterion_id');
            $table->unsignedBigInteger('rubric_id');
            $table->string('criterion_name');
            $table->text('description')->nullable();
            $table->decimal('max_points', 6, 2);
            $table->text('score_levels');
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->foreign('rubric_id')->references('rubric_id')->on('grading_rubrics')->cascadeOnDelete();
            $table->index('rubric_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rubric_criteria');
        Schema::dropIfExists('grading_rubrics');
    }
};
