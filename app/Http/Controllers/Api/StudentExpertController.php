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
            // Optional when the team already has an approved pre-proposal:
            // that PDF is the one the CaPSA read and approved, so making the
            // team hunt down and re-upload the same file only invited them to
            // attach a different, unapproved one.
            'proposal_file' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
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

        // Nobody is engaged before they have said what they take. An expert
        // who has never filled in their programmes and slots is still listed
        // - hiding them helps nobody - but a team cannot apply to them,
        // because there is no answer yet to whether they have room.
        if (! $expert->hasSetPrograms()) {
            return response()->json([
                'success' => false,
                'message' => 'This expert has not set which programs they take teams from yet, so they cannot be applied to.',
            ], 422);
        }

        // Enforced here too, not just hidden from the list - otherwise a
        // hand-crafted request could still apply to an expert who doesn't
        // take this program.
        $studentProgram = Student::mapCourseToProgram($student->course ?? '');

        if (! $expert->acceptsProgram($studentProgram)) {
            return response()->json([
                'success' => false,
                'message' => 'This expert does not accept teams from your program.',
            ], 422);
        }

        // Per-program cap first, when the expert set one. "1 - BSIT, 5 - BSIS"
        // has to mean one BSIT team, not just six teams in total - the overall
        // count alone would happily let all six be BSIT.
        $programCap = $expert->slotsForProgram($studentProgram);

        if ($programCap !== null) {
            if ($programCap < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'This expert does not accept teams from your program.',
                ], 422);
            }

            if ($expert->approvedTeamCountForProgram($studentProgram) >= $programCap) {
                return response()->json([
                    'success' => false,
                    'message' => "This expert has already reached their limit of {$programCap} {$studentProgram} team(s).",
                ], 422);
            }
        }

        // Overall cap still applies on top - an expert with no per-program
        // split has only this, and one with a split shouldn't exceed the sum.
        $slotsUsed = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->count();

        // A capacity nobody has set is not a capacity of nought: an expert
        // who has not opened their profile yet would otherwise be full from
        // the moment their account was made, and refuse every team.
        $maxTeams = $expert->max_teams ?? 0;

        if ($maxTeams > 0 && $slotsUsed >= $maxTeams) {
            return response()->json([
                'success' => false,
                'message' => 'This expert has reached their maximum number of teams.',
            ], 422);
        }

        // The chosen role has to be one this expert actually holds, and one a
        // team may engage them for. Validation only proved the id exists in
        // expert_roles - it could belong to any other expert entirely.
        $chosenRole = DB::table('expert_roles')
            ->join('roles', 'roles.role_id', '=', 'expert_roles.role_id')
            ->where('expert_roles.expert_role_id', $validated['expert_role_id'])
            ->where('expert_roles.expert_id', $expert->expert_id)
            ->whereIn('roles.role_name', CapstoneExpert::STUDENT_FACING_ROLES)
            ->first(['roles.role_name']);

        if (! $chosenRole) {
            return response()->json([
                'success' => false,
                'message' => 'That role is not available for this expert.',
            ], 422);
        }

        // One application per role per team, waiting or settled.
        //
        // An approved role is plainly taken. A pending one used to be left
        // alone, on the reasoning that a team could apply to several and
        // take whoever said yes first - but that is a team asking four
        // people to be their one adviser, and three of them read, thought
        // about and answered a request that was never really theirs to
        // accept. The team waits on the first answer instead, and applies
        // again if it is no.
        $spokenFor = TeamExpertAssignment::with(['expertRole.role', 'expert'])
            ->where('team_id', $team->team_id)
            ->whereIn('status', ['approved', 'pending'])
            // Approved before pending, so a role that is both reads as the
            // settled thing it is.
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->get()
            ->first(fn ($a) => strcasecmp((string) $a->engaged_role_name, $chosenRole->role_name) === 0);

        if ($spokenFor) {
            $heldByName = $spokenFor->expert?->name ?? 'another expert';
            $role = ucfirst($chosenRole->role_name);

            return response()->json([
                'success' => false,
                'message' => $spokenFor->status === 'approved'
                    ? 'Your team already has an approved '.$role.' ('.$heldByName.
                        '). Remove them first if you want a different one.'
                    : 'Your team is already waiting on '.$heldByName.' to answer as your '.$role.
                        '. Wait for that answer, or withdraw it, before applying to another.',
            ], 409);
        }

        // One role per expert per team: engaging someone as your adviser
        // takes them off the table as your consultant or grammarian. Another
        // team is unaffected - they can still take this expert in whichever
        // role they haven't used them for.
        $existing = TeamExpertAssignment::where('team_id', $team->team_id)
            ->where('expert_id', $expert->expert_id)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            $heldAs = $existing->engaged_role_name;

            return response()->json([
                'success' => false,
                'message' => $heldAs
                    ? "Your team already has a {$existing->status} application with this expert as your ".ucfirst($heldAs).'. An expert can only take one role per team.'
                    : 'Your team already has a pending or approved application with this expert.',
            ], 409);
        }

        // The pre-proposal the CaPSA settled on, when there is one. It is
        // what the team is actually being advised on, and an expert can trust
        // it was approved rather than take the team's word for it.
        $approvedProposal = $team->selected_pre_proposal_id
            ? CapstonePreProposal::find($team->selected_pre_proposal_id)
            : null;

        if (! $request->hasFile('proposal_file') && ! $approvedProposal) {
            return response()->json([
                'success' => false,
                'message' => 'Attach your proposal, or get a pre-proposal approved first and it will be sent for you.',
            ], 422);
        }

        try {
            $filePath = $request->hasFile('proposal_file')
                ? $request->file('proposal_file')->store('proposals', 'public')
                : null;

            $assignment = TeamExpertAssignment::create([
                'team_id' => $team->team_id,
                'expert_id' => $validated['expert_id'],
                'expert_role_id' => $validated['expert_role_id'],
                'status' => 'pending',
                'proposal_file' => $filePath,
                'pre_proposal_id' => $approvedProposal?->id,
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

        // A team cannot drop an expert who already took them on. It asks,
        // and the expert decides; they stay the team's expert until they
        // agree. An application the expert has not answered yet is still
        // the team's to withdraw, below.
        if ($assignment->status === 'approved') {
            $expertName = $assignment->expert?->name ?? 'your expert';

            if (mb_strlen(trim($request->reason)) < ExpertRemovalRequest::REASON_MIN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Give '.$expertName.' a reason they can decide on - at least '.ExpertRemovalRequest::REASON_MIN.' characters.',
                ], 422);
            }

            if (ExpertRemovalRequest::pending()->where('assignment_id', $assignment->assignment_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A request to remove '.$expertName.' is already waiting on them.',
                ], 422);
            }

            ExpertRemovalRequest::create([
                'team_id' => $team->team_id,
                'assignment_id' => $assignment->assignment_id,
                'expert_id' => $assignment->expert_id,
                'requested_by' => $student->student_id,
                'reason' => trim($request->reason),
                'status' => ExpertRemovalRequest::PENDING,
            ]);

            ActivityLog::create([
                'student_id' => $student->student_id,
                'type' => 'expert_removal_requested',
                'description' => 'Asked expert '.($assignment->expert?->name ?? '#'.$assignment->expert_id)." to let the team go — Reason: {$request->reason}",
                'metadata' => ['assignment_id' => $assignment->assignment_id, 'expert_id' => $assignment->expert_id],
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sent to '.$expertName.'. They stay your expert until they agree.',
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
}
