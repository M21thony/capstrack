<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicationCheck extends Model
{
    protected $fillable = [
        'student_s_y_id',
        'document_path',
        'concepts',
        'top_score',
        'verdict',
        'matches',
        'instructors_suggested',
    ];

    protected $casts = [
        'concepts' => 'array',
        'matches' => 'array',
        'instructors_suggested' => 'array',
        'top_score' => 'integer',
    ];

    public function studentSchoolYear(): BelongsTo
    {
        return $this->belongsTo(StudentSchoolYear::class, 'student_s_y_id', 's_y_id');
    }

    public function team(): ?Team
    {
        return $this->studentSchoolYear->team;
    }

    public function student(): ?Student
    {
        return $this->studentSchoolYear->student;
    }
}
