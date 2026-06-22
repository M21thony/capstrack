<?php

namespace App\Http\Controllers;

use App\Models\Recruitment;
use App\Models\StudentSchoolYear;
use App\Models\TeamInvitation;
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
        $studentId = $student ? $student->student_id : 8;

        // If the sender already belongs to a team, they cannot send join requests
        if ($validated['type'] === 'request') {
            $senderSy = StudentSchoolYear::where('student_id', $studentId)->first();
            if ($senderSy?->team_id) {
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

    public function accept(Request $request, TeamInvitation $invitation): JsonResponse|RedirectResponse
    {
        $student = $request->user();
        $studentId = $student ? $student->student_id : 8;

        if ($invitation->type === 'invite') {
            // Logged in user is the receiver of the invite
            if ($invitation->receiver_id !== $studentId) {
                abort(403);
            }

            // Update the receiver's team_id
            StudentSchoolYear::where('student_id', $studentId)
                ->update(['team_id' => $invitation->team_id]);
        } else {
            // Type is request. The logged in user is the receiver (team leader or owner)
            if ($invitation->receiver_id !== $studentId) {
                abort(403);
            }

            // Update the sender's (who requested to join) team_id
            StudentSchoolYear::where('student_id', $invitation->sender_id)
                ->update(['team_id' => $invitation->team_id]);
        }

        $invitation->update(['status' => 'accepted']);

        // Auto-delete the joining student's recruitment post
        $joinedStudentId = $invitation->type === 'invite' ? $studentId : $invitation->sender_id;
        Recruitment::where('student_id', $joinedStudentId)->delete();

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
        $studentId = $student ? $student->student_id : 8;

        // User must be sender or receiver to decline
        if ($invitation->sender_id !== $studentId && $invitation->receiver_id !== $studentId) {
            abort(403);
        }

        $invitation->update(['status' => 'declined']);

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
}
