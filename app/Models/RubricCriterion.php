<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricCriterion extends Model
{
    protected $table = 'rubric_criteria';

    protected $primaryKey = 'criterion_id';

    public $timestamps = false;

    protected $fillable = [
        'rubric_id',
        'criterion_name',
        'description',
        'category',
        'category_max_points',
        'max_points',
        'score_levels',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_points' => 'decimal:2',
            'category_max_points' => 'decimal:2',
            'score_levels' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(GradingRubric::class, 'rubric_id', 'rubric_id');
    }

    // The 'array' cast on score_levels doesn't reliably decode it in every
    // context (AdminGradingRubricController::formatCriterion() already
    // works around the same thing) - always go through this instead of
    // reading score_levels directly.
    public function decodedScoreLevels(): array
    {
        $levels = $this->score_levels;

        if (is_string($levels)) {
            return json_decode($levels, true) ?? [];
        }

        return is_array($levels) ? $levels : [];
    }
}
