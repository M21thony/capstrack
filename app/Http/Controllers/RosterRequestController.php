<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\RosterChangeRequest;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\StudentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A student asking their CaPSA for a member to leave a locked team.
 *
 * Only for teams that passed Title Defense. Before that, leaving goes through
 * the leader the way it always has; this is not a second way around them.
 */
class RosterRequestController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([RosterChangeRequest::LEAVE, RosterChangeRequest::REMOVE])],
            'student_id' => ['nullable', 'integer', 'required_if:type,'.RosterChangeRequest::REMOVE],
            'reason' => ['required', 'string', 'min:'.RosterChangeRequest::REASON_MIN, 'max:1000'],
        ], [
            'reason.required' => 'Tell your CaPSA why.',
            'reason.min' => 'Give your CaPSA a reason they can decide on - at least '.RosterChangeRequest::REASON_MIN.' characters.',
        ]);

        $me = $request->user();
        $team = Team::find(StudentTeam::idFor($me->student_id));

        if (! $team) {
            return $this->refuse($request, 'You are not in a team.');
        }

        if (! $team->isRosterLocked()) {
            return $this->refuse($request, 'Your team has not passed Title Defense, so members still leave through the team leader.');
        }

        $isLeader = (int) $team->leader_id === (int) $me->student_id;

        if ($validated['type'] === RosterChangeRequest::LEAVE) {
            // The leader cannot be the one to go: a team with nobody leading it
            // has nobody to schedule, submit or answer for it.
            if ($isLeader) {
                return $this->refuse($request, 'You lead the team. Hand leadership to another member first, then ask to leave.');
            }

            $subjectId = (int) $me->student_id;
        } else {
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
        if (RosterChangeRequest::pending()->where('team_id', $team->team_id)->where('student_id', $subjectId)->exists()) {
            return $this->refuse($request, 'A request about this member is already waiting on your CaPSA.');
        }

        $rosterRequest = RosterChangeRequest::create([
            'team_id' => $team->team_id,
            'student_id' => $subjectId,
            'requested_by' => $me->student_id,
            'type' => $validated['type'],
            'reason' => trim($validated['reason']),
            'status' => RosterChangeRequest::PENDING,
        ]);

        $myName = e(trim($me->f_name.' '.$me->l_name));
        $teamName = '<strong>'.e($team->team_name).'</strong>';

        // Whoever it concerns hears about it from the system, not second hand.
        Notification::create($validated['type'] === RosterChangeRequest::LEAVE
            ? [
                'student_id' => $team->leader_id,
                'type' => 'roster_request_sent',
                'title' => 'Member asked to leave',
                'message' => '<strong>'.$myName.'</strong> asked your CaPSA to let them leave '.$teamName.'.',
                'link' => route('student.team'),
            ]
            : [
                'student_id' => $subjectId,
                'type' => 'roster_request_sent',
                'title' => 'Removal requested',
                'message' => 'Your team leader asked your CaPSA to remove you from '.$teamName.'. '
                    .'Their reason: "'.e($rosterRequest->reason).'" You stay on the team unless your CaPSA approves.',
                'link' => route('student.team'),
            ]);

        $subject = Student::find($subjectId);
        $this->logActivity(
            $me->student_id,
            'roster_request_sent',
            $validated['type'] === RosterChangeRequest::LEAVE
                ? 'Asked the CaPSA to leave '.$teamName
                : 'Asked the CaPSA to remove <strong>'.e(trim(($subject->f_name ?? '').' '.($subject->l_name ?? ''))).'</strong> from '.$teamName,
            ['team_id' => $team->team_id, 'roster_request_id' => $rosterRequest->id]
        );

        $message = 'Sent to your CaPSA. You will be notified when they decide.';

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => $message, 'request' => $rosterRequest], 201)
            : redirect()->route('student.team')->with('status', $message);
    }

    /** Withdrawing is the one change a student makes to a request once it is sent. */
    public function update(Request $request, RosterChangeRequest $rosterRequest): JsonResponse|RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::in([RosterChangeRequest::WITHDRAWN])]]);

        if ((int) $rosterRequest->requested_by !== (int) $request->user()->student_id) {
            return $this->refuse($request, 'Only whoever sent this request can withdraw it.', 403);
        }

        if (! $rosterRequest->isPending()) {
            return $this->refuse($request, 'Your CaPSA has already decided on this request.', 409);
        }

        $rosterRequest->update(['status' => RosterChangeRequest::WITHDRAWN]);

        return $request->expectsJson()
            ? response()->json(['success' => true, 'message' => 'Request withdrawn.'])
            : redirect()->route('student.team')->with('status', 'Request withdrawn.');
    }

    private function refuse(Request $request, string $message, int $status = 422): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['success' => false, 'message' => $message], $status)
            : redirect()->route('student.team')->with('error', $message);
    }
}
