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
use Illuminate\Support\Facades\Validator;

class AssignmentController extends Controller
{
    public function index()
    {
        $expert = Auth::guard('expert')->user();

        $pendingAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'pending')
            ->with(['team', 'team.leader', 'team.members', 'expert.roles', 'expertRole.role', 'preProposal'])
            ->get();

        $approvedAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->with(['team', 'team.leader', 'team.members', 'expert.roles', 'expertRole.role', 'preProposal'])
            ->get();

        $rejectedAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'rejected')
            ->with(['team', 'team.leader', 'team.members', 'expert.roles', 'expertRole.role', 'preProposal'])
            ->latest()
            ->take(10)
            ->get();

        $currentSlots = $approvedAssignments->count();
        $maxSlots = $expert->max_teams ?? 0;

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
            // proposal_file is stored via ->store('proposals', 'public') in
            // Api\StudentExpertController - must resolve through that same
            // 'public' disk explicitly. The bare Storage::url() facade call
            // resolves against the DEFAULT disk (config('filesystems.default'),
            // 'local' here) instead, which only "worked" by coincidence of
            // the local driver's fallback URL logic - it breaks the moment
            // the default disk is ever reconfigured.
            $proposalUrl = $a->proposal_file ? Storage::disk('public')->url($a->proposal_file) : null;
            $message = $a->message ?? 'No message provided.';

            return [$a->assignment_id => [
                'team' => $a->team?->team_name ?? 'Unknown',
                'project' => $a->team?->project_title ?? 'No project title',
                // The role this team is asking for, not the list of every
                // role this expert holds - which was the same string on every
                // request and told the expert nothing about what they were
                // being asked to do.
                'role' => $a->engaged_role_name ? ucfirst($a->engaged_role_name) : 'Expert',
                'leader' => $leaderName,
                'leader_email' => $leaderEmail,
                'members' => $members,
                'message' => $message,
                'proposal' => $proposalUrl
                    ?? ($a->preProposal?->duplication_check_id
                        ? route('instructor.assignments.pre_proposal', $a->assignment_id)
                        : null),
                // When the team applied with the pre-proposal their CaPSA
                // approved, say so: the expert is looking at the document
                // that got through, not a PDF the team chose to attach.
                'approved_title' => $a->preProposal?->title,
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

        // Unset is not nought - see the same guard in StudentExpertController.
        $maxTeams = $expert->max_teams ?? 0;

        if ($maxTeams > 0 && $slotsUsed >= $maxTeams) {
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

    /**
     * The PDF behind an application made with an approved pre-proposal.
     *
     * The file lives on the duplication check the team ran, on the private
     * disk - so it is served through here, and only to the expert the
     * application was addressed to.
     */
    public function preProposalDocument($id)
    {
        $expert = Auth::guard('expert')->user();

        $assignment = TeamExpertAssignment::with('preProposal.duplicationCheck')
            ->where('assignment_id', $id)
            ->where('expert_id', $expert->expert_id)
            ->first();

        abort_if(! $assignment, 404);

        $check = $assignment->preProposal?->duplicationCheck;

        abort_if(! $check || ! $check->document_path, 404);
        abort_unless(Storage::exists($check->document_path), 404);

        return Storage::response(
            $check->document_path,
            'pre-proposal.pdf',
            [],
            'inline'
        );
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
                        'message' => $expertModel->name.' has rejected your request.'.($validated['reason'] ? ' Reason: '.$validated['reason'] : ''),
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

    /**
     * Drop a team an expert has already taken on.
     *
     * This used to go through on one click, with nothing recorded and nobody
     * told - a team simply lost its adviser and had no way of knowing why.
     * It now asks for a reason and for the word to be typed, the same as the
     * team's own side of this, and the reason reaches the team.
     */
    public function remove(Request $request, $id): JsonResponse
    {
        // Checked by hand rather than through $request->validate(): this is
        // only ever called by fetch(), and a failed validate() redirects,
        // which reaches the browser as a page of HTML where JSON was
        // expected and fails silently.
        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirm' => ['required', 'string'],
        ], [
            'reason.required' => 'Say why you are no longer working with this team.',
            'reason.min' => 'Say why you are no longer working with this team.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();

        if (strcasecmp(trim($validated['confirm']), 'remove') !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'Type "remove" to confirm.',
            ], 422);
        }

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

        $team = $assignment->team;
        $expertName = $expert->name;

        if ($assignment->proposal_file) {
            Storage::disk('public')->delete($assignment->proposal_file);
        }

        $assignment->delete();

        // The team finds out from this, so it is sent after the row is gone
        // rather than in place of it - the assignment itself is what frees
        // the slot, and the reason is what the team is owed.
        if ($team && $team->leader_id) {
            Notification::create([
                'student_id' => $team->leader_id,
                'type' => 'expert_removed',
                'title' => 'Expert Withdrew',
                'message' => e($expertName).' is no longer working with your team. Reason: '.e($validated['reason']),
                'link' => route('student.experts'),
                'is_read' => false,
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignment removed successfully.',
        ]);
    }
}
