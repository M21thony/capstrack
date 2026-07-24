<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RubricCriterion extends Model
{
    protected $fillable = [
        'rubric_id',
        'name',
        'max_score',
        'weight',
    ];

    protected $casts = [
        'max_score' => 'float',
        'weight' => 'float',
    ];

    public function rubric()
    {
        return $this->belongsTo(Rubric::class, 'rubric_id');
    }
}
