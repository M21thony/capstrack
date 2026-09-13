<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Models\Notification;
use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\Term;
use App\Support\StudentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        $teams = Team::query();

        if ($student->course && $student->year_section) {
            $studentIds = StudentSchoolYear::join('students', 'student_s_y.student_id', '=', 'students.student_id')
                ->where('students.course', $student->course)
                ->where('students.year_section', $student->year_section)
                ->whereNotNull('student_s_y.team_id')
                ->pluck('student_s_y.team_id')
                ->unique();

            $teams->whereIn('team_id', $studentIds);
        }

        $teams = $teams->with('leader', 'members')->latest('created_at')->paginate(20);

        return TeamResource::collection($teams)->response();
    }

    public function store(Request $request): JsonResponse
    {
        // Capstone 2: teams are settled, so there is nothing to recruit for
        // or form. Refused here as well as hidden on the page, and with a
        // reason - an empty list with no explanation reads as a broken page.
        if ($request->user()->capstoneStage() !== 1) {
            return response()->json([
                'message' => 'Capstone 2 has started. Teams keep the members they '
                    .'finished Capstone 1 with, so new teams and recruitment posts are closed.',
            ], 422);
        }

        if (! Team::creationAllowed()) {
            return response()->json(['message' => Team::CREATION_CLOSED], 422);
        }

        $validated = $request->validate([
            'team_name' => ['required', 'string', 'max:100'],
            'team_status' => ['nullable', Rule::in(['Active', 'Full'])],
        ]);

        $student = $request->user();

        if (StudentSchoolYear::where('student_id', $student->student_id)->whereNotNull('team_id')->exists()) {
            return response()->json(['message' => 'You are already in a team.'], 422);
        }

        // No project_title: the title is whichever pre-proposal is approved,
        // not something typed at team creation. Same as the web controller.
        $team = Team::create([
            'team_name' => $validated['team_name'],
            'team_status' => $validated['team_status'] ?? 'Active',
            'leader_id' => $student->student_id,
        ]);

        DB::table('student_s_y')
            ->updateOrInsert(
                // Keyed on the term too: this table is one row per student PER
                // TERM, so keying on the student alone would overwrite last
                // semester's record instead of starting this one.
                ['student_id' => $student->student_id, 'term_id' => Term::activeId()],
                ['team_id' => $team->team_id, 's_role_id' => StudentRole::leaderRoleId()]
            );

        $recruitment = Recruitment::where('student_id', $student->student_id)
            ->where('recruitment_goal', 'build_a_team')
            ->first();
        if ($recruitment) {
            $recruitment->update(['member_count' => 5]);
            $team->update(['member_count' => 5]);
        }

        Recruitment::where('student_id', $student->student_id)
            ->where('recruitment_goal', 'find_a_team')
            ->delete();

        $this->logActivity(
            $student->student_id,
            'team_created',
            'Created team <strong>'.e($team->team_name).'</strong>',
            ['team_id' => $team->team_id, 'team_name' => $team->team_name]
        );

        Notification::create([
            'student_id' => $student->student_id,
            'type' => 'team_created',
            'title' => 'Team Created',
            'message' => 'Your team <strong>'.e($team->team_name).'</strong> has been created.',
            'link' => route('student.team'),
        ]);

        return response()->json([
            'message' => 'Team created successfully.',
            'team' => new TeamResource($team->load('leader', 'members')),
        ], 201);
    }

    public function show(Request $request, Team $team): JsonResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        $team->load('leader', 'members');

        $members = $team->members->sortBy(function ($member) use ($team, $studentId) {
            $isLeader = $member->student_id === (int) $team->leader_id;
            $isSelf = $member->student_id === $studentId;

            if ($isLeader) {
                return 0;
            }
            if ($isSelf) {
                return 1;
            }

            return 2;
        })->values();

        return response()->json([
            'team' => new TeamResource($team),
            'members' => $members,
        ]);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        if ((int) $team->leader_id !== (int) $studentId) {
            return response()->json(['message' => 'Only the team leader can update the team.'], 403);
        }

        if ($team->is_confirmed && $request->has('team_status')) {
            return response()->json(['message' => 'Cannot change status. Team is already verified.'], 403);
        }

        $validated = $request->validate([
            'team_name' => ['required', 'string', 'max:100'],
            'team_status' => ['nullable', Rule::in(['Active', 'Full'])],
        ]);

        $originalStatus = $team->team_status;

        $team->update([
            'team_name' => $validated['team_name'],
            'team_status' => $validated['team_status'] ?? $team->team_status,
            'member_count' => \App\Models\Team::maxMembers(),
        ]);

        Recruitment::where('student_id', $studentId)
            ->where('recruitment_goal', 'build_a_team')
            ->update(['member_count' => 5]);

        $autoClosed = false;
        if ($team->team_status === 'Active') {
            $currentCount = StudentSchoolYear::where('team_id', $team->team_id)->count();
            if ($currentCount >= 5) {
                $team->update(['team_status' => 'Full']);
                $autoClosed = true;
                $this->logActivity(
                    $studentId,
                    'team_auto_closed',
                    'Team <strong>'.e($team->team_name).'</strong> is now closed (all slots filled)',
                    ['team_id' => $team->team_id]
                );
            }
        }

        if (! $autoClosed && $team->team_status !== $originalStatus) {
            $this->logActivity(
                $studentId,
                'team_status_changed',
                'Changed team status from <strong>'.e($originalStatus).'</strong> to <strong>'.e($team->team_status).'</strong>',
                ['team_id' => $team->team_id, 'old' => $originalStatus, 'new' => $team->team_status]
            );
        }

        if ($team->member_count !== 5) {
            $team->update(['member_count' => 5]);
        }

        $this->logActivity(
            $studentId,
            'team_updated',
            'Updated team <strong>'.e($team->team_name).'</strong>',
            ['team_id' => $team->team_id]
        );

        return response()->json([
            'message' => 'Team updated successfully.',
            'team' => new TeamResource($team->load('leader', 'members')),
        ]);
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        $isLeader = (int) $team->leader_id === (int) $studentId;

        if ($team->isRosterLocked()) {
            return response()->json(['message' => Team::ROSTER_LOCKED], 422);
        }

        if ($isLeader) {
            $memberCount = StudentSchoolYear::where('team_id', $team->team_id)->count();

            if ($memberCount > 1) {
                return response()->json([
                    'message' => 'As the team leader, you cannot leave directly. Transfer leadership to another member first.',
                ], 422);
            }

            // Solo leader — terminate the team
            StudentSchoolYear::where('team_id', $team->team_id)->update([
                'team_id' => null,
                's_role_id' => null,
            ]);
            $team->delete();

            return response()->json([
                'message' => 'You have left the team and the team has been terminated.',
            ]);
        }

        // The row holding the team, not whichever row came back first -
        // clearing the wrong year left the student in the team.
        StudentTeam::rowFor($studentId)?->update(['team_id' => null, 's_role_id' => null]);

        $this->logActivity(
            $studentId,
            'team_left',
            'Left team <strong>'.e($team->team_name ?? 'the team').'</strong>',
            ['team_id' => $team->team_id]
        );

        if ($team) {
            $leaderId = $team->leader_id;
            if ($leaderId) {
                Notification::create([
                    'student_id' => $leaderId,
                    'type' => 'member_left',
                    'title' => 'Member Left',
                    'message' => ($student ? $student->f_name.' '.$student->l_name : 'A member').' has left the team.',
                    'link' => route('student.team'),
                ]);
            }
        }

        Recruitment::where('student_id', $studentId)->delete();

        $remainingCount = StudentSchoolYear::where('team_id', $team->team_id)->count();
        if ($remainingCount === 0) {
            TeamInvitation::where('team_id', $team->team_id)->delete();
            $team->delete();
        }

        return response()->json(['message' => 'You have left the team.']);
    }

    public function transferLeadership(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'new_leader_id' => ['required', 'integer', 'exists:students,student_id'],
        ]);

        $authUser = $request->user();
        $authId = $authUser->student_id;

        if ((int) $team->leader_id !== (int) $authId) {
            return response()->json(['message' => 'Only the team leader can transfer leadership.'], 403);
        }

        $newLeaderId = (int) $validated['new_leader_id'];

        if ($newLeaderId === $authId) {
            return response()->json(['message' => 'You are already the leader.'], 422);
        }

        $isMember = StudentSchoolYear::where('student_id', $newLeaderId)
            ->where('team_id', $team->team_id)
            ->exists();

        if (! $isMember) {
            return response()->json(['message' => 'That student is not a member of your team.'], 422);
        }

        // Cancel any existing pending transfer requests for this team so the leader is never stuck
        $oldRequests = Notification::where('type', 'leadership_transfer_request')
            ->where('is_read', false)
            ->where('data->team_id', $team->team_id)
            ->get();

        foreach ($oldRequests as $oldRequest) {
            $oldRequest->update(['is_read' => true]);
            $oldData = $oldRequest->data ?? [];
            $oldPendingLeaderId = (int) ($oldData['old_leader_id'] ?? 0);

            Notification::where('type', 'leadership_transfer_pending')
                ->where('student_id', $oldPendingLeaderId)
                ->where('is_read', false)
                ->where('data->team_id', $team->team_id)
                ->update(['is_read' => true]);
        }

        $newLeader = Student::find($newLeaderId);

        Notification::create([
            'student_id' => $newLeaderId,
            'type' => 'leadership_transfer_request',
            'title' => 'Leadership Transfer Awaiting Approval',
            'message' => '<strong>'.e($authUser->f_name.' '.$authUser->l_name).'</strong> wants to transfer leadership of <strong>'.e($team->team_name).'</strong> to you. Do you accept?',
            'link' => route('student.team'),
            'data' => [
                'team_id' => $team->team_id,
                'team_name' => $team->team_name,
                'old_leader_id' => $authId,
                'new_leader_id' => $newLeaderId,
            ],
        ]);

        Notification::create([
            'student_id' => $authId,
            'type' => 'leadership_transfer_pending',
            'title' => 'Leadership Transfer Pending Approval',
            'message' => 'You requested to transfer leadership of <strong>'.e($team->team_name).'</strong> to <strong>'.e($newLeader?->f_name.' '.$newLeader?->l_name ?? 'another member').'</strong>. Your request is <strong>still pending</strong> until they approve it.',
            'link' => route('student.team'),
            'data' => [
                'team_id' => $team->team_id,
                'team_name' => $team->team_name,
                'old_leader_id' => $authId,
                'new_leader_id' => $newLeaderId,
            ],
        ]);

        $this->logActivity(
            $authId,
            'leadership_transfer_requested',
            'Requested to transfer leadership to <strong>'.e($newLeader?->f_name.' '.$newLeader?->l_name ?? 'another member').'</strong>',
            ['new_leader_id' => $newLeaderId, 'team_id' => $team->team_id]
        );

        return response()->json([
            'message' => 'Leadership transfer request sent to '.($newLeader?->f_name ?? 'the member').'. They must approve it first.',
            'team' => new TeamResource($team->load('leader', 'members')),
        ]);
    }

    /**
     * Same as the web side: one PATCH, the status says accepted or declined.
     */
    public function updateLeadership(Request $request, int $notificationId): JsonResponse
    {
        return $request->validate([
            'status' => ['required', 'in:accepted,declined'],
        ])['status'] === 'accepted'
            ? $this->acceptLeadership($request, $notificationId)
            : $this->declineLeadership($request, $notificationId);
    }

    public function acceptLeadership(Request $request, int $notificationId): JsonResponse
    {
        return $this->respondToLeadershipTransfer($request, $notificationId, true);
    }

    public function declineLeadership(Request $request, int $notificationId): JsonResponse
    {
        return $this->respondToLeadershipTransfer($request, $notificationId, false);
    }

    private function respondToLeadershipTransfer(Request $request, int $notificationId, bool $approve): JsonResponse
    {
        $notification = Notification::find($notificationId);
        $authUser = $request->user();
        $authId = $authUser->student_id;

        if (! $notification || (int) $notification->student_id !== (int) $authId || $notification->type !== 'leadership_transfer_request') {
            return response()->json(['message' => 'Invalid leadership transfer request.'], 403);
        }

        $data = $notification->data ?? [];
        $teamId = (int) ($data['team_id'] ?? 0);
        $oldLeaderId = (int) ($data['old_leader_id'] ?? 0);
        $newLeaderId = (int) ($data['new_leader_id'] ?? 0);

        if ($newLeaderId !== $authId) {
            return response()->json(['message' => 'Invalid leadership transfer request.'], 403);
        }

        $team = Team::find($teamId);

        if (! $team) {
            return response()->json(['message' => 'The team no longer exists.'], 404);
        }

        $teamIdInt = (int) $team->team_id;

        if (! $approve) {
            $notification->update(['is_read' => true]);

            Notification::where('type', 'leadership_transfer_pending')
                ->where('student_id', $oldLeaderId)
                ->where('is_read', false)
                ->where('data->team_id', $teamIdInt)
                ->update(['is_read' => true]);

            $this->logActivity(
                $authId,
                'leadership_transfer_declined',
                'Declined leadership of <strong>'.e($team->team_name).'</strong>',
                ['old_leader_id' => $oldLeaderId, 'team_id' => $teamIdInt]
            );

            Notification::create([
                'student_id' => $oldLeaderId,
                'type' => 'leadership_transfer_declined',
                'title' => 'Leadership Request Declined',
                'message' => '<strong>'.e($authUser->f_name.' '.$authUser->l_name).'</strong> declined your request to transfer leadership of <strong>'.e($team->team_name).'</strong>.',
                'link' => route('student.team'),
            ]);

            return response()->json(['message' => 'You declined the leadership transfer request.']);
        }

        // Make sure leadership hasn't already moved
        if ((int) $team->leader_id !== $oldLeaderId) {
            $notification->update(['is_read' => true]);

            return response()->json(['message' => 'Leadership of this team has already changed.'], 422);
        }

        // Make sure the new leader is still a member
        $isMember = StudentSchoolYear::where('student_id', $newLeaderId)
            ->where('team_id', $teamIdInt)
            ->exists();

        if (! $isMember) {
            $notification->update(['is_read' => true]);

            return response()->json(['message' => 'You are no longer a member of this team.'], 422);
        }

        // Perform the transfer
        $team->update(['leader_id' => $newLeaderId]);

        Recruitment::where('student_id', $oldLeaderId)
            ->where('recruitment_goal', 'build_a_team')
            ->update(['student_id' => $newLeaderId]);
        Recruitment::where('student_id', $newLeaderId)
            ->where('recruitment_goal', '!=', 'build_a_team')
            ->delete();

        // There's no separate "Leader" role - Project Head/Network Engineer
        // is the leader role, and the other two are member roles. On
        // transfer, the new leader takes the Project Head role and the
        // outgoing leader takes over whatever specific role the new leader
        // previously held (so nobody ends up with an undefined role).
        $newLeaderPreviousRoleId = StudentSchoolYear::where('student_id', $newLeaderId)->value('s_role_id');

        StudentSchoolYear::where('student_id', $oldLeaderId)->update(['s_role_id' => $newLeaderPreviousRoleId]);
        StudentSchoolYear::where('student_id', $newLeaderId)->update(['s_role_id' => StudentRole::leaderRoleId()]);

        $oldLeader = Student::find($oldLeaderId);
        $newLeader = Student::find($newLeaderId);
        $this->logActivity(
            $oldLeaderId,
            'leadership_transferred',
            'Transferred leadership to <strong>'.e($newLeader?->f_name.' '.$newLeader?->l_name ?? 'another member').'</strong>',
            ['new_leader_id' => $newLeaderId, 'team_id' => $teamIdInt]
        );

        // Mark request as read and notify both parties
        $notification->update(['is_read' => true]);

        Notification::where('type', 'leadership_transfer_pending')
            ->where('student_id', $oldLeaderId)
            ->where('is_read', false)
            ->where('data->team_id', $teamIdInt)
            ->update(['is_read' => true]);

        Notification::create([
            'student_id' => $oldLeaderId,
            'type' => 'leadership_transferred',
            'title' => 'Leadership Transferred',
            'message' => '<strong>'.e($newLeader?->f_name.' '.$newLeader?->l_name ?? 'The member').'</strong> accepted your request and is now the team leader of <strong>'.e($team->team_name).'</strong>.',
            'link' => route('student.team'),
        ]);

        Notification::create([
            'student_id' => $newLeaderId,
            'type' => 'leadership_transferred',
            'title' => 'Leadership Transferred',
            'message' => 'You are now the team leader of <strong>'.e($team->team_name).'</strong>.',
            'link' => route('student.team'),
        ]);

        return response()->json([
            'message' => 'You are now the team leader of '.$team->team_name.'!',
            'team' => new TeamResource($team->load('leader', 'members')),
        ]);
    }

    public function removeMember(Request $request, Team $team, Student $student): JsonResponse
    {
        $authUser = $request->user();
        $authId = $authUser->student_id;

        if ((int) $team->leader_id !== (int) $authId) {
            return response()->json(['message' => 'Only the team leader can remove members.'], 403);
        }

        if ($team->isRosterLocked()) {
            return response()->json(['message' => Team::ROSTER_LOCKED], 422);
        }

        if ($student->student_id === $authId) {
            return response()->json(['message' => 'You cannot remove yourself. Transfer leadership instead.'], 422);
        }

        $memberSy = StudentSchoolYear::where('student_id', $student->student_id)
            ->where('team_id', $team->team_id)
            ->first();

        if (! $memberSy) {
            return response()->json(['message' => 'That student is not in your team.'], 422);
        }

        $memberSy->update(['team_id' => null, 's_role_id' => null]);

        Recruitment::where('student_id', $student->student_id)->delete();

        TeamInvitation::where(function ($q) use ($student) {
            $q->where('sender_id', $student->student_id)
                ->orWhere('receiver_id', $student->student_id);
        })->where('team_id', $team->team_id)->delete();

        $this->logActivity(
            $authId,
            'member_removed',
            'Removed <strong>'.e($student->f_name.' '.$student->l_name).'</strong> from the team',
            ['removed_student_id' => $student->student_id, 'team_id' => $team->team_id]
        );

        Notification::create([
            'student_id' => $student->student_id,
            'type' => 'member_removed',
            'title' => 'Removed from Team',
            'message' => 'You have been removed from the team <strong>'.e($team->team_name).'</strong>.',
            'link' => route('student.team'),
        ]);

        return response()->json([
            'message' => $student->f_name.' '.$student->l_name.' has been removed from the team.',
            'team' => new TeamResource($team->load('leader', 'members')),
        ]);
    }
}
