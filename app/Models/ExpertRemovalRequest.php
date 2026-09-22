<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A team asking its CaPSA to remove an expert who already took them on.
 *
 * Decided on the Leave Requests tab of Team Management, beside member leave
 * requests, and scoped to the CaPSA's sections the same way.
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

    /**
     * Only the requests from teams in this CaPSA's sections.
     *
     * @param  Collection<int, self>  $requests
     * @return Collection<int, self>
     */
    public static function visibleTo(CapstoneExpert $capsa, Collection $requests): Collection
    {
        $scope = app(\App\Support\SectionScope::class);
        $sections = $capsa->sections ?? [];

        return $requests
            ->filter(fn (self $r) => $r->team && $scope->covers($sections, $scope->sectionFor($r->team)))
            ->values();
    }

    /** How many are waiting on this CaPSA. */
    public static function pendingCountFor(CapstoneExpert $capsa): int
    {
        return static::visibleTo($capsa, static::pending()->with('team')->get())->count();
    }
}
