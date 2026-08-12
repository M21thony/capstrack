<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelGrade extends Model
{
    protected $table = 'panel_grades';

    protected $primaryKey = 'grade_id';

    protected $fillable = [
        'defense_id',
        'expert_id',
        'rubric_id',
        'scores',
        'total_score',
        'verdict',
        'remarks',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'total_score' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function schedule()
    {
        return $this->belongsTo(DefenseSchedule::class, 'defense_id', 'defense_id');
    }

    public function expert()
    {
        return $this->belongsTo(CapstoneExpert::class, 'expert_id', 'expert_id');
    }

    public function rubric()
    {
        return $this->belongsTo(GradingRubric::class, 'rubric_id', 'rubric_id');
    }
}
