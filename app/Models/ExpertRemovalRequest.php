<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A team asking an expert who already took them on to let them go.
 *
 * The team cannot drop that expert by itself. The request goes to the expert
 * it names, who decides it on their Assignments page: releasing the team
 * removes the assignment, declining keeps them on.
 */
class ExpertRemovalRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public const WITHDRAWN = 'withdrawn';

    public const REASON_MIN = 15;

    protected $fillable = [
        'team_id',
        'assignment_id',
        'expert_id',
        'requested_by',
        'reason',
        'status',
        'decided_by',
        'decision_note',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function assignment()
    {
        return $this->belongsTo(TeamExpertAssignment::class, 'assignment_id', 'assignment_id');
    }

    public function expert()
    {
        return $this->belongsTo(CapstoneExpert::class, 'expert_id', 'expert_id');
    }

    public function requester()
    {
        return $this->belongsTo(Student::class, 'requested_by', 'student_id');
    }

    public function decider()
    {
        return $this->belongsTo(CapstoneExpert::class, 'decided_by', 'expert_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
