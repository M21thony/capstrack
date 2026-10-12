<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Support\StudentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:students,student_id'],
            'team_id' => ['nullable', 'integer', 'exists:team,team_id'],
            'type' => ['required', 'string', 'in:invite,request'],
        ]);

        $student = $request->user();
        $studentId = $student->student_id;

        // The rule lives on the team because there are two of these
        // controllers and it was written into the other one, which this page
        // does not post to - so a team of two could be sent three invitations
        // for two seats and every one went through.
        //
        // Only for invitations. A request is somebody asking to be let in and
        // the leader decides on it; refusing here would answer for a leader
        // who has not seen them.
        if ($validated['type'] === 'invite') {
            $team = Team::find($validated['team_id'] ?? StudentTeam::idFor($studentId));

            if ($team && $reason = $team->inviteBlockReason(1, Student::find($validated['receiver_id']))) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $reason], 422);
                }

                return back()->with('error', $reason);
            }
        }

        // If the sender already belongs to a team, they cannot send join requests
        if ($validated['type'] === 'request') {
            // Asking to join is joining. With it closed on Team Rules the
            // request is refused now, rather than sent to a leader who could
            // only accept it into a rule that forbids it.
            $askedTeam = Team::find($validated['team_id'] ?? null);
            $closed = ! Team::joiningAllowed($askedTeam?->section() ?: $student->sectionLabel()) ? Team::JOINING_CLOSED
                : ($student->capstoneStage() !== 1 ? Team::MEMBERS_SETTLED : null);

            if ($closed) {
                if ($request->expectsJson()) {
                    return response()->json(['success' => false, 'message' => $closed], 422);
                }

                return back()->with('error', $closed);
            }

            if (StudentTeam::has($studentId)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You are already in a team. Leave your team first before joining another.',
                    ], 422);
                }

                return back()->with('error', 'You are already in a team. Leave your team first before joining another.');
            }
        }

        // Check if there is already a pending invitation of the same type
        $exists = TeamInvitation::where('sender_id', $studentId)
            ->where('receiver_id', $validated['receiver_id'])
            ->where('type', $validated['type'])
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A pending invitation or request already exists.',
                ], 422);
            }

            return back()->with('error', 'A pending invitation or request already exists.');
        }

        $invitation = TeamInvitation::create([
            'sender_id' => $studentId,
            'receiver_id' => $validated['receiver_id'],
            'team_id' => $validated['team_id'] ?? null,
            'type' => $validated['type'],
            'status' => 'pending',
        ]);

        // Log activity
        if ($validated['type'] === 'invite') {
            $this->logActivity(
                $studentId,
                'invitation_sent',
                'Invited <strong>'.e(Student::find($validated['receiver_id'])?->f_name ?? 'a student').'</strong> to join the team',
                ['receiver_id' => $validated['receiver_id'], 'team_id' => $validated['team_id']]
            );
            // Notify receiver
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
            // Notify team leader (receiver)
            Notification::create([
                'student_id' => $validated['receiver_id'],
                'type' => 'request_received',
                'title' => 'Join Request',
                'message' => ($student ? $student->f_name.' '.$student->l_name : 'A student').' wants to join your team.',
                'link' => route('student.team'),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $validated['type'] === 'invite' ? 'Invitation sent successfully.' : 'Join request sent successfully.',
                'invitation' => $invitation,
            ], 201);
        }

        return redirect()
            ->route('student.team')
            ->with('status', $validated['type'] === 'invite' ? 'Invitation sent!' : 'Join request sent!');
    }

    /**
     * Accepting and declining are the same thing to the invitation: its
     * status changes. So both arrive here as a PATCH carrying the status
     * they want, and the work stays in the two methods that already did
     * it - this only decides which one runs.
     */
    public function update(Request $request, TeamInvitation $invitation): JsonResponse|RedirectResponse
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
     * The team's recruitment post says what they were short of. When it
     * names exactly one role that still has a seat, that is the answer and
     * the student is never asked. Naming two or more is a genuine choice, so
     * this returns null and the team page asks them once, on arrival.
     *
     * Null is also the answer when there is no post at all, which is every
     * directly-invited member.
     */
    private function roleFromTeamPost(int $teamId): ?int
    {
        $team = Team::find($teamId);

        if (! $team) {
            return null;
        }

        $wanted = Recruitment::where('student_id', $team->leader_id)
            ->where('recruitment_goal', 'build_a_team')
            ->value('roles');

        $wanted = collect((array) $wanted)->filter()->values();

        if ($wanted->isEmpty()) {
            return null;
        }

        $open = StudentRole::memberRoles()
            ->filter(fn ($role) => $wanted->contains($role->s_role_name))
            ->filter(fn ($role) => StudentRole::hasSeat($teamId, $role->s_role_id))
            ->values();

        // One and only one - anything else is the student's to decide.
        return $open->count() === 1 ? $open->first()->s_role_id : null;
    }

    public function accept(Request $request, TeamInvitation $invitation): JsonResponse|RedirectResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        // The moment somebody actually joins is the moment to ask whether they
        // may. This path - the one the page uses - asked nothing: an invitation
        // sent while a seat was free could be accepted after it went, after
        // Capstone 2 began, or after the CaPSA closed joining. Two invitations
        // for the last seat both got in.
        $joining = Team::find($invitation->team_id);

        $joiner = $invitation->type === 'invite' ? $student : Student::find($invitation->sender_id);

        if ($joining && $reason = $joining->joinBlockReason($joiner)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $reason], 422);
            }

            return back()->with('error', $reason);
        }

        $memberRoleId = $this->roleFromTeamPost($invitation->team_id);

        if ($invitation->type === 'invite') {
            // Logged in user is the receiver of the invite
            if ($invitation->receiver_id !== $studentId) {
                abort(403);
            }

            // Update the receiver's team_id and role
            StudentSchoolYear::where('student_id', $studentId)
                ->update([
                    'team_id' => $invitation->team_id,
                    's_role_id' => $memberRoleId,
                ]);
        } else {
            // Type is request. The logged in user is the receiver (team leader or owner)
            if ($invitation->receiver_id !== $studentId) {
                abort(403);
            }

            // Update the sender's (who requested to join) team_id and role
            StudentSchoolYear::where('student_id', $invitation->sender_id)
                ->update([
                    'team_id' => $invitation->team_id,
                    's_role_id' => $memberRoleId,
                ]);
        }

        $invitation->update(['status' => 'accepted']);

        // Auto-delete the joining student's recruitment post
        $joinedStudentId = $invitation->type === 'invite' ? $studentId : $invitation->sender_id;
        Recruitment::where('student_id', $joinedStudentId)->delete();

        // Log the join activity
        $team = Team::find($invitation->team_id);
        $joiningStudent = Student::find($joinedStudentId);

        // Auto-close team if it has reached full capacity
        if ($team && $team->team_status === 'Active') {
            $currentCount = StudentSchoolYear::where('team_id', $team->team_id)->count();
            // The CaPSA's limit, not member_count - that column is a flat 5
            // written at creation, so a team of three never closed at three.
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
            if ($invitation->type === 'request' && $invitation->receiver_id === $studentId) {
                $this->logActivity(
                    $studentId,
                    'request_accepted',
                    'Accepted <strong>'.e($joiningStudent->f_name.' '.$joiningStudent->l_name).'</strong>\'s request to join the team',
                    ['joined_student_id' => $joinedStudentId, 'team_id' => $team->team_id]
                );
            }
        }

        // Notify the sender that their invite/request was accepted
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

        // Notify other team members that someone joined
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

        // Decline other invitations for this student since they joined a team
        TeamInvitation::where('status', 'pending')
            ->where(function ($query) use ($studentId, $invitation) {
                $targetId = $invitation->type === 'invite' ? $studentId : $invitation->sender_id;
                $query->where('receiver_id', $targetId)
                    ->orWhere('sender_id', $targetId);
            })
            ->update(['status' => 'declined']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Invitation accepted.',
            ]);
        }

        return redirect()
            ->route('student.team')
            ->with('status', 'Welcome to the team!');
    }

    public function decline(Request $request, TeamInvitation $invitation): JsonResponse|RedirectResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        // User must be sender or receiver to decline
        if ($invitation->sender_id !== $studentId && $invitation->receiver_id !== $studentId) {
            abort(403);
        }

        $invitation->update(['status' => 'declined']);

        // Log activity
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

        // Notify the other party
        Notification::create([
            'student_id' => $otherPartyId,
            'type' => $invitation->type === 'invite' ? 'invitation_declined' : 'request_declined',
            'title' => $invitation->type === 'invite' ? 'Invitation Declined' : 'Request Declined',
            'message' => 'Your '.($invitation->type === 'invite' ? 'invitation' : 'join request').' has been declined.',
            'link' => route('student.team'),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Invitation declined.',
            ]);
        }

        return redirect()
            ->route('student.team')
            ->with('status', 'Invitation declined.');
    }

    /**
     * GET /invitations — what is waiting for you, minus what you put off.
     *
     * This was GET /invitations/popup, named after the thing on screen rather
     * than the thing being fetched. It is the collection, filtered to the
     * pending ones addressed to you; the popup is one of the places that
     * reads it.
     */
    public function index(Request $request): JsonResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        $dismissed = (array) $request->session()->get('dismissed_invitations', []);

        $items = TeamInvitation::with(['sender', 'team'])
            ->where('receiver_id', $studentId)
            ->where('status', 'pending')
            ->latest('created_at')
            ->get()
            ->reject(fn ($invitation) => in_array($invitation->id, $dismissed))
            ->values()
            ->map(function ($invitation) {
                $person = $invitation->sender;

                return [
                    'id' => $invitation->id,
                    'type' => $invitation->type,
                    'name' => trim(($person?->f_name ?? '').' '.($person?->l_name ?? '')) ?: ($person?->email ?? 'A student'),
                    'avatar' => $person?->avatarUrl() ?? '',
                    'email' => $person?->email ?? '',
                    'meta' => trim((($person?->course ?? '').' '.($person?->year_section ?? ''))) ?: '',
                    'team_name' => $invitation->team?->team_name ?? ($invitation->type === 'request' ? 'your team' : 'a team'),
                    'time_ago' => $invitation->created_at?->diffForHumans(),
                ];
            });

        return response()->json(['items' => $items]);
    }

    public function remindLater(Request $request, TeamInvitation $invitation): JsonResponse|RedirectResponse
    {
        $dismissed = (array) $request->session()->get('dismissed_invitations', []);
        $dismissed[] = $invitation->id;
        $request->session()->put('dismissed_invitations', array_values(array_unique($dismissed)));

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }
}
