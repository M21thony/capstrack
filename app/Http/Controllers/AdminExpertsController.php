<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\ExpertExpertise;
use App\Models\Notification;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\ExpertCapacity;
use App\Support\SectionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminExpertsController extends Controller
{
    /**
     * Display a listing of the experts. Admin and CaPSA/TSA each get their own
     * separate view now (resources/views/admin/experts.blade.php vs
     * capsa/experts.blade.php) - same URL and route name either way, the
     * view picked just depends on who's looking, so this one method still
     * covers both instead of duplicating the query into two controllers.
     *
     * Admin accounts never appear in this list, for either viewer - Admin
     * is a different kind of account entirely (runs the platform), not a
     * professional role like Adviser/Consultant/Grammarian/CaPSA/TSA that this
     * page exists to manage. This is unconditional, not "hide it from
     * CaPSA/TSA but show it to Admin" - an Admin managing another Admin's
     * account isn't this page's job either.
     */
    public function index()
    {
        $viewer = auth('expert')->user();
        $isCapsaOnly = $viewer && $viewer->is_capsa && ! $viewer->is_admin;

        // Admin's copy of this page is about CaPSA/TSAs and nothing else - the
        // faculty themselves are the CaPSA/TSA's to onboard and give roles to,
        // from their own copy, so listing every expert here only offered the
        // same job from two places with different powers behind it.
        // The only list that shows a deactivated account, because it is the
        // only one that can switch it back on. Everywhere else - the browse
        // page a student applies from, panel assignment, the API - the model's
        // own scope has already taken them out.
        $experts = CapstoneExpert::withTrashed()
            ->where('approved_status', true)
            ->where('is_admin', false)
            ->when(! $isCapsaOnly, fn ($q) => $q->whereHas(
                'roles',
                fn ($r) => $r->where('role_name', CapstoneExpert::CAPSA_ROLE)
            ))
            ->with('expertise', 'roles')
            ->withCount(['teamAssignments' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            // Your own account is always the first row.
            ->sortBy(fn ($e) => $e->expert_id === $viewer?->expert_id ? 0 : 1)
            ->values();

        // Section picker for the "Make CaPSA/TSA" and "Edit sections" modals.
        // Assigning takes a section, not a year: the picker shows each
        // program's letters, and a letter is that section's starting class
        // (3rd year, where capstone starts - "BSIT-3A"). Where that class goes
        // in 4th year is Year Promotion's call. A 4th-year section is only
        // offered on its own, below, once someone holds it or students are in
        // it, so saving a CaPSA/TSA who already has one does not drop it.
        $available = collect(AdminProfileController::getAllAvailableSections());
        $isStarting = fn (string $section) => (bool) preg_match('/^[A-Z]+-'.SectionCatalog::STARTING_YEAR.'[A-Z]*$/', $section);

        $groupedSections = $available->filter($isStarting)
            ->groupBy(fn (string $section) => explode('-', $section)[0]);
        $laterSections = $available->reject($isStarting)->values();

        // Not route names: these are the two view FILES. They read the same
        // as the route names, which is how a bulk rename of route('admin.x')
        // to route('capsa.x') silently pointed both branches at the CaPSA/TSA
        // page and took Add CaPSA/TSA away from Admin.
        // Which sections another CaPSA/TSA already covers, so the picker can
        // leave them out - a section handled twice means two people
        // approving the same teams.
        $sectionOwners = CapstoneExpert::whereHas(
            'roles',
            fn ($q) => $q->where('role_name', CapstoneExpert::CAPSA_ROLE)
        )->get()->flatMap(
            fn ($capsa) => collect($capsa->sections ?? [])->mapWithKeys(
                fn ($section) => [$section => $capsa->expert_id]
            )
        )->all();

        // The Expert coverage tab: the viewer's own teams, as on the dashboard's
        // card, each with the roles it still needs so the chips can list them.
        // Slots and shortages stay program-wide: other sections share them.
        $coverage = [];
        $coverageTeams = [];

        if ($isCapsaOnly) {
            $capacity = app(ExpertCapacity::class);
            $programs = $viewer->handledPrograms();
            // The CaPSA/TSA's own teams, the same list the dashboard card counts.
            $own = app(\App\Support\CapsaReports::class)->context($viewer, [])['teams']->pluck('team_id')->all();
            $coverage = $capacity->forPrograms($programs, $own);
            $missing = $capacity->teamsMissing($programs, $own);
            $coverageTeams = Team::whereIn('team_id', array_keys($missing))->orderBy('team_name')->get()
                ->map(fn (Team $team) => [
                    'name' => $team->team_name,
                    'section' => $team->section(),
                    'program' => $missing[$team->team_id]['program'],
                    'missing' => $missing[$team->team_id]['missing'],
                ])->values()->all();
        }

        return view($isCapsaOnly ? 'capsa.experts' : 'admin.experts', [
            'coverage' => $coverage,
            'coverageTeams' => $coverageTeams,
            'tab' => request('tab') === 'coverage' ? 'coverage' : 'experts',
            'experts' => $experts,
            'groupedSections' => $groupedSections,
            'laterSections' => $laterSections,
            'sectionOwners' => $sectionOwners,
            // Sections whose checklist and requests currently reach nobody.
            'uncoveredSections' => $available->diff(array_keys($sectionOwners))->values(),
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
    /**
     * Add one expert from their email address.
     *
     * The list upload is for a whole faculty at once; this is for the person
     * it missed. Without it a CaPSA/TSA had to re-upload a spreadsheet to admit
     * one late arrival, which is a lot of ceremony for one row - and it is
     * the reason a stricter sign-in rule for experts would have been unfair,
     * because there was no quick way to let somebody in.
     *
     * A role is required, unlike the bulk upload, which reports a row with no
     * recognisable role and moves on. Adding one person deliberately is a
     * decision, and a decision with no role in it leaves an account that can
     * sign in and do nothing.
     */
    public function store(Request $request): JsonResponse
    {
        // Checked by hand rather than through $request->validate(): the only
        // caller is a fetch(), and a failed validate() on a route outside
        // api/* answers with a redirect - HTML where JSON was expected.
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:190'],
            // The address is the whole of what is needed. A name typed here
            // is a guess at how somebody spells their own - Google gives the
            // real one the first time they sign in, which is how Add CaPSA/TSA
            // has always worked. Roles are the CaPSA/TSA's to set and can be set
            // now or from the row afterwards.
            'name' => ['nullable', 'string', 'max:120'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'in:'.implode(',', CapstoneExpert::STUDENT_FACING_ROLES)],
            'expertise' => ['nullable', 'array'],
            'expertise.*' => ['string', 'max:100'],
            'on_study_leave' => ['nullable', 'boolean'],
        ], [
            'email.required' => 'Type the email address of the person to add.',
            'email.email' => 'That is not an email address.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $email = mb_strtolower(trim($request->input('email')));

        // An account on any other domain is one nobody could ever sign in
        // with, so it would look made and then never be reachable.
        if (! Str::endsWith($email, CapstoneExpert::EMAIL_DOMAINS)) {
            return response()->json([
                'success' => false,
                'message' => 'An expert signs in with '.implode(' or ', CapstoneExpert::EMAIL_DOMAINS)
                    .', so an address outside those could never be used.',
            ], 422);
        }

        // Never the Admin. Their account runs the system; it is not an
        // expert, and giving it a student-facing role is what put them in
        // front of every team on student/experts. Hiding them from the
        // directory stopped the suggestion, not this - an address typed out
        // in full arrives here just the same.
        //
        // The refusal does not say why. Naming the account confirms which
        // address belongs to the administrator to anybody who guesses it,
        // and a CaPSA/TSA has no reason to be told. It is still a weak signal -
        // this is the only address that is refused - but a signal that
        // confirms nothing is worth more than one that does.
        $existing = CapstoneExpert::withTrashed()->where('email', $email)->first();

        if ($existing && $existing->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'That address cannot be given a role here.',
            ], 422);
        }

        $expert = $existing;
        $created = false;

        if (! $expert) {
            // Blank stays blank until they sign in and Google says. A degree
            // written after a comma ("Juan Dela Cruz, MIT") is their credentials.
            [$name, $credentials] = CapstoneExpert::splitName((string) $request->input('name'));
            [$firstName, $lastName] = $this->nameFrom($name);

            $expert = CapstoneExpert::create([
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'credentials' => $credentials,
                // CaPSA/TSA naming them is the vetting, the same as including
                // them on the list - there is nobody left to approve them.
                'approved_status' => true,
                'reject_attempts' => 0,
            ]);

            $created = true;
        }

        $roleIds = DB::table('roles')
            ->whereIn('role_name', CapstoneExpert::STUDENT_FACING_ROLES)
            ->pluck('role_id', 'role_name');

        $asked = collect($request->input('roles', []))->filter();

        $wanted = $asked
            ->filter(fn ($r) => $roleIds->has($r))
            ->map(fn ($r) => $roleIds[$r])
            ->all();

        // Named roles that this system has never heard of is a broken
        // install, not an empty form.
        if ($asked->isNotEmpty() && $wanted === []) {
            return response()->json([
                'success' => false,
                'message' => 'Those roles are not set up on this system.',
            ], 500);
        }

        // No roles asked for is a perfectly good state: the person exists and
        // the CaPSA/TSA decides what they are later. Syncing an empty list would
        // strip the roles an existing expert already has.
        if ($wanted !== []) {
            // Scoped to the student-facing roles, so this can neither grant
            // nor revoke CaPSA/TSA - that stays Admin's own action.
            CapstoneExpert::syncRoles($expert->expert_id, $wanted, $roleIds->values());
        }

        // Only when asked, so adding an existing expert again does not clear it.
        if ($request->has('on_study_leave')) {
            $expert->update(['on_study_leave' => $request->boolean('on_study_leave')]);
        }

        // Blank means "not saying", the same as an empty column on the list -
        // an expert fills their own areas in from their profile.
        $expertise = collect($request->input('expertise', []))
            ->map(fn ($e) => trim((string) $e))
            ->filter()
            ->unique()
            ->values();

        if ($expertise->isNotEmpty()) {
            ExpertExpertise::where('expert_id', $expert->expert_id)->delete();

            foreach ($expertise as $field) {
                ExpertExpertise::create(['expert_id' => $expert->expert_id, 'expertise_field' => $field]);
            }
        }

        // Their own name once they have signed in; until then the address is
        // the only thing anybody can recognise them by.
        $label = trim($expert->name) !== '' ? $expert->name : $email;

        return response()->json([
            'success' => true,
            'created' => $created,
            'expert_id' => $expert->expert_id,
            'message' => $created
                ? $label.' was added. They can sign in with '.$email.'.'
                : $label.' already had an account'.($wanted !== [] ? ' - their roles were updated.' : '.'),
        ]);
    }

    /**
     * Edit an existing expert's core profile fields. Roles, is_admin,
     * is_capsa and approved_status are all changed through their own
     * dedicated flows (approval, toggleCapsa), not this generic edit.
     */
    public function update(Request $request, CapstoneExpert $expert): JsonResponse
    {
        // Editing the address is as effective a lock-out as deactivating:
        // change it and the Admin's Google account matches nobody.
        if ($refusal = $this->refuseIfAdmin($expert)) {
            return $refusal;
        }

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
     * PUT /experts/{expert}/study-leave - the expert is taking a master's or
     * similar: still takes teams, but is slower to schedule. Students see it
     * as a quiet tag on the expert's card.
     */
    public function updateStudyLeave(Request $request, CapstoneExpert $expert): JsonResponse
    {
        if ($refusal = $this->refuseIfAdmin($expert)) {
            return $refusal;
        }

        $validated = $request->validate(['on_study_leave' => ['required', 'boolean']]);

        $expert->update(['on_study_leave' => (bool) $validated['on_study_leave']]);

        return response()->json([
            'success' => true,
            'on_study_leave' => $expert->on_study_leave,
            'message' => $expert->on_study_leave
                ? $expert->name.' is marked as on study leave.'
                : $expert->name.' is no longer marked as on study leave.',
        ]);
    }

    /**
     * PUT /experts/{expert}/degree - the one fact the Panel Chair rule reads.
     *
     * Its own resource because it is set from the expert's detail window,
     * which edits nothing else.
     */
    public function updateDegree(Request $request, CapstoneExpert $expert): JsonResponse
    {
        if ($refusal = $this->refuseIfAdmin($expert)) {
            return $refusal;
        }

        $validated = $request->validate([
            // The degree as written after a name (MIT, PhD); it sets the level too.
            'degree' => ['nullable', 'in:'.implode(',', CapstoneExpert::degreeTitles())],
            'highest_degree' => ['nullable', 'in:'.implode(',', array_keys(CapstoneExpert::DEGREES))],
        ]);

        $expert->update($request->has('degree')
            ? CapstoneExpert::degreeFromTitle($validated['degree'] ?? null)
            : ['highest_degree' => $validated['highest_degree'] ?? null, 'degree_title' => null]);

        return response()->json([
            'success' => true,
            'message' => $expert->name.': '.($expert->degreeText() ?? 'no degree').' saved.'
                .($expert->canChairPanel() ? ' They can be Panel Chair.' : ' They cannot be Panel Chair.'),
            'highest_degree' => $expert->highest_degree,
            'degree' => $expert->degree_title,
            'can_chair' => $expert->canChairPanel(),
        ]);
    }

    /**
     * The Admin's account is not editable from here, by anyone.
     *
     * They run the system. There is no second Admin and nobody above them,
     * so an Admin who is deactivated - or whose address is edited into one
     * Google will never return - is the whole system locked out with no way
     * back in short of the database. Every route that changes an expert
     * comes through here first.
     *
     * A CaPSA/TSA is told nothing that identifies the account. The directory
     * already hides the Admin from them, and a refusal that says "that is
     * the administrator" hands back exactly what the hiding was for. The
     * Admin acting on themselves gets the real reason, because they know
     * who they are and a blank refusal would read as a bug.
     */
    private function refuseIfAdmin(CapstoneExpert $expert): ?JsonResponse
    {
        if (! $expert->is_admin) {
            return null;
        }

        $viewer = Auth::guard('expert')->user();
        $itIsThem = $viewer && (int) $viewer->expert_id === (int) $expert->expert_id;

        return response()->json([
            'success' => false,
            'message' => $itIsThem
                ? 'You cannot change your own administrator account here - there would be nobody left to undo it.'
                : 'That account cannot be changed here.',
        ], 422);
    }

    /**
     * Delete an expert and notify all affected team members.
     */
    public function destroy(Request $request, CapstoneExpert $expert): JsonResponse
    {
        if ($refusal = $this->refuseIfAdmin($expert)) {
            return $refusal;
        }

        // Switching yourself off signs you out for good, with nobody on your
        // side of the screen left to switch you back on.
        if ((int) $expert->expert_id === (int) Auth::guard('expert')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot deactivate your own account. Ask another CaPSA/TSA or the admin if it needs to be switched off.',
            ], 422);
        }

        // A CaPSA/TSA's account is the admin's to switch off, not another CaPSA/TSA's.
        if ($expert->is_capsa && ! Auth::guard('expert')->user()?->is_admin) {
            return response()->json(['success' => false, 'message' => 'Only the admin can deactivate a CaPSA/TSA account.'], 422);
        }

        if ($blocker = $expert->deactivationBlocker()) {
            return response()->json(['success' => false, 'message' => $blocker], 422);
        }

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
                $message = '<strong>'.e($expertName)."</strong> ({$roleName}) is no longer available to <strong>".e($teamName).'</strong>, so your team will need to apply to somebody else for that role.';
                if ($reason) {
                    $message .= ' Reason: '.e($reason);
                }

                Notification::create([
                    'student_id' => $studentId,
                    'type' => 'expert_removed',
                    'title' => 'Your '.strtolower($roleName).', '.$expertName.', is no longer available',
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

        // Switched off, not thrown away: the row, their expertise and their
        // grades all stay, so somebody back from study leave is one click away
        // rather than a re-application. Their places on teams do not - see
        // CapstoneExpert::booted().
        $expert->delete();

        $msg = "{$expertName}'s account has been deactivated.";
        if ($notifiedTeams > 0) {
            $msg .= " {$notifiedTeams} team(s) have been notified.";
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
        ]);
    }

    /** PUT /experts/{expert}/activation — switch the account back on. */
    public function reactivate(int $expertId): JsonResponse
    {
        $expert = CapstoneExpert::withTrashed()->findOrFail($expertId);

        if (! $expert->deactivated_at) {
            return response()->json([
                'success' => false,
                'message' => 'That account is already active.',
            ], 422);
        }

        $expert->restore();

        return response()->json([
            'success' => true,
            'message' => "{$expert->name}'s account is active again.",
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
        // nor revoke capstone_adviser (that is Make/Remove CaPSA/TSA's job), and
        // diffing rather than delete-all so an unchanged role keeps its
        // expert_role_id - the handle live assignments point at.
        CapstoneExpert::syncRoles($expert->expert_id, $validated['role_ids'], $allowedRoleIds);

        return response()->json([
            'success' => true,
            'message' => "Roles updated for {$expert->name}.",
        ]);
    }

    /**
     * Change which sections a CaPSA/TSA covers, without touching the role.
     *
     * Sections could only be set while granting CaPSA/TSA, so changing them meant
     * removing the person as CaPSA/TSA and making them one again - which cuts off
     * their account approval, panel, verification and financial access in
     * between, for a change of section that was never about their role.
     */
    public function updateSections(Request $request, CapstoneExpert $expert): JsonResponse
    {
        if ($refusal = $this->refuseIfAdmin($expert)) {
            return $refusal;
        }

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

        // Sections are what a CaPSA/TSA covers, so they only mean anything on
        // someone who holds the role. Setting them on anyone else would leave
        // a value nothing reads, waiting to surprise whoever grants it later.
        if (! $expert->is_capsa) {
            return response()->json([
                'success' => false,
                'message' => 'That expert is not a CaPSA/TSA, so they have no sections to change.',
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
    /**
     * Make somebody a CaPSA/TSA from their email address alone.
     *
     * There is no permanent CaPSA/TSA - the job moves between faculty - and until
     * now making one meant first getting them into the system through the
     * expert list upload, then finding them in it and granting the role. For
     * one person that is a spreadsheet to serve a single row. This makes the
     * account if it does not exist, grants the role, and sets the sections,
     * in one action.
     */
    public function storeCapsa(Request $request): JsonResponse
    {
        // Checked by hand rather than through $request->validate(): the only
        // caller is a fetch(), and a failed validate() on a route outside
        // api/* answers with a redirect - which reaches the browser as a page
        // of HTML where JSON was expected, and fails in silence.
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            // Only the address is required. A name typed here is a guess that
            // Google overwrites on the person's first sign-in anyway, and the
            // sections they cover can be given to them later from this same
            // modal - neither is worth blocking the invitation on.
            'name' => ['nullable', 'string', 'max:120'],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string'],
        ], [
            'email.required' => 'Type the email address of the person to make a CaPSA/TSA.',
            'email.email' => 'That is not an email address.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $email = mb_strtolower(trim($request->input('email')));

        // An account on any other domain is one nobody could ever sign in
        // with, so it would look created and then never be reachable.
        if (! Str::endsWith($email, CapstoneExpert::EMAIL_DOMAINS)) {
            return response()->json([
                'success' => false,
                'message' => 'A CaPSA/TSA signs in with '.implode(' or ', CapstoneExpert::EMAIL_DOMAINS)
                    .', so an address outside those could never be used.',
            ], 422);
        }

        $roleId = DB::table('roles')->where('role_name', CapstoneExpert::CAPSA_ROLE)->value('role_id');

        if (! $roleId) {
            return response()->json([
                'success' => false,
                'message' => 'The Capstone Subject Adviser role is missing from the roles table.',
            ], 500);
        }

        // Never the Admin. Their account runs the system; it is not an
        // expert, and giving it a student-facing role is what put them in
        // front of every team on student/experts. Hiding them from the
        // directory stopped the suggestion, not this - an address typed out
        // in full arrives here just the same.
        //
        // The refusal does not say why. Naming the account confirms which
        // address belongs to the administrator to anybody who guesses it,
        // and a CaPSA/TSA has no reason to be told. It is still a weak signal -
        // this is the only address that is refused - but a signal that
        // confirms nothing is worth more than one that does.
        $existing = CapstoneExpert::withTrashed()->where('email', $email)->first();

        if ($existing && $existing->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'That address cannot be given a role here.',
            ], 422);
        }

        $expert = $existing;
        $created = false;

        if (! $expert) {
            // Blank stays blank rather than becoming something derived from
            // the address; the list says so in the row itself.
            [$name, $credentials] = CapstoneExpert::splitName((string) $request->input('name'));
            [$firstName, $lastName] = $this->nameFrom($name);

            $expert = CapstoneExpert::create([
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'credentials' => $credentials,
                // Admin naming them is the vetting, so there is nobody left
                // to approve them afterwards.
                'approved_status' => true,
                'reject_attempts' => 0,
            ]);

            $created = true;
        }

        DB::table('expert_roles')->insertOrIgnore([
            'expert_id' => $expert->expert_id,
            'role_id' => $roleId,
        ]);

        // Optional now, so an empty submission must not wipe the sections an
        // existing CaPSA/TSA already handles.
        if ($request->has('sections')) {
            $expert->sections = $request->input('sections', []);
        }

        $expert->save();

        // Their name if it is known, their address if it is not.
        $label = $expert->name !== '' ? $expert->name : $email;

        return response()->json([
            'success' => true,
            'created' => $created,
            'name' => $expert->name,
            'message' => $created
                ? "{$label} was made a CaPSA/TSA. They can sign in with {$email}."
                : "{$label} is now a CaPSA/TSA.",
        ]);
    }

    /**
     * A full name, split into the parts the rest of the app stores.
     *
     * On the LAST space, not the first: "Mark Anthony Balilla" is Mark
     * Anthony Balilla, not Mark, of the family Anthony Balilla. Two given
     * names is the ordinary case here, not the exception.
     *
     * Add Expert still asks for a name; Add CaPSA/TSA does not, and a blank one
     * gives back two blank strings. It must never be read off the address
     * instead: that works for "juan.delacruz@" but produces
     * "Markanthonybalilla21" for an address with nothing to split on, which
     * would then land on every screen showing a name, in every panel list,
     * and in the emails the system sends. Nothing at all is easier to read
     * as "not known yet" - and Google fills it in at sign-in.
     *
     * @return array{0: string, 1: string} first, last
     */
    private function nameFrom(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));

        $at = mb_strrpos($name, ' ');

        if ($at === false) {
            // One word is one word - somebody who goes by a single name keeps
            // it, rather than having it written into both halves.
            return [$name, ''];
        }

        return [mb_substr($name, 0, $at), mb_substr($name, $at + 1)];
    }

    public function toggleCapsa(Request $request, CapstoneExpert $expert): JsonResponse
    {
        if ($refusal = $this->refuseIfAdmin($expert)) {
            return $refusal;
        }

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

        // Read once, up front - being a CaPSA/TSA is just holding this role, so
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

            // Taking the role away frees their sections too. Left on the row,
            // TeamChecklist::capsaFor() - which reads sections, not the role -
            // kept sending those sections' checklist and requests to someone
            // who is no longer a CaPSA/TSA, and the picker hid them from everyone
            // else as already covered.
            if (! $willBeCapsa) {
                $expert->sections = [];
                $expert->save();
            } elseif (array_key_exists('sections', $validated)) {
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
