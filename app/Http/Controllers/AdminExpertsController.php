<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\StudentSchoolYear;
use App\Models\TeamExpertAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminExpertsController extends Controller
{
    /**
     * Display a listing of the experts. Admin and CaPSA each get their own
     * separate view now (resources/views/admin/experts.blade.php vs
     * capsa/experts.blade.php) - same URL and route name either way, the
     * view picked just depends on who's looking, so this one method still
     * covers both instead of duplicating the query into two controllers.
     *
     * Admin accounts never appear in this list, for either viewer - Admin
     * is a different kind of account entirely (runs the platform), not a
     * professional role like Adviser/Consultant/Grammarian/CaPSA that this
     * page exists to manage. This is unconditional, not "hide it from
     * CaPSA but show it to Admin" - an Admin managing another Admin's
     * account isn't this page's job either.
     */
    public function index()
    {
        $viewer = auth('expert')->user();
        $isCapsaOnly = $viewer && $viewer->is_capsa && ! $viewer->is_admin;

        $experts = CapstoneExpert::where('approved_status', true)
            ->where('is_admin', false)
            ->with('expertise', 'roles')
            ->withCount(['teamAssignments' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // Section picker for the "Make CaPSA" modal - grouped by program
        // (the part of the label before the dash, e.g. "BSIT-3A" -> "BSIT")
        // so a CaPSA covering every section of a program is one click on
        // that group, not checking each section one at a time.
        $groupedSections = collect(AdminProfileController::getAllAvailableSections())
            ->groupBy(fn ($section) => explode('-', $section)[0] ?? $section);

        return view($isCapsaOnly ? 'capsa.experts' : 'admin.experts', [
            'experts' => $experts,
            'groupedSections' => $groupedSections,
        ]);
    }

    /**
     * Return a JSON list of approved experts for API consumers.
     */
    public function apiIndex(): JsonResponse
    {
        $experts = CapstoneExpert::where('approved_status', true)
            ->orderBy('created_at', 'desc')
            ->get();
        // Optionally eager load relationships if needed by the client
        $experts->load('expertise', 'roles');

        return response()->json(['success' => true, 'data' => $experts]);
    }

    /**
     * Manually add a single expert account (bypassing the self-service
     * request+approval flow - e.g. an emergency add CAPSA needs done now).
     * Pre-approved immediately, same as a roster-uploaded row.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:190', 'unique:capstone_experts,email'],
            'credentials' => ['nullable', 'string', 'max:190'],
            'program' => ['nullable', 'string', 'max:100'],
            'max_teams' => ['nullable', 'integer', 'min:0'],
        ]);

        $expert = CapstoneExpert::create($validated + ['approved_status' => true]);

        return response()->json([
            'success' => true,
            'message' => "Expert {$expert->name} has been added.",
            'expert_id' => $expert->expert_id,
        ]);
    }

    /**
     * Edit an existing expert's core profile fields. Roles, is_admin,
     * is_capsa and approved_status are all changed through their own
     * dedicated flows (approval, toggleCapsa), not this generic edit.
     */
    public function update(Request $request, CapstoneExpert $expert): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:190', 'unique:capstone_experts,email,'.$expert->expert_id.',expert_id'],
            'credentials' => ['nullable', 'string', 'max:190'],
            'program' => ['nullable', 'string', 'max:100'],
            'max_teams' => ['nullable', 'integer', 'min:0'],
        ]);

        $expert->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Expert {$expert->name} has been updated.",
        ]);
    }

    /**
     * Delete an expert and notify all affected team members.
     */
    public function destroy(Request $request, CapstoneExpert $expert): JsonResponse
    {
        $reason = trim($request->input('reason', ''));
        $expertName = $expert->name;
        $notifiedTeams = 0;

        $approvedAssignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)
            ->where('status', 'approved')
            ->with('team', 'role', 'expertRole.role')
            ->get();

        foreach ($approvedAssignments as $assignment) {
            $team = $assignment->team;
            if (! $team) {
                continue;
            }

            $roleName = $assignment->engaged_role_name ?? 'Expert';
            $teamName = $team->team_name ?? 'Unknown Team';

            $memberIds = StudentSchoolYear::where('team_id', $team->team_id)
                ->whereNotNull('student_id')
                ->pluck('student_id');

            foreach ($memberIds as $studentId) {
                $message = 'Your expert <strong>'.e($expertName)."</strong> ({$roleName}) has been removed from <strong>".e($teamName).'</strong>.';
                if ($reason) {
                    $message .= ' Reason: '.e($reason);
                }

                Notification::create([
                    'student_id' => $studentId,
                    'type' => 'expert_removed',
                    'title' => 'Expert Removed from Your Team',
                    'message' => $message,
                    'link' => route('student.team'),
                    'is_read' => false,
                    'created_at' => now(),
                ]);
            }

            $notifiedTeams++;
        }

        cache()->put('expert_removed_'.$expert->expert_id, [
            'name' => $expertName,
            'reason' => $reason,
        ], now()->addMinutes(10));

        $expert->delete();

        $msg = "Expert {$expertName} has been removed.";
        if ($notifiedTeams > 0) {
            $msg .= " {$notifiedTeams} team(s) have been notified.";
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
        ]);
    }

    /**
     * Replace an already-approved expert's academic roles (adviser /
     * consultant / grammarian). Role assignment used to only happen once,
     * at account approval (AdminApprovalController::approve()) - this is
     * the same set of roles, just reachable any time afterward instead of
     * locked in at that one moment. Panel Member isn't offered here since
     * it's assigned per-defense (AdminPanelAssignmentController), not to
     * the account itself, and capstone_adviser/is_admin/is_capsa each stay
     * on their own dedicated flow.
     */
    public function updateRoles(Request $request, CapstoneExpert $expert): JsonResponse
    {
        $validated = $request->validate([
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,role_id'],
        ]);

        $allowedRoleIds = DB::table('roles')
            ->whereIn('role_name', CapstoneExpert::STUDENT_FACING_ROLES)
            ->pluck('role_id');

        // Scoped to the student-facing roles so this screen can neither grant
        // nor revoke capstone_adviser (that is Make/Remove CaPSA's job), and
        // diffing rather than delete-all so an unchanged role keeps its
        // expert_role_id - the handle live assignments point at.
        CapstoneExpert::syncRoles($expert->expert_id, $validated['role_ids'], $allowedRoleIds);

        return response()->json([
            'success' => true,
            'message' => "Roles updated for {$expert->name}.",
        ]);
    }

    /**
     * Change which sections a CaPSA covers, without touching the role.
     *
     * Sections could only be set while granting CaPSA, so changing them meant
     * removing the person as CaPSA and making them one again - which cuts off
     * their account approval, panel, verification and financial access in
     * between, for a change of section that was never about their role.
     */
    public function updateSections(Request $request, CapstoneExpert $expert): JsonResponse
    {
        // Checked by hand rather than through $request->validate(): the only
        // caller is a fetch(), and a failed validate() on a route outside
        // api/* answers with a redirect - which reaches the browser as a page
        // of HTML where JSON was expected, and fails in silence.
        $validator = Validator::make($request->all(), [
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['string'],
        ], [
            'sections.required' => 'Choose at least one section.',
            'sections.min' => 'Choose at least one section.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();

        // Sections are what a CaPSA covers, so they only mean anything on
        // someone who holds the role. Setting them on anyone else would leave
        // a value nothing reads, waiting to surprise whoever grants it later.
        if (! $expert->is_capsa) {
            return response()->json([
                'success' => false,
                'message' => 'That expert is not a CaPSA, so they have no sections to change.',
            ], 422);
        }

        $expert->sections = array_values(array_unique($validated['sections']));
        $expert->save();

        return response()->json([
            'success' => true,
            'sections' => $expert->fresh()->sections,
        ]);
    }

    // Replaces the old, broken "/turnover" route (its controller method
    // never existed, so it 500'd if ever hit). No exclusivity - more than
    // one expert can hold CAPSA at once, each covering their own section(s)
    // (one CAPSA can have multiple sections), so turning one on never turns
    // another off.
    public function toggleCapsa(Request $request, CapstoneExpert $expert): JsonResponse
    {
        $validated = $request->validate([
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string'],
        ]);

        $roleId = DB::table('roles')->where('role_name', CapstoneExpert::CAPSA_ROLE)->value('role_id');

        if (! $roleId) {
            return response()->json([
                'success' => false,
                'message' => 'The Capstone Subject Adviser role is missing from the roles table.',
            ], 500);
        }

        // Read once, up front - being a CaPSA is just holding this role, so
        // granting/revoking it IS the toggle. There is no is_capsa column to
        // keep in step any more.
        $willBeCapsa = ! $expert->is_capsa;

        DB::transaction(function () use ($expert, $validated, $roleId, $willBeCapsa) {
            if ($willBeCapsa) {
                DB::table('expert_roles')->insertOrIgnore([
                    'expert_id' => $expert->expert_id,
                    'role_id' => $roleId,
                ]);
            } else {
                DB::table('expert_roles')
                    ->where('expert_id', $expert->expert_id)
                    ->where('role_id', $roleId)
                    ->delete();
            }

            if (array_key_exists('sections', $validated)) {
                $expert->sections = $validated['sections'];
                $expert->save();
            }
        });

        return response()->json([
            'success' => true,
            'is_capsa' => $willBeCapsa,
            'sections' => $expert->fresh()->sections,
        ]);
    }
}
