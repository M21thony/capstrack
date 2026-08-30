<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A CAPSA-uploaded pre-approval list: a student's real CSPC email must
// appear here (see AuthController::handleGoogleCallback()) before their
// Google sign-in is allowed to create a Student account at all.
class StudentRoster extends Model
{
    protected $fillable = [
        'name',
        'email',
        'student_number',
        'section',
        'program',
        'school_year',
        'imported_by',
    ];

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(CapstoneExpert::class, 'imported_by', 'expert_id');
    }
}
