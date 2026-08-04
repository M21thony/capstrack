<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rubric extends Model
{
    protected $fillable = [
        'admin_id',
        'name',
        'type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function criteria()
    {
        return $this->hasMany(RubricCriterion::class, 'rubric_id');
    }

    public function admin()
    {
        return $this->belongsTo(CapstoneExpert::class, 'admin_id', 'expert_id');
    }
}
