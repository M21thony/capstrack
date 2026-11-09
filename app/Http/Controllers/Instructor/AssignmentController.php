<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstonePreProposal;
use App\Models\DefenseSchedule;
use App\Models\Notification;
use App\Models\Student;
use App\Models\TeamExpertAssignment;
use App\Models\TitleChangeRequest;
use App\Services\RecipientResolver;
use App\Support\DefenseBoard;
use App\Support\ExpertApplications;
use App\Support\TitleRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    /**
     * Team Assignments is the Team Applications tab of Teams now. Its address
     * still arrives from bookmarks and older notifications.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('instructor.teams', ['tab' => 'applications']);
    }

    /**
     * Approving and rejecting both land the record on a new status, so one
     * PATCH carries which one. The deciding stays in the two methods that
     * already do it - this only routes to them.
     */
    public function update(Request $request, $id): JsonResponse
    {
        return match ($request->validate(['status' => ['required', 'in:approved,rejected,meet']])['status']) {
            'approved' => $this->approve($id),
            'meet' => $this->meet($request, $id),
            default => $this->reject($request, $id),
        };
    }

    /**
     * "Meet first": the request stays pending while the team books one of the
     * expert's consultation times, and the expert approves or declines after.
     */
    public function meet(Request $request, $id): JsonResponse
    {
        $note = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']], [
            'reason.required' => 'Tell the team what you would like to talk about.',
            'reason.min' => 'A line or two, so the team knows what to bring.',
        ])['reason'];

        $assignment = TeamExpertAssignment::where('assignment_id', $id)
            ->where('expert_id', Auth::guard('expert')->user()->expert_id)
            ->first();

        if (! $assignment) {
            return response()->json(['success' => false, 'message' => 'Assignment not found.'], 404);
        }
        if ($assignment->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'This request has already been processed.'], 422);
        }
        if ($assignment->meet_asked_at) {
            return response()->json(['success' => false, 'message' => 'You already asked this team to meet first.'], 422);
        }

        $assignment->update(['meet_note' => $note, 'meet_asked_at' => now()]);

        if ($leaderId = $assignment->team?->leader_id) {
            Notification::create([
                'student_id' => $leaderId,
                'type' => 'expert_meet_first',
                'title' => 'Your Expert Wants to Meet First',
                'message' => $assignment->expert->name.' would like to meet your team before answering. Book one of their times. Note: '.$note,
                'link' => route('student.experts'),
                'is_read' => false,
                'created_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Sent. The team will book one of your consultation times.']);
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

        $leader = $assignment->team?->leader;
        $full = app(ExpertApplications::class)->fullAt($expert, Student::mapCourseToProgram($leader->course ?? ''));

        if ($full) {
            return response()->json([
                'success' => false,
                'message' => $full['program']
                    ? "You have reached your limit of {$full['cap']} {$full['program']} team(s)."
                    : 'You have reached your maximum number of teams.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($assignment) {
                $assignment->update(['status' => 'approved']);
            });

            if ($leader) {
                Notification::create(['student_id' => $leader->student_id, 'type' => 'expert_accepted', 'title' => 'Expert Request Accepted', 'message' => $expert->name." accepted your team's request.", 'link' => route('student.experts'), 'is_read' => false, 'created_at' => now()]);
            }

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
