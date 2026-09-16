<?php

namespace App\Models;

use App\Support\SectionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A member leaving a team whose members are locked, waiting on a CaPSA.
 *
 * See Team::isTeamLocked() for when that is, and TeamLock for what
 * approving one does.
 */
class MemberLeaveRequest extends Model
{
    /** The member asks to leave. */
    public const LEAVE = 'leave';

    /** The leader asks for a member to be removed. */
    public const REMOVE = 'remove';

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    public const WITHDRAWN = 'withdrawn';

    public const REASON_MIN = 15;

    protected $fillable = [
        'team_id',
        'student_id',
        'requested_by',
        'type',
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

    /** The member who would leave. */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
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
     * Through SectionScope, the same as every other CaPSA page, so the list
     * and the approve behind it cannot disagree about whose team it is.
     *
     * @param  Collection<int, self>  $requests
     * @return Collection<int, self>
     */
    public static function visibleTo(CapstoneExpert $capsa, Collection $requests): Collection
    {
        $scope = app(SectionScope::class);
        $sections = $capsa->sections ?? [];

        return $requests
            ->filter(fn (self $r) => $r->team && $scope->covers($sections, $scope->sectionFor($r->team)))
            ->values();
    }

    /** How many are waiting on this CaPSA - the sidebar badge. */
    public static function pendingCountFor(CapstoneExpert $capsa): int
    {
        return static::visibleTo($capsa, static::pending()->with('team')->get())->count();
    }
}
