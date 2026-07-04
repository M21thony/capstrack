<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $table = 'team';

    protected $primaryKey = 'team_id';

    public $timestamps = false;

    protected $fillable = [
        'team_name',
        'project_title',
        'team_status',
        'leader_id',
        'is_confirmed',
        'member_count',
    ];

    protected $casts = [
        'is_confirmed' => 'boolean',
        'member_count' => 'integer',
    ];

    public function members()
    {
        return $this->hasManyThrough(
            Student::class,
            StudentSchoolYear::class,
            'team_id',
            'student_id',
            'team_id',
            'student_id'
        );
    }

    public function leader()
    {
        return $this->belongsTo(Student::class, 'leader_id', 'student_id');
    }
}
