<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingRubric extends Model
{
    protected $table = 'grading_rubrics';

    protected $primaryKey = 'rubric_id';

    protected $fillable = [
        'rubric_name',
        'rubric_type',
        'description',
        'total_points',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'total_points' => 'decimal:2',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class, 'rubric_id', 'rubric_id');
    }
}
