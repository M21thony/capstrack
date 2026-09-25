<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\MemberLeaveRequest;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\Notifier;
use App\Support\SectionScope;
use App\Support\StudentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A student asking their CaPSA for a member to leave a team.
 *
 * A member leaving always needs the CaPSA. A leader removing someone only
 * does once the team passed Title Defense; before that the leader removes
 * members directly.
 */
class LeaveRequestController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([MemberLeaveRequest::LEAVE, MemberLeaveRequest::REMOVE])],
            'student_id' => ['nullable', 'integer', 'required_if:type,'.MemberLeaveRequest::REMOVE],
            'reason' => ['required', 'string', 'min:'.MemberLeaveRequest::REASON_MIN, 'max:1000'],
        ], [
            'reason.required' => 'Tell your CaPSA why.',
            'reason.min' => 'Give your CaPSA a reason they can decide on - at least '.MemberLeaveRequest::REASON_MIN.' characters.',
        ]);

        $me = $request->user();
        $team = Team::find(StudentTeam::idFor($me->student_id));

        if (! $team) {
            return $this->refuse($request, 'You are not in a team.');
        }

        $isLeader = (int) $team->leader_id === (int) $me->student_id;

        if ($validated['type'] === MemberLeaveRequest::LEAVE) {
            // The leader cannot be the one to go: a team with nobody leading it
            // has nobody to schedule, submit or answer for it.
            if ($isLeader) {
                return $this->refuse($request, 'You lead the team. Hand leadership to another member first, then ask to leave.');
            }

            $subjectId = (int) $me->student_id;
        } else {
            if (! $team->isTeamLocked()) {
                return $this->refuse($request, 'Your team has not passed Title Defense, so you can remove members yourself.');
            }

            if (! $isLeader) {
                return $this->refuse($request, 'Only the team leader can ask for a member to be removed.', 403);
            }

            $subjectId = (int) $validated['student_id'];

            if ($subjectId === (int) $me->student_id) {
                return $this->refuse($request, 'You cannot remove yourself. Hand leadership to another member, then ask to leave.');
            }

            if (! StudentSchoolYear::where('student_id', $subjectId)->where('team_id', $team->team_id)->exists()) {
                return $this->refuse($request, 'That student is not on your team.');
            }
        }

        // One question about a member at a time. A leave and a removal of the
        // same person both pending would let the CaPSA approve one and leave
        // the other hanging over someone who is already gone.
        if (MemberLeaveRequest::pending()->where('team_id', $team->team_id)->where('student_id', $subjectId)->exists()) {
            return $this->refuse($request, 'A request about this member is already waiting on your CaPSA.');
        }

        $leaveRequest = MemberLeaveRequest::create([
            'team_id' => $team->team_id,
            'student_id' => $subjectId,
            'requested_by' => $me->student_id,
            'type' => $validated['type'],
            'reason' => trim($validated['reason']),
            'status' => MemberLeaveRequest::PENDING,
        ]);

        $myName = e(trim($me->f_name.' '.$me->l_name));
        $teamName = '<strong>'.e($team->team_name).'</strong>';

        // Whoever it concerns hears about it from the system, not second hand.
        Notification::create($validated['type'] === MemberLeaveRequest::LEAVE
            ? [
                'student_id' => $team->leader_id,
                'type' => 'leave_request_sent',
                'title' => 'Member asked to leave',
                'message' => '<strong>'.$myName.'</strong> asked your CaPSA to let them leave '.$teamName.'.',
                'link' => route('student.team'),
            ]
            : [
                'student_id' => $subjectId,
                'type' => 'leave_request_sent',
                'title' => 'Removal requested',
                'message' => 'Your team leader asked your CaPSA to remove you from '.$teamName.'. '
                    .'Their reason: "'.e($leaveRequest->reason).'" You stay on the team unless your CaPSA approves.',
                'link' => route('student.team'),
            ]);

        $subject = Student::find($subjectId);
        $this->notifyCapsa($team, $leaveRequest, $myName, e(trim(($subject->f_name ?? '').' '.($subject->l_name ?? ''))));

        $this->logActivity(
            $me->student_id,
            'leave_request_sent',
            $validated['type'] === MemberLeaveRequest::LEAVE
                ? 'Asked the CaPSA to leave '.$teamName
                : 'Asked the CaPSA to remove <strong>'.e(trim(($subject->f_name ?? '').' '.($subject->l_name ?? ''))).'</strong> from '.$teamName,
            ['team_id' => $team->team_id, 'leave_request_id' => $leaveRequest->id]
        );

        $message = 'Sent to your CaPSA. You will be notified when they decide.';

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message, 'request' => $leaveRequest], 201)
            : redirect()->route('student.team')->with('status', $message);
    }

    /** Withdrawing is the one change a student makes to a request once it is sent. */
    public function update(Request $request, MemberLeaveRequest $leaveRequest): JsonResponse|RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::in([MemberLeaveRequest::WITHDRAWN])]]);

        if ((int) $leaveRequest->requested_by !== (int) $request->user()->student_id) {
            return $this->refuse($request, 'Only whoever sent this request can withdraw it.', 403);
        }

        if (! $leaveRequest->isPending()) {
            return $this->refuse($request, 'Your CaPSA has already decided on this request.', 409);
        }

        $leaveRequest->update(['status' => MemberLeaveRequest::WITHDRAWN]);

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Request withdrawn.'])
            : redirect()->route('student.team')->with('status', 'Request withdrawn.');
    }

    /**
     * The CaPSA decides, so the CaPSA hears about it with the reason in hand.
     * It goes to every CaPSA who can see the request on their Leave Requests
     * tab - the same section rule MemberLeaveRequest::visibleTo() applies.
     */
    private function notifyCapsa(Team $team, MemberLeaveRequest $leaveRequest, string $requesterName, string $subjectName): void
    {
        $scope = app(SectionScope::class);
        $section = $scope->sectionFor($team);

        $capsas = CapstoneExpert::whereHas('roles', fn ($q) => $q->where('role_name', CapstoneExpert::CAPSA_ROLE))
            ->get()
            ->filter(fn (CapstoneExpert $capsa) => $scope->covers($capsa->sections ?? [], $section));

        $teamName = '<strong>'.e($team->team_name).'</strong>'.($section !== '' ? ' ('.e($section).')' : '');
        $isLeave = $leaveRequest->type === MemberLeaveRequest::LEAVE;

        foreach ($capsas as $capsa) {
            Notifier::toExpert(
                $capsa,
                'leave_request_received',
                $isLeave ? 'Student asked to leave a team' : 'Leader asked to remove a member',
                ($isLeave
                    ? '<strong>'.$requesterName.'</strong> asked to leave '.$teamName.'.'
                    : '<strong>'.$requesterName.'</strong> asked to remove <strong>'.$subjectName.'</strong> from '.$teamName.'.')
                    .' Reason: "'.e($leaveRequest->reason).'"',
                route('capsa.team_management', ['tab' => 'leave_requests']),
                ['leave_request_id' => $leaveRequest->id, 'team_id' => $team->team_id],
            );
        }
    }

    private function refuse(Request $request, string $message, int $status = 422): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => $message], $status)
            : redirect()->route('student.team')->with('error', $message);
    }
}
