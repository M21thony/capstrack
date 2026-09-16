<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvitationResource;
use App\Models\Notification;
use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Support\StudentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        $invitations = TeamInvitation::with(['team', 'sender', 'receiver'])
            ->where(function ($q) use ($student) {
                $q->where('receiver_id', $student->student_id)
                    ->orWhere('sender_id', $student->student_id);
            })
            ->latest()
            ->paginate(20);

        return InvitationResource::collection($invitations)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:students,student_id'],
            'team_id' => ['nullable', 'integer', 'exists:team,team_id'],
            'type' => ['required', 'string', 'in:invite,request'],
        ]);

        $student = $request->user();
        $studentId = $student->student_id;

        $teamId = $validated['team_id'] ?? StudentTeam::idFor($studentId);

        // Capstone 2 teams are closed. Somebody joining in 4th year was not
        // there for the title, the proposal or any of the work being graded,
        // which is what the department's rule is about. Checked here as well
        // as in the page, because the page can only hide a button.
        // Only when inviting. A request is somebody asking to be let in, and
        // the leader decides on it - refusing at this end would tell the
        // asker the team is full before the leader has even seen them.
        if ($validated['type'] === 'invite') {
            $team = Team::find($teamId);

            if ($team && $reason = $team->inviteBlockReason(1, Student::find($validated['receiver_id']))) {
                return response()->json(['message' => $reason], 422);
            }
        }

        if ($validated['type'] === 'request') {
            if (! Team::joiningAllowed()) {
                return response()->json(['message' => Team::JOINING_CLOSED], 422);
            }

            if ($student->capstoneStage() !== 1) {
                return response()->json(['message' => Team::MEMBERS_SETTLED], 422);
            }

            if (StudentTeam::has($studentId)) {
                return response()->json([
                    'message' => 'You are already in a team. Leave your team first before joining another.',
                ], 422);
            }
        }

        $exists = TeamInvitation::where('sender_id', $studentId)
            ->where('receiver_id', $validated['receiver_id'])
            ->where('type', $validated['type'])
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'A pending invitation or request already exists.',
            ], 422);
        }

        $invitation = TeamInvitation::create([
            'sender_id' => $studentId,
            'receiver_id' => $validated['receiver_id'],
            'team_id' => $validated['team_id'] ?? null,
            'type' => $validated['type'],
            'status' => 'pending',
        ]);

        if ($validated['type'] === 'invite') {
            $this->logActivity(
                $studentId,
                'invitation_sent',
                'Invited <strong>'.e(Student::find($validated['receiver_id'])?->f_name ?? 'a student').'</strong> to join the team',
                ['receiver_id' => $validated['receiver_id'], 'team_id' => $validated['team_id']]
            );

            Notification::create([
                'student_id' => $validated['receiver_id'],
                'type' => 'invitation_received',
                'title' => 'Team Invitation',
                'message' => 'You have been invited to join a team.',
                'link' => route('student.team'),
            ]);
        } else {
            $this->logActivity(
                $studentId,
                'request_sent',
                'Requested to join a team',
                ['team_id' => $validated['team_id']]
            );

            Notification::create([
                'student_id' => $validated['receiver_id'],
                'type' => 'request_received',
                'title' => 'Join Request',
                'message' => ($student->f_name.' '.$student->l_name).' wants to join your team.',
                'link' => route('student.team'),
            ]);
        }

        return response()->json([
            'message' => $validated['type'] === 'invite' ? 'Invitation sent successfully.' : 'Join request sent successfully.',
            'invitation' => new InvitationResource($invitation->load(['sender', 'receiver', 'team'])),
        ], 201);
    }

    /**
     * Accepting and declining are the same thing to the invitation: its
     * status changes. So both arrive here as a PATCH carrying the status
     * they want, and the work stays in the two methods that already did
     * it - this only decides which one runs.
     */
    public function update(Request $request, TeamInvitation $invitation): JsonResponse
    {
        $status = $request->validate([
            'status' => ['required', 'in:accepted,declined'],
        ])['status'];

        return $status === 'accepted'
            ? $this->accept($request, $invitation)
            : $this->decline($request, $invitation);
    }

    /**
     * The role a student joining this team should be given outright.
     *
     * Mirrors the web controller: the team's recruitment post says what they
     * were short of, so one named role that still has a seat is assigned
     * without asking. Two or more is a real choice, and no post at all means
     * nothing to go on - both answer null, and the team page asks once.
     */
    private function roleFromTeamPost(int $teamId): ?int
    {
        $team = Team::find($teamId);

        if (! $team) {
            return null;
        }

        $wanted = collect((array) Recruitment::where('student_id', $team->leader_id)
            ->where('recruitment_goal', 'build_a_team')
            ->value('roles'))->filter()->values();

        if ($wanted->isEmpty()) {
            return null;
        }

        $open = StudentRole::memberRoles()
            ->filter(fn ($role) => $wanted->contains($role->s_role_name))
            ->filter(fn ($role) => StudentRole::hasSeat($teamId, $role->s_role_id))
            ->values();

        return $open->count() === 1 ? $open->first()->s_role_id : null;
    }

    public function accept(Request $request, TeamInvitation $invitation): JsonResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        // Asked of the model, so this and the web accept path cannot drift
        // apart again: joining closed, Capstone 2 begun, or the last seat gone.
        $joining = Team::find($invitation->team_id);

        $joiner = $invitation->type === 'invite' ? $student : Student::find($invitation->sender_id);

        if ($joining && $reason = $joining->joinBlockReason($joiner)) {
            return response()->json(['message' => $reason], 422);
        }

        $memberRoleId = $this->roleFromTeamPost($invitation->team_id);

        if ($invitation->type === 'invite') {
            if ($invitation->receiver_id !== $studentId) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            StudentSchoolYear::where('student_id', $studentId)
                ->update([
                    'team_id' => $invitation->team_id,
                    's_role_id' => $memberRoleId,
                ]);
        } else {
            if ($invitation->receiver_id !== $studentId) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            StudentSchoolYear::where('student_id', $invitation->sender_id)
                ->update([
                    'team_id' => $invitation->team_id,
                    's_role_id' => $memberRoleId,
                ]);
        }

        $invitation->update(['status' => 'accepted']);

        $joinedStudentId = $invitation->type === 'invite' ? $studentId : $invitation->sender_id;
        Recruitment::where('student_id', $joinedStudentId)->delete();

        $team = Team::find($invitation->team_id);
        $joiningStudent = Student::find($joinedStudentId);

        if ($team && $team->team_status === 'Active') {
            $currentCount = StudentSchoolYear::where('team_id', $team->team_id)->count();
            if ($currentCount >= $team->memberLimit()) {
                $team->update(['team_status' => 'Full']);
                $this->logActivity(
                    $team->leader_id,
                    'team_auto_closed',
                    'Team <strong>'.e($team->team_name).'</strong> is now closed (all slots filled)',
                    ['team_id' => $team->team_id]
                );
            }
        }

        if ($team && $joiningStudent) {
            $this->logActivity(
                $joinedStudentId,
                'team_joined',
                'Joined team <strong>'.e($team->team_name).'</strong>',
                ['team_id' => $team->team_id, 'team_name' => $team->team_name]
            );

            if ($invitation->type === 'request') {
                $this->logActivity(
                    $studentId,
                    'request_accepted',
                    'Accepted <strong>'.e($joiningStudent->f_name.' '.$joiningStudent->l_name).'</strong>\'s request to join the team',
                    ['joined_student_id' => $joinedStudentId, 'team_id' => $team->team_id]
                );
            }
        }

        if ($invitation->type === 'invite') {
            Notification::create([
                'student_id' => $invitation->sender_id,
                'type' => 'invitation_accepted',
                'title' => 'Invitation Accepted',
                'message' => ($joiningStudent ? $joiningStudent->f_name.' '.$joiningStudent->l_name : 'Someone').' accepted your invitation to join the team.',
                'link' => route('student.team'),
            ]);
        } else {
            Notification::create([
                'student_id' => $invitation->sender_id,
                'type' => 'request_accepted',
                'title' => 'Request Accepted',
                'message' => 'Your request to join <strong>'.e($team->team_name ?? 'the team').'</strong> has been accepted.',
                'link' => route('student.team'),
            ]);
        }

        $teamMemberIds = StudentSchoolYear::where('team_id', $invitation->team_id)
            ->where('student_id', '!=', $joinedStudentId)
            ->pluck('student_id');

        foreach ($teamMemberIds as $memberId) {
            Notification::create([
                'student_id' => $memberId,
                'type' => 'member_joined',
                'title' => 'New Team Member',
                'message' => ($joiningStudent ? $joiningStudent->f_name.' '.$joiningStudent->l_name : 'Someone').' joined the team.',
                'link' => route('student.team'),
            ]);
        }

        TeamInvitation::where('status', 'pending')
            ->where(function ($query) use ($studentId, $invitation) {
                $targetId = $invitation->type === 'invite' ? $studentId : $invitation->sender_id;
                $query->where('receiver_id', $targetId)
                    ->orWhere('sender_id', $targetId);
            })
            ->update(['status' => 'declined']);

        return response()->json(['message' => 'Invitation accepted.']);
    }

    public function decline(Request $request, TeamInvitation $invitation): JsonResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        if ($invitation->sender_id !== $studentId && $invitation->receiver_id !== $studentId) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $invitation->update(['status' => 'declined']);

        $otherPartyId = $invitation->sender_id === $studentId ? $invitation->receiver_id : $invitation->sender_id;

        if ($invitation->type === 'invite') {
            $this->logActivity(
                $studentId,
                'invitation_declined',
                'Declined invitation to join the team',
                ['invitation_id' => $invitation->id]
            );
        } else {
            $this->logActivity(
                $studentId,
                'request_declined',
                'Declined a join request',
                ['invitation_id' => $invitation->id]
            );
        }

        Notification::create([
            'student_id' => $otherPartyId,
            'type' => $invitation->type === 'invite' ? 'invitation_declined' : 'request_declined',
            'title' => $invitation->type === 'invite' ? 'Invitation Declined' : 'Request Declined',
            'message' => 'Your '.($invitation->type === 'invite' ? 'invitation' : 'join request').' has been declined.',
            'link' => route('student.team'),
        ]);

        return response()->json(['message' => 'Invitation declined.']);
    }
}
