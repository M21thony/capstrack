<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the earlier Rubric/RubricController scaffolding, superseded by
 * the complete-grading-rubrics-crud branch's GradingRubric/RubricCriterion
 * feature (see the migration right after this one). Both tables were empty
 * - nothing built on top of the old feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rubric_criteria');
        Schema::dropIfExists('rubrics');
    }

    public function down(): void
    {
        // Intentionally irreversible - the old schema is gone for good,
        // replaced by grading_rubrics/rubric_criteria.
    }
};
