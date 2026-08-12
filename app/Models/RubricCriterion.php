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
        'max_points',
        'score_levels',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_points' => 'decimal:2',
            'score_levels' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(GradingRubric::class, 'rubric_id', 'rubric_id');
    }
}
