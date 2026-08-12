<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssignmentResource;
use App\Http\Resources\ExpertResource;
use App\Models\ActivityLog;
use App\Models\CapstoneExpert;
use App\Models\StudentSchoolYear;
use App\Models\TeamExpertAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentExpertController extends Controller
{
    public function index(): JsonResponse
    {
        $student = Auth::user();

        $experts = CapstoneExpert::where('approved_status', true)
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
            'proposal_file' => ['required', 'file', 'mimes:pdf', 'max:5120'],
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

        $expert = CapstoneExpert::where('expert_id', $validated['expert_id'])
            ->where('approved_status', true)
            ->first();

        if (! $expert) {
            return response()->json([
                'success' => false,
                'message' => 'Expert not found or not yet approved.',
            ], 404);
        }

        $slotsUsed = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->count();

        if ($slotsUsed >= ($expert->max_teams ?? 4)) {
            return response()->json([
                'success' => false,
                'message' => 'This expert has reached their maximum number of teams.',
            ], 422);
        }

        $offersQualifyingRole = DB::table('expert_roles')
            ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
            ->where('expert_roles.expert_id', $expert->expert_id)
            ->where('roles.role_name', '!=', 'capstone_adviser')
            ->exists();

        if (! $offersQualifyingRole) {
            return response()->json([
                'success' => false,
                'message' => 'This expert is not available for team applications.',
            ], 422);
        }

        $alreadyApplied = TeamExpertAssignment::where('team_id', $team->team_id)
            ->where('expert_id', $expert->expert_id)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($alreadyApplied) {
            return response()->json([
                'success' => false,
                'message' => 'Your team already has a pending or approved application with this expert.',
            ], 409);
        }

        try {
            $filePath = $request->file('proposal_file')->store('proposals', 'public');

            $assignment = TeamExpertAssignment::create([
                'team_id' => $team->team_id,
                'expert_id' => $validated['expert_id'],
                'status' => 'pending',
                'proposal_file' => $filePath,
                'message' => $validated['message'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Application sent successfully. Waiting for the expert to approve.',
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
}
