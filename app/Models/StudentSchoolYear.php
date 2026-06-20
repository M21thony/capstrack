<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSchoolYear extends Model
{
    protected $table = 'student_s_y';

    protected $primaryKey = 's_y_id';

    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'team_id',
        's_role_id',
        'school_year',
        'program',
        'student_status',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }
}
