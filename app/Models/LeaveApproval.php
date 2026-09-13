<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveApproval extends Model
{
    protected $table = 'leave_approvals';

    const STATUS_PENDING = 'pending';

    const STATUS_APPROVED = 'approved';

    const STATUS_DECLINED = 'declined';

    /** Closed unanswered because the team passed Title Defense - see RosterLock. */
    const STATUS_CANCELLED = 'cancelled';

    const MAX_ATTEMPTS = 3;

    const EXPIRY_HOURS = 3;

    protected $fillable = [
        'student_id',
        'team_id',
        'attempt',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && now()->greaterThanOrEqualTo($this->expires_at);
    }
}
