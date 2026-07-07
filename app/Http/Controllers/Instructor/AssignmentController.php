<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\TeamExpertAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    public function index()
    {
        $expert = Auth::guard('expert')->user();

        $pendingAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'pending')
            ->with(['team', 'role', 'team.leader', 'team.members'])
            ->get();

        $approvedAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->with(['team', 'role', 'team.leader', 'team.members'])
            ->get();

        $rejectedAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'rejected')
            ->with(['team', 'role', 'team.leader', 'team.members'])
            ->latest()
            ->take(10)
            ->get();

        $currentSlots = $approvedAssignments->count();
        $maxSlots = $expert->max_teams ?? 4;

        $allAssignments = $pendingAssignments
            ->merge($approvedAssignments)
            ->merge($rejectedAssignments);

        $assignmentData = $allAssignments->mapWithKeys(function ($a) {
            $leaderName = $a->team && $a->team->leader
                ? ($a->team->leader->displayFirstName() ?? $a->team->leader->f_name ?? 'N/A')
                : 'N/A';
            $leaderEmail = $a->team && $a->team->leader ? $a->team->leader->email : '';
            $members = $a->team && $a->team->members
                ? $a->team->members
                    ->reject(fn ($m) => $m->student_id === ($a->team->leader_id ?? null))
                    ->map(fn ($m) => [
                        'name' => trim(($m->f_name ?? '').' '.($m->l_name ?? '')),
                        'email' => $m->email ?? '',
                    ])
                    ->filter(fn ($m) => $m['name'] !== '')
                    ->values()
                : [];
            $proposalUrl = $a->proposal_file ? Storage::url($a->proposal_file) : null;
            $message = $a->message ?? 'No message provided.';

            return [$a->assignment_id => [
                'team' => $a->team?->team_name ?? 'Unknown',
                'project' => $a->team?->project_title ?? 'No project title',
                'role' => ucfirst($a->role?->role_name ?? ''),
                'leader' => $leaderName,
                'leader_email' => $leaderEmail,
                'members' => $members,
                'message' => $message,
                'proposal' => $proposalUrl,
                'date' => $a->created_at ? $a->created_at->format('M d, Y g:i A') : 'N/A',
                'rejection_reason' => $a->rejection_reason ?? null,
                'status' => $a->status ?? 'pending',
            ]];
        })->toArray();

        return view('instructor.assignments', [
            'pendingAssignments' => $pendingAssignments,
            'approvedAssignments' => $approvedAssignments,
            'rejectedAssignments' => $rejectedAssignments,
            'currentSlots' => $currentSlots,
            'maxSlots' => $maxSlots,
            'assignmentData' => $assignmentData,
        ]);
    }

    public function approve($id): JsonResponse
    {
        $expert = Auth::guard('expert')->user();

        $assignment = TeamExpertAssignment::where('assignment_id', $id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found.',
            ], 404);
        }

        if ($assignment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been processed.',
            ], 422);
        }

        $slotsUsed = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->count();

        if ($slotsUsed >= ($expert->max_teams ?? 4)) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached your maximum number of teams.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($assignment) {
                $assignment->update(['status' => 'approved']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Team request approved successfully.',
            ]);
        } catch (\Throwable $e) {
            logger()->error('Assignment approve error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $expert = Auth::guard('expert')->user();

        $assignment = TeamExpertAssignment::where('assignment_id', $id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found.',
            ], 404);
        }

        if ($assignment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been processed.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($assignment, $validated) {
                $assignment->update([
                    'status' => 'rejected',
                    'rejection_reason' => $validated['reason'] ?? null,
                ]);

                $team = $assignment->team;
                $expertModel = $assignment->expert;

                if ($team && $team->leader_id) {
                    Notification::create([
                        'student_id' => $team->leader_id,
                        'type' => 'expert_rejected',
                        'title' => 'Expert Request Rejected',
                        'message' => $expertModel->name.' has rejected your request for the '.$assignment->role->role_name.' role.'
                            .($validated['reason'] ? ' Reason: '.$validated['reason'] : ''),
                        'link' => route('student.experts'),
                        'is_read' => false,
                        'created_at' => now(),
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Team request rejected.',
            ]);
        } catch (\Throwable $e) {
            logger()->error('Assignment reject error', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function remove($id): JsonResponse
    {
        $expert = Auth::guard('expert')->user();

        $assignment = TeamExpertAssignment::where('assignment_id', $id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        if (! $assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found.',
            ], 404);
        }

        if ($assignment->status === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Please reject the request instead.',
            ], 422);
        }

        if ($assignment->proposal_file) {
            Storage::disk('public')->delete($assignment->proposal_file);
        }

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assignment removed successfully.',
        ]);
    }
}
