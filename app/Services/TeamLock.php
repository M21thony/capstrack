<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\CapstoneExpert;
use App\Models\LeaveApproval;
use App\Models\MemberLeaveRequest;
use App\Models\Notification;
use App\Models\Recruitment;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;

/**
 * What passing Title Defense does to a team's members.
 *
 * Title Defense is where Capstone 1 ends. The title is approved, and the
 * people on the team are the people who earned it, so from then on nobody
 * joins and nobody leaves on their own say-so.
 *
 * Locking is not just refusing at the doors (Team::isTeamLocked() does
 * that). Things already in motion would otherwise finish on their own: an
 * invitation sent last week could still be accepted, and a leave request
 * would still approve itself when its 3-hour timer ran out. lock() closes
 * all of it once, at the moment of the pass, and tells everyone it touched.
 */
class TeamLock
{
    public function lock(Team $team): void
    {
        $memberIds = StudentSchoolYear::where('team_id', $team->team_id)->pluck('student_id');
        $teamName = '<strong>'.e($team->team_name).'</strong>';

        // Invitations out and join requests in. A request can be sent without
        // a team id, addressed only to the leader, so those are caught by who
        // received them.
        $open = TeamInvitation::where('status', 'pending')
            ->where(fn ($q) => $q->where('team_id', $team->team_id)
                ->orWhere(fn ($r) => $r->where('type', 'request')->whereIn('receiver_id', $memberIds)))
            ->get();

        foreach ($open as $invitation) {
            $invitation->update(['status' => 'declined']);
            $isInvite = $invitation->type === 'invite';

            Notification::create([
                'student_id' => $isInvite ? $invitation->receiver_id : $invitation->sender_id,
                'type' => 'team_locked',
                'title' => $isInvite ? 'Invitation closed' : 'Join request closed',
                'message' => $teamName.' passed Title Defense, so its members are locked. Your '
                    .($isInvite ? 'invitation' : 'join request').' was closed.',
                'link' => route('student.team'),
            ]);
        }

        // Nothing left to recruit for.
        Recruitment::whereIn('student_id', $memberIds)->delete();

        // Leave requests waiting on the leader. The leader no longer decides
        // them, and the timer must not decide them either.
        $leaves = LeaveApproval::where('team_id', $team->team_id)
            ->where('status', LeaveApproval::STATUS_PENDING)
            ->get();

        foreach ($leaves as $leave) {
            $leave->update(['status' => LeaveApproval::STATUS_CANCELLED]);

            Notification::create([
                'student_id' => $leave->student_id,
                'type' => 'team_locked',
                'title' => 'Leave request closed',
                'message' => 'Your request to leave '.$teamName.' was closed because the team passed Title Defense. '
                    .'If you still need to leave, ask your CaPSA from the Team page.',
                'link' => route('student.team'),
            ]);
        }

        foreach ($memberIds as $memberId) {
            Notification::create([
                'student_id' => $memberId,
                'type' => 'team_locked',
                'title' => 'Team members locked',
                'message' => $teamName.' passed Title Defense, so its members are locked. No one can join, '
                    .'and a member can only leave with your CaPSA\'s approval.',
                'link' => route('student.team'),
            ]);
        }
    }

    /** Take a member off a team, as a CaPSA approved. */
    public function release(MemberLeaveRequest $request, CapstoneExpert $capsa, ?string $note): void
    {
        $team = $request->team;
        $member = $request->student;
        $name = e(trim(($member->f_name ?? '').' '.($member->l_name ?? '')) ?: 'A member');
        $teamName = '<strong>'.e($team->team_name).'</strong>';
        $noteText = $note ? ' Their note: "'.e($note).'"' : '';

        StudentSchoolYear::where('student_id', $request->student_id)
            ->where('team_id', $request->team_id)
            ->update(['team_id' => null, 's_role_id' => null]);
        $team->reopenIfRoom();

        Recruitment::where('student_id', $request->student_id)->delete();

        $request->update([
            'status' => MemberLeaveRequest::APPROVED,
            'decided_by' => $capsa->expert_id,
            'decision_note' => $note,
            'decided_at' => now(),
        ]);

        Notification::create([
            'student_id' => $request->student_id,
            'type' => 'leave_request_approved',
            'title' => $request->type === MemberLeaveRequest::LEAVE ? 'Leave approved' : 'Removed from team',
            'message' => ($request->type === MemberLeaveRequest::LEAVE
                ? 'Your CaPSA approved your request to leave '.$teamName.'.'
                : 'Your CaPSA approved your leader\'s request to remove you from '.$teamName.'.')
                .' You are no longer on the team.'.$noteText,
            'link' => route('student.team'),
            // Which request, so tapping it can show the whole decision.
            'data' => ['leave_request_id' => $request->id],
        ]);

        foreach (StudentSchoolYear::where('team_id', $request->team_id)->pluck('student_id') as $memberId) {
            Notification::create([
                'student_id' => $memberId,
                'type' => 'leave_request_approved',
                'title' => 'Member left the team',
                'message' => '<strong>'.$name.'</strong> is no longer on '.$teamName.'. Your CaPSA approved it.'.$noteText,
                'link' => route('student.team'),
                'data' => ['leave_request_id' => $request->id],
            ]);
        }

        ActivityLog::create([
            'student_id' => $request->student_id,
            'type' => 'team_left',
            'description' => 'Left team '.$teamName.' with CaPSA approval',
            'metadata' => ['team_id' => $request->team_id, 'leave_request_id' => $request->id],
            'created_at' => now(),
        ]);

        // The last one out ends the team; deleting it tells its experts.
        if (! StudentSchoolYear::where('team_id', $request->team_id)->exists()) {
            $team->delete();

            return;
        }

        // The experts working with the team hear who is no longer on it.
        foreach ($team->expertAssignments()->where('status', 'approved')->with('expert')->get() as $assignment) {
            Notifier::toExpert(
                $assignment->expert,
                'team_member_left',
                'Member left a team',
                '<strong>'.$name.'</strong> is no longer on '.$teamName.'. Their CaPSA approved it.',
                route('instructor.teams'),
                ['team_id' => $team->team_id],
            );
        }
    }
}
