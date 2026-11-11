<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssignmentResource;
use App\Http\Resources\ExpertResource;
use App\Models\ActivityLog;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\ExpertRemovalRequest;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\TeamExpertAssignment;
use App\Support\ExpertApplications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentExpertController extends Controller
{
    public function __construct(private ExpertApplications $applications) {}

    public function index(): JsonResponse
    {
        $student = Auth::user();

        $studentProgram = Student::mapCourseToProgram($student->course ?? '');

        $experts = CapstoneExpert::where('approved_status', true)
            ->acceptsProgram($studentProgram)
            ->whereHas('roles', function ($q) {
                $q->where('role_name', '!=', 'capstone_adviser');
            })
            ->with(['roles' => function ($q) {
                $q->where('role_name', '!=', 'capstone_adviser');
            }, 'expertise', 'teamAssignments' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->paginate(9)
            ->through(function ($expert) {
                $expert->slots_used = $expert->teamAssignments->count();
                unset($expert->teamAssignments);

                return $expert;
            });

        return response()->json([
            'success' => true,
            'data' => ExpertResource::collection($experts),
            'meta' => [
                'current_page' => $experts->currentPage(),
                'last_page' => $experts->lastPage(),
                'per_page' => $experts->perPage(),
                'total' => $experts->total(),
            ],
        ]);
    }

    public function show(CapstoneExpert $expert): JsonResponse
    {
        if (! $expert->approved_status) {
            return response()->json([
                'success' => false,
                'message' => 'Expert not found.',
            ], 404);
        }

        $expert->load(['roles' => function ($q) {
            $q->where('role_name', '!=', 'capstone_adviser');
        }, 'expertise', 'teamAssignments' => function ($q) {
            $q->where('status', 'approved');
        }]);

        $expert->slots_used = $expert->teamAssignments->count();

        return response()->json([
            'success' => true,
            'data' => new ExpertResource($expert),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expert_id' => ['required', 'integer', 'exists:capstone_experts,expert_id'],
            'expert_role_id' => ['required', 'integer', 'exists:expert_roles,expert_role_id'],
            'message' => ['required', 'string', function ($attribute, $value, $fail) {
                if (str_word_count($value) > 500) {
                    $fail('The message field must not be greater than 500 words.');
                }
            }],
        ]);

        $student = Auth::user();

        $team = StudentSchoolYear::where('student_id', $student->student_id)
            ->whereNotNull('team_id')
            ->first()?->team;

        if (! $team) {
            return response()->json([
                'success' => false,
                'message' => 'You must be in a team to apply for an expert.',
            ], 422);
        }

        if ($team->leader_id !== $student->student_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the team leader can apply for experts.',
            ], 403);
        }

        $expert = CapstoneExpert::find($validated['expert_id']);

        if (! $expert) {
            return response()->json(['success' => false, 'message' => 'Expert not found or not yet approved.'], 404);
        }

        // Slots, program, role and "is this role already taken": the same
        // checks an approved change request runs on the replacement.
        if ($refusal = $this->applications->refusal($team, $student, $expert, (int) $validated['expert_role_id'])) {
            return response()->json(['success' => false, 'message' => $refusal['message']], $refusal['status']);
        }

        // Only the pre-proposal the CaPSA/TSA recommended goes out: the expert can
        // trust it was checked, and no other file can be sent in its place.
        $approvedProposal = $team->selected_pre_proposal_id
            ? CapstonePreProposal::find($team->selected_pre_proposal_id)
            : null;

        if (! $approvedProposal) {
            return response()->json([
                'success' => false,
                'message' => 'Your pre-proposal has to be recommended by your CaPSA/TSA before you can apply for an expert.',
            ], 422);
        }

        try {
            $assignment = $this->applications->send($team, $expert, (int) $validated['expert_role_id'], $validated['message'], null, $approvedProposal->id);

            return response()->json([
                'success' => true,
                'message' => 'Application sent. Waiting for '.$expert->name.' to answer.',
                'data' => new AssignmentResource($assignment->load('expert')),
            ]);
        } catch (\Throwable $e) {
            logger()->error('Expert apply error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, TeamExpertAssignment $assignment): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $student = Auth::user();

        $team = StudentSchoolYear::where('student_id', $student->student_id)
            ->whereNotNull('team_id')
            ->first()?->team;

        if (! $team || $assignment->team_id !== $team->team_id) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment not found.',
            ], 404);
        }

        if ($team->leader_id !== $student->student_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the team leader can remove expert assignments.',
            ], 403);
        }

        // An expert who already took the team on is only changed with the
        // CaPSA/TSA's permission. The request goes to them; the expert stays
        // until they approve it. An application still waiting on the expert
        // is the team's to withdraw, below.
        if ($assignment->status === 'approved') {
            if (mb_strlen(trim($request->reason)) < ExpertRemovalRequest::REASON_MIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Give your CaPSA/TSA a reason they can decide on - at least '.ExpertRemovalRequest::REASON_MIN.' characters.',
                ], 422);
            }

            if (ExpertRemovalRequest::pending()->where('assignment_id', $assignment->assignment_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A request to remove this expert is already waiting on your CaPSA/TSA.',
                ], 422);
            }

            // Who the team wants instead, applied to the moment the CaPSA/TSA
            // approves - so the role is not left empty while the team starts
            // over. Checked now as well as then: a replacement who cannot take
            // the team is better refused here than discovered later.
            $replacement = $this->pickedReplacement($request, $team, $student, $assignment);

            if (isset($replacement['message'])) {
                return response()->json(['success' => false, 'message' => $replacement['message']], $replacement['status']);
            }

            ExpertRemovalRequest::create([
                'team_id' => $team->team_id,
                'assignment_id' => $assignment->assignment_id,
                'expert_id' => $assignment->expert_id,
                'requested_by' => $student->student_id,
                'reason' => trim($request->reason),
                'status' => ExpertRemovalRequest::PENDING,
                'replacement_expert_id' => $replacement['expert']->expert_id,
                'replacement_expert_role_id' => $replacement['expert_role_id'],
                'replacement_message' => $replacement['message_to_expert'],
                'replacement_proposal_file' => $request->hasFile('replacement_proposal_file')
                    ? $request->file('replacement_proposal_file')->store('proposals', 'public')
                    : null,
            ]);

            ActivityLog::create([
                'student_id' => $student->student_id,
                'type' => 'expert_removal_requested',
                'description' => 'Asked the CaPSA/TSA to remove expert '.($assignment->expert?->name ?? '#'.$assignment->expert_id)." — Reason: {$request->reason}",
                'metadata' => ['assignment_id' => $assignment->assignment_id, 'expert_id' => $assignment->expert_id],
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sent to your CaPSA/TSA. '.$assignment->expert?->name.' stays with your team until they approve; then your application goes to '.$replacement['expert']->name.'.',
            ]);
        }

        ActivityLog::create([
            'student_id' => $student->student_id,
            'type' => 'expert_removed',
            'description' => $assignment->expert?->name
                ? "Removed expert {$assignment->expert->name} — Reason: {$request->reason}"
                : "Removed expert assignment #{$assignment->assignment_id} — Reason: {$request->reason}",
            'metadata' => [
                'assignment_id' => $assignment->assignment_id,
                'expert_id' => $assignment->expert_id,
                'reason' => $request->reason,
            ],
            'created_at' => now(),
        ]);

        if ($assignment->proposal_file) {
            Storage::disk('public')->delete($assignment->proposal_file);
        }

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Expert assignment removed successfully.',
        ]);
    }

    /**
     * The replacement a change request names, checked the way an application
     * to them would be - the role being changed counts as free.
     *
     * @return array{expert: CapstoneExpert, expert_role_id: int, message_to_expert: string}|array{message: string, status: int}
     */
    private function pickedReplacement(Request $request, $team, Student $leader, TeamExpertAssignment $current): array
    {
        $approvedProposal = (bool) $team->selected_pre_proposal_id;

        $validator = validator($request->all(), [
            'replacement_expert_id' => ['required', 'integer'],
            'replacement_message' => ['required', 'string', 'max:3000'],
            'replacement_proposal_file' => [$approvedProposal ? 'nullable' : 'required', 'file', 'mimes:pdf', 'max:5120'],
        ], [
            'replacement_expert_id.required' => 'Pick the '.($current->engaged_role_name ?: 'person').' you want instead.',
            'replacement_message.required' => 'Write a short message to the '.($current->engaged_role_name ?: 'person').' you picked.',
            'replacement_proposal_file.required' => 'Attach your pre-proposal as a PDF for the '.($current->engaged_role_name ?: 'person').' you picked.',
        ]);

        if ($validator->fails()) {
            return ['message' => $validator->errors()->first(), 'status' => 422];
        }

        $role = $current->engaged_role_name;
        $expert = CapstoneExpert::find((int) $request->input('replacement_expert_id'));

        if (! $expert || (int) $expert->expert_id === (int) $current->expert_id) {
            return ['message' => 'Pick someone other than the expert you are changing.', 'status' => 422];
        }

        // The same role as the one being changed.
        $expertRoleId = (int) DB::table('expert_roles')
            ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
            ->where('expert_roles.expert_id', $expert->expert_id)
            ->where('roles.role_name', $role)
            ->value('expert_roles.expert_role_id');

        if (! $expertRoleId) {
            return ['message' => $expert->name.' does not take teams as a '.($role ?: 'this role').'.', 'status' => 422];
        }

        if ($refusal = $this->applications->refusal($team, $leader, $expert, $expertRoleId, $current->assignment_id)) {
            return $refusal;
        }

        return ['expert' => $expert, 'expert_role_id' => $expertRoleId, 'message_to_expert' => trim((string) $request->input('replacement_message'))];
    }

    /**
     * The team taking back a change request the CaPSA/TSA has not decided on yet.
     *
     * DELETE /api/expert-removal-requests/{expertRemovalRequest}
     *
     * Once the CaPSA/TSA has approved or declined, there is nothing left to take
     * back - an approval has already removed the expert.
     */
    public function destroyRemovalRequest(Request $request, ExpertRemovalRequest $expertRemovalRequest): JsonResponse
    {
        /** @var Student $student */
        $student = Auth::user();

        $team = StudentSchoolYear::where('student_id', $student->student_id)
            ->whereNotNull('team_id')
            ->first()?->team;

        if (! $team || $expertRemovalRequest->team_id !== $team->team_id) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found.',
            ], 404);
        }

        if ($team->leader_id !== $student->student_id) {
            return response()->json([
                'success' => false,
                'message' => 'Only the team leader can take back a change request.',
            ], 403);
        }

        if (! $expertRemovalRequest->isPending()) {
            return response()->json([
                'success' => false,
                'message' => 'Your CaPSA/TSA has already '.$expertRemovalRequest->status.' this request, so there is nothing to take back.',
            ], 409);
        }

        $expertRemovalRequest->update(['status' => ExpertRemovalRequest::WITHDRAWN]);

        // The PDF meant for the replacement never goes anywhere now.
        if ($expertRemovalRequest->replacement_proposal_file) {
            Storage::disk('public')->delete($expertRemovalRequest->replacement_proposal_file);
        }

        $expertName = $expertRemovalRequest->expert?->name ?? ('expert #'.$expertRemovalRequest->expert_id);

        ActivityLog::create([
            'student_id' => $student->student_id,
            'type' => 'expert_removal_withdrawn',
            'description' => 'Took back the request to remove expert '.$expertName.' - the CaPSA/TSA had not decided on it yet.',
            'metadata' => [
                'expert_removal_request_id' => $expertRemovalRequest->id,
                'assignment_id' => $expertRemovalRequest->assignment_id,
                'expert_id' => $expertRemovalRequest->expert_id,
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Request withdrawn. '.($expertRemovalRequest->expert?->name ?? 'Your expert').' stays with your team.',
        ]);
    }
}
