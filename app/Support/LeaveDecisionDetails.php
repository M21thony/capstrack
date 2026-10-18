<?php

namespace App\Support;

use App\Models\MemberLeaveRequest;
use App\Models\Notification;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * What a student is shown on tapping a notification about their CaPSA's
 * decision on a leave or removal request: the decision, the CaPSA's reason,
 * who decided and when, and the request it answered.
 *
 * Newer notifications name their request (data.leave_request_id). Older ones
 * do not, so they are matched to the student's own request that the CaPSA
 * decided closest before the notification went out.
 */
class LeaveDecisionDetails
{
    public const TYPES = ['leave_request_approved', 'leave_request_declined'];

    /**
     * @param  Collection<int, Notification>  $notifications
     * @return array<int, array<string, string|null>> keyed by notification id
     */
    public static function forNotifications(Collection $notifications, int $studentId): array
    {
        $decisions = $notifications->filter(fn (Notification $n) => in_array($n->type, self::TYPES, true));

        if ($decisions->isEmpty()) {
            return [];
        }

        $named = MemberLeaveRequest::with(['team', 'student', 'requester', 'decider'])
            ->whereIn('id', $decisions->map(fn (Notification $n) => $n->data['leave_request_id'] ?? null)->filter()->unique())
            ->get()
            ->keyBy('id');

        // For the older notifications: this student's own decided requests.
        $own = MemberLeaveRequest::with(['team', 'student', 'requester', 'decider'])
            ->where(fn ($q) => $q->where('requested_by', $studentId)->orWhere('student_id', $studentId))
            ->whereIn('status', [MemberLeaveRequest::APPROVED, MemberLeaveRequest::DECLINED])
            ->whereNotNull('decided_at')
            ->orderByDesc('decided_at')
            ->get();

        $details = [];

        foreach ($decisions as $notification) {
            $id = $notification->data['leave_request_id'] ?? null;
            $status = $notification->type === 'leave_request_approved' ? MemberLeaveRequest::APPROVED : MemberLeaveRequest::DECLINED;
            $request = $id
                ? $named->get($id)
                // The decision of its kind nearest in time to the notification
                // (the two are written together), else the latest. Nearest,
                // not "just before": a notification's time can come from the
                // database's clock and a decision's from the app's.
                : $own->where('status', $status)
                    ->sortBy(fn (MemberLeaveRequest $r) => $notification->created_at
                        ? abs($r->decided_at->diffInSeconds($notification->created_at, false))
                        : -$r->decided_at->timestamp)
                    ->first();

            if ($request) {
                $details[$notification->id] = self::describe($request, $studentId);
            }
        }

        return $details;
    }

    /** @return array<string, string|null> */
    private static function describe(MemberLeaveRequest $request, int $studentId): array
    {
        $name = fn (?Student $s) => $s ? (trim($s->f_name.' '.$s->l_name) ?: $s->email) : null;
        $isLeave = $request->type === MemberLeaveRequest::LEAVE;
        $decider = $request->decider;

        return [
            'status' => $request->status,
            'kind' => $isLeave ? 'Request to leave the team' : 'Request to remove a member',
            'team' => $request->team?->team_name,
            'member' => $name($request->student),
            'requestedBy' => (int) $request->requested_by === $studentId ? 'You' : $name($request->requester),
            'reason' => $request->reason,
            'requestedOn' => $request->created_at?->format('F j, Y · g:i A'),
            'decisionNote' => $request->decision_note,
            // Declined by the clock: nobody decided, and the popup says so.
            'auto' => (bool) $request->auto_declined,
            'autoDays' => MemberLeaveRequest::DECIDE_WITHIN_DAYS,
            'decidedBy' => $decider ? trim(($decider->first_name ?? '').' '.($decider->last_name ?? '')) ?: 'Your CaPSA' : 'Your CaPSA',
            'decidedOn' => $request->decided_at?->format('F j, Y · g:i A'),
        ];
    }
}
