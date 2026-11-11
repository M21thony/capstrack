<?php

namespace App\Models;

use App\Support\SectionScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A member leaving a team whose members are locked, waiting on a CaPSA/TSA.
 *
 * The CaPSA/TSA has DECIDE_WITHIN_DAYS to decide. A request left that long is
 * declined on its own (auto_declined) and the team is told it ran out of
 * time - not that the CaPSA/TSA said no.
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

    public const ASK_CAPSA = "Leaving a team needs your CaPSA/TSA's approval. Use Ask CaPSA/TSA to Leave on the Team page.";

    /** A CaPSA/TSA has this many days to decide; after that the request is declined on its own. */
    public const DECIDE_WITHIN_DAYS = 3;

    /** The reason an auto-decline carries, where a CaPSA/TSA's own would be. */
    public const AUTO_DECLINE_NOTE = 'Declined automatically: your CaPSA/TSA did not act on it within '.self::DECIDE_WITHIN_DAYS.' days.';

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
        'auto_declined',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'auto_declined' => 'boolean',
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

    /** When a pending request declines itself if the CaPSA/TSA has not decided. */
    public function autoDeclinesAt(): Carbon
    {
        return $this->created_at->copy()->addDays(self::DECIDE_WITHIN_DAYS);
    }

    /**
     * Declines every request the CaPSA/TSA left waiting DECIDE_WITHIN_DAYS or
     * longer, and tells the team. Run on a schedule and before any page or
     * decision reads the requests, so a CaPSA/TSA can never approve one that has
     * already run out, whether or not the scheduler is running.
     *
     * @return int how many were declined
     */
    public static function expireOverdue(): int
    {
        $overdue = static::pending()
            ->where('created_at', '<=', now()->subDays(self::DECIDE_WITHIN_DAYS))
            ->with('team')
            ->get();

        foreach ($overdue as $request) {
            // Only if it is still pending: two runs at once decline it once.
            $declined = static::whereKey($request->getKey())->pending()->update([
                'status' => self::DECLINED,
                'decided_by' => null,
                'decision_note' => self::AUTO_DECLINE_NOTE,
                'decided_at' => $request->autoDeclinesAt(),
                'auto_declined' => true,
                'updated_at' => now(),
            ]);

            if ($declined && $request->team) {
                $request->refresh()->tellDeclined();
            }
        }

        return $overdue->count();
    }

    /**
     * Tells the one who asked - and, for a removal, the member it was about -
     * that the request was declined, by the CaPSA/TSA or by running out of time.
     */
    public function tellDeclined(): void
    {
        $team = $this->team;
        $teamName = '<strong>'.e($team->team_name).'</strong>';
        $isLeave = $this->type === self::LEAVE;

        if ($this->auto_declined) {
            $why = ' Your CaPSA/TSA did not act on it within '.self::DECIDE_WITHIN_DAYS.' days, so it was declined automatically. You can ask again.';
            $requester = [
                'title' => 'Request declined automatically',
                'message' => ($isLeave
                    ? 'Your request to leave '.$teamName.' was declined automatically. You are still on the team.'
                    : 'Your request to remove a member from '.$teamName.' was declined automatically.').$why,
            ];
            $member = [
                'title' => 'You stay on the team',
                'message' => 'The request to remove you from '.$teamName.' was declined automatically: your CaPSA/TSA did not act on it within '.self::DECIDE_WITHIN_DAYS.' days.',
            ];
        } else {
            $why = ' Their reason: "'.e((string) $this->decision_note).'"';
            $requester = [
                'title' => 'Request declined',
                'message' => ($isLeave
                    ? 'Your CaPSA/TSA declined your request to leave '.$teamName.'. You are still on the team.'
                    : 'Your CaPSA/TSA declined your request to remove a member from '.$teamName.'.').$why,
            ];
            $member = [
                'title' => 'You stay on the team',
                'message' => 'Your CaPSA/TSA declined the request to remove you from '.$teamName.'.'.$why,
            ];
        }

        Notification::create($requester + [
            'student_id' => $this->requested_by,
            'type' => 'leave_request_declined',
            'link' => route('student.team'),
            // Which request, so tapping it can show the whole decision.
            'data' => ['leave_request_id' => $this->id],
        ]);

        // The member a removal was about was told it was asked; they are told
        // how it ended too.
        if (! $isLeave) {
            Notification::create($member + [
                'student_id' => $this->student_id,
                'type' => 'leave_request_declined',
                'link' => route('student.team'),
                'data' => ['leave_request_id' => $this->id],
            ]);
        }
    }

    /**
     * Only the requests from teams in this CaPSA/TSA's sections.
     *
     * Through SectionScope, the same as every other CaPSA/TSA page, so the list
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

    /** How many are waiting on this CaPSA/TSA - the sidebar badge. */
    public static function pendingCountFor(CapstoneExpert $capsa): int
    {
        return static::visibleTo($capsa, static::pending()->with('team')->get())->count();
    }
}
