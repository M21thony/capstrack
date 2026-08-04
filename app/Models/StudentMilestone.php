<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentMilestone extends Model
{
    protected $fillable = [
        'milestone_id',
        'team_id',
        'student_id',
        'file_path',
        'completed_at',
        'is_verified',
        'verified_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
