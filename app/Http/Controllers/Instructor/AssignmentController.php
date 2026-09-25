<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstonePreProposal;
use App\Models\Notification;
use App\Models\TeamExpertAssignment;
use App\Models\TitleChangeRequest;
use App\Services\RecipientResolver;
use App\Support\TitleRevision;
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

        // A queue is worked from the front: whoever has waited longest first.
        $pendingAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'pending')
            ->with(['team', 'team.leader', 'team.members', 'expert.roles', 'expertRole.role', 'preProposal'])
            ->oldest()
            ->get();

        $approvedAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->with(['team', 'team.leader', 'team.members', 'expert.roles', 'expertRole.role', 'preProposal'])
            ->latest()
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
                // How long a request has sat in the queue. Three days is when
                // a team starts to wonder whether anyone has seen it.
                'waiting' => $a->created_at ? $a->created_at->diffForHumans(null, true) : '',
                'stale' => $a->created_at ? $a->created_at->lte(now()->subDays(3)) : false,
                'decided' => $a->updated_at ? $a->updated_at->format('M d, Y g:i A') : '',
                'rejection_reason' => $a->rejection_reason ?? null,
                'status' => $a->status ?? 'pending',
            ]];
        })->toArray();

        // New titles waiting for this expert to confirm as a team's adviser.
        $resolver = app(RecipientResolver::class);
        $titleConfirmations = CapstonePreProposal::where('adviser_status', TitleRevision::PENDING)
            ->whereNull('retired_at')
            ->with(['team', 'duplicationCheck'])
            ->get()
            ->filter(fn ($p) => $resolver->resolveTeamRoleExpert((int) $p->team_id, 'adviser') === (int) $expert->expert_id)
            ->sortBy('created_at')
            ->values();

        $titleData = $titleConfirmations->mapWithKeys(function ($tc) {
            $setAside = $tc->team
                ? $tc->team->allPreProposals()->whereNotNull('retired_at')->pluck('title')->filter()->values()->all()
                : [];

            return [$tc->id => [
                'team' => $tc->team?->team_name ?? 'Unknown',
                'title' => $tc->title,
                'replaces' => $setAside,
                'verdict' => $tc->duplicationCheck?->verdict,
                'top_score' => $tc->duplicationCheck?->top_score,
                'document' => $tc->duplicationCheck?->document_path
                    ? route('instructor.title-confirmations.document', $tc)
                    : null,
                'update_url' => route('instructor.title-confirmations.update', $tc),
                'date' => $tc->created_at ? $tc->created_at->format('M d, Y g:i A') : '',
                'waiting' => $tc->created_at ? $tc->created_at->diffForHumans(null, true) : '',
                'stale' => $tc->created_at ? $tc->created_at->lte(now()->subDays(3)) : false,
            ]];
        })->toArray();

        // Passed teams asking to change part of their title, for this adviser.
        $titleChanges = TitleChangeRequest::where('status', TitleChangeRequest::PENDING)
            ->with(['team', 'requester'])
            ->oldest()
            ->get()
            ->filter(fn ($c) => $resolver->resolveTeamRoleExpert((int) $c->team_id, 'adviser') === (int) $expert->expert_id)
            ->values();

        return view('instructor.assignments', [
            'titleChanges' => $titleChanges,
            'titleConfirmations' => $titleConfirmations,
            'titleData' => $titleData,
            'pendingAssignments' => $pendingAssignments,
            'approvedAssignments' => $approvedAssignments,
            'rejectedAssignments' => $rejectedAssignments,
            'currentSlots' => $currentSlots,
            'maxSlots' => $maxSlots,
            'assignmentData' => $assignmentData,
        ]);
    }

    /**
     * Approving and rejecting both land the record on a new status, so one
     * PATCH carries which one. The deciding stays in the two methods that
     * already do it - this only routes to them.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return $request->validate([
            'status' => ['required', 'in:approved,rejected'],
        ])['status'] === 'approved'
            ? $this->approve($id)
            : $this->reject($request, $id);
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
            // Required. A decline reaches the team as a decision they have to
            // do something about, and most of the time what an expert means
            // is "tighten this and come back" rather than "no" - which is
            // unsayable without a sentence. Twenty characters is roughly the
            // shortest useful one; it stops "no" and "full" going out alone.
            'reason' => ['required', 'string', 'min:20', 'max:500'],
        ], [
            'reason.required' => 'Tell the team why. They can apply again, and this is what they have to go on.',
            'reason.min' => 'A line or two, so the team knows what to change.',
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
                    'rejection_reason' => $validated['reason'],
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
     * Clear a declined request off the expert's list.
     *
     * An expert cannot drop a team they approved: the team built its plans
     * around that yes, and a change of expert goes through their CaPSA (the
     * students' own side asks for it the same way). What is left here is
     * tidying the Declined tab. The team was already told why when it was
     * declined, so nobody is notified again.
     */
    public function remove(Request $request, $id): JsonResponse
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
                'message' => 'Please decline the request instead.',
            ], 422);
        }

        if ($assignment->status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Approved teams stay with you. If you can no longer work with this team, ask their CaPSA.',
            ], 422);
        }

        if ($assignment->proposal_file) {
            Storage::disk('public')->delete($assignment->proposal_file);
        }

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Removed from your list.',
        ]);
    }
}
