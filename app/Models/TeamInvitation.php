<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvitation extends Model
{
    protected $table = 'team_invitations';

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'team_id',
        'type',
        'status',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'sender_id', 'student_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'receiver_id', 'student_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }
}
