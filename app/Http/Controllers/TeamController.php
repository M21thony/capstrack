<?php

namespace App\Http\Controllers;

use App\Models\DefenseSchedule;
use App\Models\MemberLeaveRequest;
use App\Models\Notification;
use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\Term;
use App\Support\DefenseProgress;
use App\Support\ProjectTags;
use App\Support\StudentTeam;
use App\Support\TitleRevision;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function checkName(Request $request): JsonResponse
    {
        $name = trim((string) $request->query('name'));
        $ignoreId = $request->query('ignore_team_id');

        $exists = $name !== '' && Team::nameExists($name, $ignoreId ? (int) $ignoreId : null);

        return response()->json([
            'exists' => $exists,
            'message' => $exists ? 'This team name is already taken.' : '',
        ]);
    }

    public function index(Request $request)
    {
        $studentId = auth()->id() ?? 8;
        $student = Student::find($studentId);

        if (! $student) {
            $student = auth()->user();
            $studentId = $student ? $student->student_id : 8;
        }

        $teamId = StudentTeam::idFor($studentId);

        // The enrolment row itself, for the school year and the role on it -
        // a different question from which team they are in, and one a student
        // with no team still has an answer to.
        $studentSy = StudentSchoolYear::where('student_id', $studentId)->first();

        $myTeam = null;
        $teamMembers = collect();

        if ($teamId) {
            $myTeam = Team::find($teamId);
            $teamLeaderId = (int) $myTeam?->leader_id;
            $teamMembers = Student::join('student_s_y', 'students.student_id', '=', 'student_s_y.student_id')
                ->leftJoin('student_roles', 'student_s_y.s_role_id', '=', 'student_roles.s_role_id')
                ->where('student_s_y.team_id', $teamId)
                ->select('students.*', 'student_s_y.s_role_id', 'student_roles.s_role_name')
                ->get()
                ->sortBy(function ($member) use ($studentId, $teamLeaderId) {
                    $isLeader = (int) $member->student_id === $teamLeaderId;
                    $isSelf = $member->student_id === $studentId;

                    if ($isLeader) {
                        return 0;
                    }
                    if ($isSelf) {
                        return 1;
                    }

                    return 2;
                })->values();
        }

        $myRecruitment = Recruitment::where('student_id', $studentId)->latest()->first();

        $skills = [];
        if ($myRecruitment && is_array($myRecruitment->roles)) {
            $skills = $myRecruitment->roles;
        }

        $memberCount = 5;
        $isLeader = $myTeam && (int) $myTeam->leader_id === (int) $studentId;
        $teamFull = $myTeam && ! $myTeam->is_confirmed && $isLeader && $teamMembers->count() >= $memberCount;
        $isTeamFull = $myTeam && $teamMembers->count() >= $memberCount;
        $isConfirmed = $myTeam && $myTeam->is_confirmed;
        $postHidden = $myTeam && ($myTeam->team_status === 'Full' || $myTeam->hasSubmittedPreProposals());

        $fullTeamStudentIds = Recruitment::where('recruitment_goal', 'build_a_team')
            ->with('student.team')
            ->get()
            ->filter(function ($recruit) {
                $team = $recruit->student?->team;

                if (! $team) {
                    return false;
                }

                return StudentSchoolYear::where('team_id', $team->team_id)->count() >= ($team->member_count ?? $recruit->member_count ?? 5);
            })
            ->pluck('student_id')
            ->toArray();

        $leaderIds = $myTeam ? [(int) $myTeam->leader_id] : [];

        $currentSchoolYear = $studentSy?->school_year;

        // Dynamic Section Overview Statistics (filtered by same course, year_section and school year)
        $totalStudents = StudentSchoolYear::join('students', 'student_s_y.student_id', '=', 'students.student_id')
            ->where('student_s_y.school_year', $currentSchoolYear)
            ->where('students.course', $student->course)
            ->where('students.year_section', $student->year_section)
            ->count();

        $teamedUp = StudentSchoolYear::join('students', 'student_s_y.student_id', '=', 'students.student_id')
            ->where('student_s_y.school_year', $currentSchoolYear)
            ->where('students.course', $student->course)
            ->where('students.year_section', $student->year_section)
            ->whereNotNull('student_s_y.team_id')
            ->count();

        $stillIndividual = StudentSchoolYear::join('students', 'student_s_y.student_id', '=', 'students.student_id')
            ->where('student_s_y.school_year', $currentSchoolYear)
            ->where('students.course', $student->course)
            ->where('students.year_section', $student->year_section)
            ->whereNull('student_s_y.team_id')
            ->count();

        // Feed recruitment posts (only from students in the same course, year_section and school year)
        // Exclude posts from leaders whose team is complete, closed, or full
        $otherRecruitments = Recruitment::with(['student.team'])
            ->where('student_id', '!=', $studentId)
            ->whereNotIn('student_id', $fullTeamStudentIds)
            ->whereDoesntHave('student.team', function ($q) {
                // Off the feed once the team stops taking members OR has
                // turned its pre-proposals in - two separate reasons, which
                // is exactly what one status column used to blur.
                $q->where('team_status', 'Full')
                    ->orWhereHas('preProposals', fn ($p) => $p->whereNotNull('submitted_at'));
            })
            ->whereIn('student_id', function ($query) use ($student, $currentSchoolYear) {
                $query->select('student_s_y.student_id')
                    ->from('student_s_y')
                    ->join('students', 'student_s_y.student_id', '=', 'students.student_id')
                    ->where('student_s_y.school_year', $currentSchoolYear)
                    ->where('students.course', $student->course)
                    ->where('students.year_section', $student->year_section);
            })
            ->latest()
            ->paginate(5);

        // Pending Invites Collection: both team invites sent to this student AND join requests sent to this student
        $pendingInvitations = TeamInvitation::with(['team', 'sender', 'receiver'])
            ->where('receiver_id', $studentId)
            ->where('status', 'pending')
            ->get();

        $invites = $pendingInvitations->where('type', 'invite')->values();
        $incomingRequests = $pendingInvitations->where('type', 'request')->values();

        // Outgoing join requests sent by this student that are still pending
        $outgoingRequests = TeamInvitation::with(['team', 'receiver'])
            ->where('sender_id', $studentId)
            ->where('type', 'request')
            ->where('status', 'pending')
            ->latest('created_at')
            ->get();

        // The other direction: invitations this leader has sent that are still
        // waiting on an answer. Without these the slot they are holding looked
        // empty, so a leader could invite five people into three places.
        $outgoingInvitations = TeamInvitation::with(['receiver'])
            ->where('sender_id', $studentId)
            ->where('type', 'invite')
            ->where('status', 'pending')
            ->latest('created_at')
            ->get();

        $hasPendingRequest = $outgoingRequests->isNotEmpty();

        // Leaving always waits on the CaPSA; after Title Defense so does
        // removing a member.
        $teamLocked = $myTeam?->isTeamLocked() ?? false;
        $leaveRequests = $myTeam
            ? MemberLeaveRequest::with(['student', 'requester'])
                ->pending()
                ->where('team_id', $myTeam->team_id)
                ->oldest()
                ->get()
            : collect();

        $teamLeaderName = null;

        if ($myTeam && $myTeam->leader_id) {
            $leader = Student::find($myTeam->leader_id);
            $teamLeaderName = $leader ? trim(($leader->f_name ?? '').' '.($leader->l_name ?? '')) : null;
        }

        $userRole = $studentSy?->role?->s_role_name;

        // A member who has joined but not yet said what they do. The team
        // page asks them once, and only once - a role is settled for the
        // semester after that.
        $needsRole = $myTeam && ! $isLeader && ! $studentSy?->s_role_id;
        $roleChoices = $needsRole ? $this->roleChoicesFor($myTeam) : [];

        $expertRemovedNotifications = Notification::where('student_id', $studentId)
            ->where('type', 'expert_removed')
            ->where('is_read', false)
            ->latest('created_at')
            ->get();

        $pendingLeadershipTransfer = Notification::where('student_id', $studentId)
            ->where('type', 'leadership_transfer_request')
            ->where('is_read', false)
            ->get()
            ->first(function ($notif) {
                $remindAt = $notif->data['remind_me_at'] ?? null;

                return ! $remindAt || now()->gte(Carbon::parse($remindAt));
            });

        // The panel sitting on the team's next defense. Assigning a panel
        // (AdminPanelAssignmentController::assign) writes the same three
        // experts onto every stage's schedule at once, so the current stage's
        // row is where to look - falling back to whichever schedule most
        // recently carried a panel, so a team that has finished every stage
        // still sees who sat on its last one instead of an empty card.
        $panel = collect();
        $panelDefenseType = null;

        if ($myTeam) {
            $stage = (new DefenseProgress)->currentStage($myTeam);

            $schedule = DefenseSchedule::where('team_id', $myTeam->team_id)
                ->when($stage, fn ($q) => $q->where('defense_type', $stage))
                ->latestAttempt()
                ->with('panels.expert')
                ->first();

            if (! $schedule || $schedule->panels->isEmpty()) {
                $schedule = DefenseSchedule::where('team_id', $myTeam->team_id)
                    ->whereHas('panels')
                    ->with('panels.expert')
                    ->latest('defense_id')
                    ->first();
            }

            if ($schedule) {
                $panelDefenseType = $schedule->defense_type;
                $panel = $schedule->panels->sortBy(fn ($p) => match ($p->panel_role) {
                    'Panel Chair' => 0,
                    'Panel Member 1' => 1,
                    'Panel Member 2' => 2,
                    default => 3,
                })->values();
            }
        }

        return view('student.team', [
            'myTeam' => $myTeam,
            'panel' => $panel,
            'panelDefenseType' => $panelDefenseType,
            // The CaPSA's Team Rules, so a closed door is said before anyone fills in a form.
            'creationAllowed' => Team::creationAllowed(),
            'joiningAllowed' => Team::joiningAllowed(),
            // What a recruitment post may advertise for. Project Head is left
            // out: that seat belongs to whoever leads the team.
            'memberRoleNames' => StudentRole::memberRoles()->pluck('s_role_name')->all(),
            'needsRole' => $needsRole,
            'roleChoices' => $roleChoices,
            'teamMembers' => $teamMembers,
            'myRecruitment' => $myRecruitment,
            'myRecruitmentGoal' => $myRecruitment?->recruitment_goal,
            'isTeamFull' => $isTeamFull,
            'skills' => $skills,
            'memberCount' => $memberCount,
            'isLeader' => $isLeader,
            'otherRecruitments' => $otherRecruitments,
            'totalStudents' => $totalStudents,
            'teamedUp' => $teamedUp,
            'stillIndividual' => $stillIndividual,
            'invites' => $invites,
            'incomingRequests' => $incomingRequests,
            'outgoingRequests' => $outgoingRequests,
            'outgoingInvitations' => $outgoingInvitations,
            'hasPendingRequest' => $hasPendingRequest,
            'teamLocked' => $teamLocked,
            'teamLockedAt' => $teamLocked ? $myTeam->teamLockedAt() : null,
            'leaveRequests' => $leaveRequests,
            'leaderIds' => $leaderIds,
            'userRole' => $userRole,
            'expertRemovedNotifications' => $expertRemovedNotifications,
            'pendingLeadershipTransfer' => $pendingLeadershipTransfer,
        ]);
    }

    /**
     * The roles this team's next member may pick from.
     *
     * Narrowed to what the team's recruitment post asked for, when it asked
     * for anything - a team advertising for a programmer and a tester should
     * not have somebody arrive as a UI designer. With no post, or none of its
     * roles left with a seat, every member role with room is on the table,
     * because the alternative is a student who cannot answer the question.
     *
     * @return array<int, array{id: int, name: string, duties: ?string, taken: int, seats: int, held_by: array<int, string>, full: bool}>
     */
    private function roleChoicesFor(Team $team): array
    {
        $seating = StudentRole::seatingFor($team->team_id);

        $wanted = collect((array) Recruitment::where('student_id', $team->leader_id)
            ->where('recruitment_goal', 'build_a_team')
            ->value('roles'))->filter();

        $asked = collect($seating)
            ->filter(fn ($row) => $wanted->contains($row['role']->s_role_name))
            ->filter(fn ($row) => $row['seats'] === null || $row['taken'] < $row['seats']);

        $offer = $asked->isNotEmpty() ? $asked : collect($seating);

        return $offer->map(fn ($row) => [
            'id' => $row['role']->s_role_id,
            'name' => $row['role']->s_role_name,
            'duties' => $row['role']->s_role_description,
            'taken' => $row['taken'],
            'seats' => $row['seats'],
            'held_by' => $row['held_by'],
            // A role with no seat limit never fills.
            'full' => $row['seats'] !== null && $row['taken'] >= $row['seats'],
        ])->values()->all();
    }

    /**
     * PUT /team/role - a member records what they do on the team.
     *
     * Once. The leader does not choose for anybody, and nobody changes their
     * mind afterwards; the only thing that moves a role once it is set is a
     * transfer of leadership, which swaps two of them at once.
     */
    public function updateRole(Request $request): JsonResponse|RedirectResponse
    {
        $studentId = $request->user()->student_id;
        $studentSy = StudentSchoolYear::where('student_id', $studentId)->first();
        $team = $studentSy?->team_id ? Team::find($studentSy->team_id) : null;

        if (! $team) {
            return $this->roleAnswer($request, false, 'Join a team before choosing a role.');
        }

        if ((int) $team->leader_id === (int) $studentId) {
            return $this->roleAnswer($request, false, 'The team leader is the Project Head.');
        }

        if ($studentSy->s_role_id) {
            return $this->roleAnswer($request, false, 'Your role is already set for this semester.');
        }

        $roleId = (int) $request->validate([
            'role_id' => ['required', 'integer'],
        ])['role_id'];

        $allowed = collect($this->roleChoicesFor($team))
            ->reject(fn ($choice) => $choice['full'])
            ->pluck('id');

        if (! $allowed->contains($roleId)) {
            return $this->roleAnswer($request, false, 'That role is not open on this team.');
        }

        // Checked again at the moment of writing: two people can be sitting
        // on this dialog at once, and the list they were shown is as old as
        // whenever the page last loaded.
        if (! StudentRole::hasSeat($team->team_id, $roleId)) {
            return $this->roleAnswer($request, false, 'Somebody just took the last seat in that role. Pick another.');
        }

        $studentSy->update(['s_role_id' => $roleId]);

        $roleName = StudentRole::find($roleId)?->s_role_name ?? 'a role';

        $this->logActivity(
            $studentId,
            'role_chosen',
            'Took the role of <strong>'.e($roleName).'</strong>',
            ['team_id' => $team->team_id, 's_role_id' => $roleId]
        );

        return $this->roleAnswer($request, true, 'You are the '.$roleName.'.');
    }

    private function roleAnswer(Request $request, bool $ok, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => $ok, 'message' => $message], $ok ? 200 : 422);
        }

        return redirect()->route('student.team')->with($ok ? 'status' : 'error', $message);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        // Before validation, so a closed rule is the answer a student gets -
        // not a complaint about the team name they typed into it.
        $closed = $request->user()->capstoneStage() !== 1 ? Team::NO_NEW_TEAMS
            : (Team::creationAllowed() ? null : Team::CREATION_CLOSED);

        if ($closed) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $closed], 422);
            }

            return redirect()->route('student.team')->with('error', $closed);
        }

        $validated = $request->validate([
            'team_name' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) {
                if (Team::nameExists($value)) {
                    $fail('The team name has already been taken.');
                }
            }],
            'team_status' => ['nullable', Rule::in(['Active', 'Full'])],
        ]);

        $student = $request->user();

        if (StudentSchoolYear::where('student_id', $student->student_id)->whereNotNull('team_id')->exists()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'You are already in a team.'], 422);
            }

            return redirect()->route('student.team')->with('error', 'You are already in a team.');
        }

        // No project_title here: a team's title is not typed, it is whichever
        // pre-proposal gets approved (see AdminTeamVerificationController's
        // resolveTitleIfSettled), so a new team simply has none yet.
        $team = Team::create([
            'team_name' => $validated['team_name'],
            'team_status' => $validated['team_status'] ?? 'Active',
            'leader_id' => $student->student_id,
        ]);

        DB::table('student_s_y')
            ->updateOrInsert(
                // Keyed on the term too: this table is one row per student PER
                // TERM, so keying on the student alone would overwrite last
                // semester's record instead of starting this one.
                ['student_id' => $student->student_id, 'term_id' => Term::activeId()],
                [
                    'team_id' => $team->team_id,
                    's_role_id' => StudentRole::leaderRoleId(),
                    // Written straight through the query builder, so the
                    // model's creating hook never fires - the year has to be
                    // named here or the row lands with none, which is how
                    // every row on the live database ended up null.
                    'school_year' => Term::currentSchoolYear(),
                ]
            );

        // Keep member count synced at the fixed 5-member limit
        $recruitment = Recruitment::where('student_id', $student->student_id)
            ->where('recruitment_goal', 'build_a_team')
            ->first();
        if ($recruitment) {
            $recruitment->update(['member_count' => 5]);
            $team->update(['member_count' => 5]);
        }

        // Only delete "find_a_team" posts — keep "build_a_team" so leader status is preserved
        Recruitment::where('student_id', $student->student_id)
            ->where('recruitment_goal', 'find_a_team')
            ->delete();

        $this->logActivity(
            $student->student_id,
            'team_created',
            'Created team <strong>'.e($team->team_name).'</strong>',
            ['team_id' => $team->team_id, 'team_name' => $team->team_name]
        );

        // Notify team creator
        Notification::create([
            'student_id' => $student->student_id,
            'type' => 'team_created',
            'title' => 'Team Created',
            'message' => 'Your team <strong>'.e($team->team_name).'</strong> has been created.',
            'link' => route('student.team'),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Team created successfully.',
                'redirect_url' => route('student.team'),
                'team' => $team,
            ], 201);
        }

        return redirect()
            ->route('student.team')
            ->with('status', 'Team "'.$team->team_name.'" created successfully!');
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $student = $request->user();
        $studentId = $student->student_id;

        if ((int) $team->leader_id !== (int) $studentId) {
            return redirect()->route('student.team')
                ->with('error', 'Only the team leader can update the team.');
        }

        if ($team->is_confirmed && $request->has('team_status')) {
            return redirect()->route('student.team')
                ->with('error', 'Cannot change status. Team is already verified.');
        }

        $validated = $request->validate([
            'team_name' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) use ($team) {
                if (Team::nameExists($value, $team->team_id)) {
                    $fail('The team name has already been taken.');
                }
            }],
            'team_status' => ['nullable', Rule::in(['Active', 'Full'])],
            'tags' => ['nullable', 'json'],
        ]);

        // The tags are the capstone title's own: the ones the team picked on
        // the pre-proposal that became its title (or kept on the team, for a
        // title set without one). They are edited here, from the same fixed
        // list, and a title always keeps at least one. Before there is a
        // title there is nothing to tag, so tags are left alone.
        $hasTitle = (bool) ($team->project_title || $team->selectedPreProposal);
        $titleProposal = $hasTitle ? $team->titleProposal() : null;
        $titleTags = null;

        // The title itself can be corrected here - a misspelling, a wrong
        // case, a word or two. A reworded title needs the panel's "passed
        // with revision"; a different one goes through the CaPSA. Checked
        // before anything is saved, so a refused title changes nothing.
        $oldTitle = (string) $team->project_title;
        $newTitle = null;

        if ($team->project_title && $request->filled('project_title')) {
            $candidate = trim(preg_replace('/\s+/', ' ', (string) $request->input('project_title')));
            $canRevise = TitleRevision::canRevise($team);

            Validator::make(['project_title' => $candidate], [
                'project_title' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) use ($oldTitle, $canRevise) {
                    if (! $canRevise && ! TitleRevision::isCorrection($oldTitle, $value)) {
                        $fail('That is more than a spelling fix. Only small corrections can be made here - a different title goes through your CaPSA.');
                    }
                }],
            ])->validate();

            $newTitle = $candidate !== $oldTitle ? $candidate : null;
        }

        if ($hasTitle && $request->has('tags')) {
            $titleTags = Validator::make(
                ['tags' => json_decode($request->input('tags'), true)],
                [
                    'tags' => ['required', 'array', 'min:'.ProjectTags::MIN, 'max:'.ProjectTags::MAX],
                    'tags.*' => ['string', 'distinct', Rule::in(ProjectTags::OPTIONS)],
                ],
                [
                    'tags.required' => 'Keep at least one tag on your capstone title.',
                    'tags.array' => 'Keep at least one tag on your capstone title.',
                    'tags.min' => 'Keep at least one tag on your capstone title.',
                    'tags.max' => 'Pick at most '.ProjectTags::MAX.' tags.',
                    'tags.*.in' => 'Pick tags from the list.',
                    'tags.*.distinct' => 'Each tag can only be added once.',
                ],
            )->validate()['tags'];

            $titleProposal?->update(['tags' => array_values($titleTags)]);
        }

        $originalStatus = $team->team_status;

        if ($newTitle !== null) {
            $team->update(['project_title' => $newTitle]);

            // Written down, so the CaPSA can see what the approved title became.
            $this->logActivity(
                $studentId,
                'title_corrected',
                'Corrected the capstone title of <strong>'.e($team->team_name).'</strong> from <strong>'.e($oldTitle).'</strong> to <strong>'.e($newTitle).'</strong>',
                ['team_id' => $team->team_id, 'old' => $oldTitle, 'new' => $newTitle]
            );
        }

        $team->update([
            'team_name' => $validated['team_name'],
            // Mirrored onto the team, which panel suggestions and the CaPSA's
            // team list read.
            'tags' => $titleTags !== null ? array_values($titleTags) : $team->tags,
            'team_status' => $validated['team_status'] ?? $team->team_status,
            'member_count' => 5,
        ]);

        // Keep recruitment post in sync with the fixed 5-member limit
        Recruitment::where('student_id', $studentId)
            ->where('recruitment_goal', 'build_a_team')
            ->update(['member_count' => 5]);

        // Auto-close team if it has reached full capacity
        $autoClosed = false;
        if ($team->team_status === 'Active') {
            $currentCount = StudentSchoolYear::where('team_id', $team->team_id)->count();
            if ($currentCount >= 5) {
                $team->update(['team_status' => 'Full']);
                $autoClosed = true;
                $this->logActivity(
                    $studentId,
                    'team_auto_closed',
                    'Team <strong>'.e($team->team_name).'</strong> is now closed (all slots filled)',
                    ['team_id' => $team->team_id]
                );
            }
        }

        if (! $autoClosed && $team->team_status !== $originalStatus) {
            $this->logActivity(
                $studentId,
                'team_status_changed',
                'Changed team status from <strong>'.e($originalStatus).'</strong> to <strong>'.e($team->team_status).'</strong>',
                ['team_id' => $team->team_id, 'old' => $originalStatus, 'new' => $team->team_status]
            );
        }

        if ($team->member_count !== 5) {
            $team->update(['member_count' => 5]);
        }

        $this->logActivity(
            $studentId,
            'team_updated',
            'Updated team <strong>'.e($team->team_name).'</strong>',
            ['team_id' => $team->team_id]
        );

        return redirect()->route('student.team')
            ->with('status', 'Team updated successfully!');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $student = $request->user();
        $studentId = $student ? $student->student_id : 8;

        $teamId = StudentTeam::idFor($studentId);

        if (! $teamId) {
            return redirect()
                ->route('student.team')
                ->with('status', 'You are not in a team.');
        }

        $team = Team::find($teamId);
        $isLeader = $team && (int) $team->leader_id === (int) $studentId;

        if ($team?->isTeamLocked()) {
            return redirect()->route('student.team')->with('error', Team::TEAM_LOCKED);
        }

        if ($isLeader) {
            $memberCount = StudentSchoolYear::where('team_id', $team->team_id)->count();

            if ($memberCount > 1) {
                return redirect()
                    ->route('student.team')
                    ->with('error', 'As the team leader, you cannot leave directly. Transfer leadership to another member first.');
            }

            // Solo leader — terminate the team entirely
            return $this->terminateSoloTeam($student, $team);
        }

        // A member leaves only with their CaPSA's approval.
        return redirect()->route('student.team')->with('error', MemberLeaveRequest::ASK_CAPSA);
    }

    private function terminateSoloTeam(Student $student, Team $team): RedirectResponse
    {
        $studentId = $student->student_id;
        $teamId = $team->team_id;
        $teamName = $team->team_name;

        StudentSchoolYear::where('team_id', $teamId)->update([
            'team_id' => null,
            's_role_id' => null,
        ]);
        $team->delete();

        // Delete the leader's recruitment post since the team no longer exists
        Recruitment::where('student_id', $studentId)->delete();

        $this->logActivity(
            $studentId,
            'team_left',
            'Left team <strong>'.e($teamName ?? 'the team').'</strong> (team terminated)',
        );

        return redirect()
            ->route('student.team')
            ->with('status', 'You have left the team and the team has been terminated.');
    }

    public function removeMember(Request $request, Student $student): RedirectResponse
    {
        $authUser = $request->user();
        $authId = $authUser->student_id;

        $myTeam = Team::find(
            StudentSchoolYear::where('student_id', $authId)->value('team_id')
        );

        if (! $myTeam) {
            return redirect()->route('student.team')
                ->with('error', 'You are not in a team.');
        }

        if ((int) $myTeam->leader_id !== (int) $authId) {
            return redirect()->route('student.team')
                ->with('error', 'Only the team leader can remove members.');
        }

        if ($myTeam->isTeamLocked()) {
            return redirect()->route('student.team')->with('error', Team::TEAM_LOCKED);
        }

        if ($student->student_id === $authId) {
            return redirect()->route('student.team')
                ->with('error', 'You cannot remove yourself. Transfer leadership instead.');
        }

        // Verify the target student is actually in this team
        $memberSy = StudentSchoolYear::where('student_id', $student->student_id)
            ->where('team_id', $myTeam->team_id)
            ->first();

        if (! $memberSy) {
            return redirect()->route('student.team')
                ->with('error', 'That student is not in your team.');
        }

        $memberSy->update(['team_id' => null, 's_role_id' => null]);

        Recruitment::where('student_id', $student->student_id)->delete();

        TeamInvitation::where(function ($q) use ($student) {
            $q->where('sender_id', $student->student_id)
                ->orWhere('receiver_id', $student->student_id);
        })->where('team_id', $myTeam->team_id)->delete();

        $this->logActivity(
            $authId,
            'member_removed',
            'Removed <strong>'.e($student->f_name.' '.$student->l_name).'</strong> from the team',
            ['removed_student_id' => $student->student_id, 'team_id' => $myTeam->team_id]
        );

        // Notify removed member
        Notification::create([
            'student_id' => $student->student_id,
            'type' => 'member_removed',
            'title' => 'Removed from Team',
            'message' => 'You have been removed from the team <strong>'.e($myTeam->team_name).'</strong>.',
            'link' => route('student.team'),
        ]);

        return redirect()->route('student.team')
            ->with('status', $student->f_name.' '.$student->l_name.' has been removed from the team.');
    }

    public function transferLeadership(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'new_leader_id' => ['required', 'integer', 'exists:students,student_id'],
        ]);

        $authUser = $request->user();
        $authId = $authUser->student_id;

        $myTeam = Team::find(
            StudentSchoolYear::where('student_id', $authId)->value('team_id')
        );

        if (! $myTeam) {
            return redirect()->route('student.team')
                ->with('error', 'You are not in a team.');
        }

        if ((int) $myTeam->leader_id !== (int) $authId) {
            return redirect()->route('student.team')
                ->with('error', 'Only the team leader can transfer leadership.');
        }

        $newLeaderId = (int) $validated['new_leader_id'];

        if ($newLeaderId === $authId) {
            return redirect()->route('student.team')
                ->with('error', 'You are already the leader.');
        }

        // Verify new leader is in the team
        $isMember = StudentSchoolYear::where('student_id', $newLeaderId)
            ->where('team_id', $myTeam->team_id)
            ->exists();

        if (! $isMember) {
            return redirect()->route('student.team')
                ->with('error', 'That student is not a member of your team.');
        }

        // Cancel any existing pending transfer requests for this team so the leader is never stuck
        $oldRequests = Notification::where('type', 'leadership_transfer_request')
            ->where('is_read', false)
            ->where('data->team_id', $myTeam->team_id)
            ->get();

        foreach ($oldRequests as $oldRequest) {
            $oldRequest->update(['is_read' => true]);
            $oldData = $oldRequest->data ?? [];
            $oldPendingLeaderId = (int) ($oldData['old_leader_id'] ?? 0);

            Notification::where('type', 'leadership_transfer_pending')
                ->where('student_id', $oldPendingLeaderId)
                ->where('is_read', false)
                ->where('data->team_id', $myTeam->team_id)
                ->update(['is_read' => true]);
        }

        $newLeader = Student::find($newLeaderId);

        Notification::create([
            'student_id' => $newLeaderId,
            'type' => 'leadership_transfer_request',
            'title' => 'Leadership Transfer Awaiting Approval',
            'message' => '<strong>'.e($authUser->f_name.' '.$authUser->l_name).'</strong> wants to transfer leadership of <strong>'.e($myTeam->team_name).'</strong> to you. Do you accept?',
            'link' => route('student.team'),
            'data' => [
                'team_id' => $myTeam->team_id,
                'team_name' => $myTeam->team_name,
                'old_leader_id' => $authId,
                'new_leader_id' => $newLeaderId,
            ],
        ]);

        Notification::create([
            'student_id' => $authId,
            'type' => 'leadership_transfer_pending',
            'title' => 'Leadership Transfer Pending Approval',
            'message' => 'You requested to transfer leadership of <strong>'.e($myTeam->team_name).'</strong> to <strong>'.e($newLeader?->f_name.' '.$newLeader?->l_name ?? 'another member').'</strong>. Your request is <strong>still pending</strong> until they approve it.',
            'link' => route('student.team'),
            'data' => [
                'team_id' => $myTeam->team_id,
                'team_name' => $myTeam->team_name,
                'old_leader_id' => $authId,
                'new_leader_id' => $newLeaderId,
            ],
        ]);

        $this->logActivity(
            $authId,
            'leadership_transfer_requested',
            'Requested to transfer leadership to <strong>'.e($newLeader?->f_name.' '.$newLeader?->l_name ?? 'another member').'</strong>',
            ['new_leader_id' => $newLeaderId, 'team_id' => $myTeam->team_id]
        );

        return redirect()->route('student.team')
            ->with('status', 'Leadership transfer request sent to '.($newLeader?->f_name ?? 'the member').'. They must approve it first.');
    }

    /**
     * Taking the handover or turning it down both settle the same pending
     * transfer, so one PATCH carries which. The methods below still do it.
     */
    public function updateLeadership(Request $request, $notificationId): RedirectResponse
    {
        return $request->validate([
            'status' => ['required', 'in:accepted,declined'],
        ])['status'] === 'accepted'
            ? $this->acceptLeadership($request, $notificationId)
            : $this->declineLeadership($request, $notificationId);
    }

    public function acceptLeadership(Request $request, $notificationId): RedirectResponse
    {
        $notification = Notification::find($notificationId);
        $authUser = $request->user();
        $authId = $authUser->student_id;

        if (! $notification || (int) $notification->student_id !== (int) $authId || $notification->type !== 'leadership_transfer_request') {
            return redirect()->route('student.team')
                ->with('error', 'Invalid leadership transfer request.');
        }

        $data = $notification->data ?? [];
        $teamId = (int) ($data['team_id'] ?? 0);
        $oldLeaderId = (int) ($data['old_leader_id'] ?? 0);
        $newLeaderId = (int) ($data['new_leader_id'] ?? 0);

        if ($newLeaderId !== $authId) {
            return redirect()->route('student.team')
                ->with('error', 'Invalid leadership transfer request.');
        }

        $myTeam = Team::find($teamId);

        if (! $myTeam) {
            $notification->update(['is_read' => true]);

            return redirect()->route('student.team')
                ->with('error', 'The team no longer exists.');
        }

        // Make sure leadership hasn't already moved
        if ((int) $myTeam->leader_id !== $oldLeaderId) {
            $notification->update(['is_read' => true]);

            return redirect()->route('student.team')
                ->with('error', 'Leadership of this team has already changed.');
        }

        // Make sure the new leader is still a member
        $isMember = StudentSchoolYear::where('student_id', $newLeaderId)
            ->where('team_id', $myTeam->team_id)
            ->exists();

        if (! $isMember) {
            $notification->update(['is_read' => true]);

            return redirect()->route('student.team')
                ->with('error', 'You are no longer a member of this team.');
        }

        // Perform the transfer
        $myTeam->update(['leader_id' => $newLeaderId]);

        Recruitment::where('student_id', $oldLeaderId)
            ->where('recruitment_goal', 'build_a_team')
            ->update(['student_id' => $newLeaderId]);
        Recruitment::where('student_id', $newLeaderId)
            ->where('recruitment_goal', '!=', 'build_a_team')
            ->delete();

        // A straight swap, and the only thing that moves a settled role.
        // Project Head seats one person, so the outgoing leader has to land
        // somewhere: they take the role the new leader just vacated. Nothing
        // is created or freed, so every seat count stays correct by
        // construction and neither of them is asked to choose again.
        $newLeaderPreviousRoleId = StudentSchoolYear::where('student_id', $newLeaderId)->value('s_role_id');

        StudentSchoolYear::where('student_id', $oldLeaderId)->update(['s_role_id' => $newLeaderPreviousRoleId]);
        StudentSchoolYear::where('student_id', $newLeaderId)->update(['s_role_id' => StudentRole::leaderRoleId()]);

        $oldLeader = Student::find($oldLeaderId);
        $newLeader = Student::find($newLeaderId);
        $this->logActivity(
            $oldLeaderId,
            'leadership_transferred',
            'Transferred leadership to <strong>'.e($newLeader?->f_name.' '.$newLeader?->l_name ?? 'another member').'</strong>',
            ['new_leader_id' => $newLeaderId, 'team_id' => $myTeam->team_id]
        );

        // Mark request as read and notify both parties
        $notification->update(['is_read' => true]);

        Notification::where('type', 'leadership_transfer_pending')
            ->where('student_id', $oldLeaderId)
            ->where('is_read', false)
            ->where('data->team_id', $myTeam->team_id)
            ->update(['is_read' => true]);

        Notification::create([
            'student_id' => $oldLeaderId,
            'type' => 'leadership_transferred',
            'title' => 'Leadership Transferred',
            'message' => '<strong>'.e($newLeader?->f_name.' '.$newLeader?->l_name ?? 'The member').'</strong> accepted your request and is now the team leader of <strong>'.e($myTeam->team_name).'</strong>.',
            'link' => route('student.team'),
        ]);

        Notification::create([
            'student_id' => $newLeaderId,
            'type' => 'leadership_transferred',
            'title' => 'Leadership Transferred',
            'message' => 'You are now the team leader of <strong>'.e($myTeam->team_name).'</strong>.',
            'link' => route('student.team'),
        ]);

        return redirect()->route('student.team')
            ->with('status', 'You are now the team leader of '.$myTeam->team_name.'!');
    }

    public function declineLeadership(Request $request, $notificationId): RedirectResponse
    {
        $notification = Notification::find($notificationId);
        $authUser = $request->user();
        $authId = $authUser->student_id;

        if (! $notification || (int) $notification->student_id !== (int) $authId || $notification->type !== 'leadership_transfer_request') {
            return redirect()->route('student.team')
                ->with('error', 'Invalid leadership transfer request.');
        }

        $data = $notification->data ?? [];
        $oldLeaderId = (int) ($data['old_leader_id'] ?? 0);
        $teamId = (int) ($data['team_id'] ?? 0);
        $team = Team::find($teamId);

        $notification->update(['is_read' => true]);

        Notification::where('type', 'leadership_transfer_pending')
            ->where('student_id', $oldLeaderId)
            ->where('is_read', false)
            ->where('data->team_id', $teamId)
            ->update(['is_read' => true]);

        $this->logActivity(
            $authId,
            'leadership_transfer_declined',
            'Declined leadership of <strong>'.e($team?->team_name ?? 'the team').'</strong>',
            ['old_leader_id' => $oldLeaderId, 'team_id' => $teamId]
        );

        Notification::create([
            'student_id' => $oldLeaderId,
            'type' => 'leadership_transfer_declined',
            'title' => 'Leadership Request Declined',
            'message' => '<strong>'.e($authUser->f_name.' '.$authUser->l_name).'</strong> declined your request to transfer leadership of <strong>'.e($team?->team_name ?? 'the team').'</strong>.',
            'link' => route('student.team'),
        ]);

        return redirect()->route('student.team')
            ->with('status', 'You declined the leadership transfer request.');
    }

    public function remindLeadershipLater(Request $request, $notificationId): JsonResponse|RedirectResponse
    {
        $notification = Notification::find($notificationId);
        $authUser = $request->user();
        $authId = $authUser->student_id;

        if (! $notification || (int) $notification->student_id !== (int) $authId || $notification->type !== 'leadership_transfer_request') {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Invalid leadership transfer request.'], 403);
            }

            return back()->with('error', 'Invalid leadership transfer request.');
        }

        $data = $notification->data ?? [];
        $data['remind_me_at'] = now()->addHours(3)->toDateTimeString();
        $notification->update(['data' => $data]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    /**
     * The classmates a leader can still invite into their team.
     *
     * Brought over from Zzek-08's branch, which is the one thing on it this
     * branch did not already have. It reads the term through school_year
     * rather than term_id, and falls back to the computed academic year when
     * a student's stored one is legacy data that matches nobody.
     */
    public function getAvailableClassmates(Request $request): JsonResponse
    {
        $student = $request->user();
        $studentId = $student ? $student->student_id : 8;

        $studentSy = StudentSchoolYear::where('student_id', $studentId)
            ->latest('school_year')
            ->first();

        if (! $studentSy) {
            return response()->json(['classmates' => []]);
        }

        $myTeam = Team::find($studentSy->team_id);

        if (! $myTeam) {
            return response()->json(['classmates' => []]);
        }

        if ((int) $myTeam->leader_id !== (int) $studentId) {
            return response()->json(['classmates' => [], 'error' => 'Only the team leader can invite members.'], 403);
        }

        $currentSchoolYear = $studentSy->school_year;

        $currentCount = StudentSchoolYear::where('team_id', $myTeam->team_id)->count();
        $maxMembers = $myTeam->memberLimit();

        if ($currentCount >= $maxMembers) {
            return response()->json(['classmates' => [], 'error' => 'Team is already full.']);
        }

        // Seats left, counting invitations already out - those hold a seat
        // until they are answered.
        $pendingCount = TeamInvitation::where('team_id', $myTeam->team_id)
            ->where('type', 'invite')
            ->where('status', 'pending')
            ->count();

        $availableSlots = max(0, $maxMembers - $currentCount - $pendingCount);

        // Anyone already sitting on an unanswered invitation from this team.
        // They stay in the list, marked, rather than being invited twice.
        $pendingInviteReceiverIds = TeamInvitation::where('team_id', $myTeam->team_id)
            ->where('type', 'invite')
            ->where('status', 'pending')
            ->pluck('receiver_id')
            ->all();

        // The fallback for a leader whose stored year is null, corrupted, or
        // simply matches nobody (legacy "26-1" data). Asked of the term
        // rather than guessed from the month, so it agrees with what the
        // rest of the app writes.
        $computedSchoolYear = Term::currentSchoolYear();

        $classmates = Student::join('student_s_y', 'students.student_id', '=', 'student_s_y.student_id')
            ->where('student_s_y.school_year', $currentSchoolYear)
            ->where('students.course', $student->course)
            ->where('students.year_section', $student->year_section)
            ->where('students.student_id', '!=', $studentId)
            ->select('students.student_id', 'students.f_name', 'students.l_name', 'students.email', 'students.p_picture', 'students.course', 'students.year_section', 'students.student_number', 'student_s_y.team_id')
            ->orderBy('students.l_name')
            ->get();

        // Fallback: if no classmates matched the stored school_year (e.g. corrupted
        // data like "26-1"), retry with the computed current academic year.
        if ($classmates->isEmpty() && $currentSchoolYear !== $computedSchoolYear) {
            $classmates = Student::join('student_s_y', 'students.student_id', '=', 'student_s_y.student_id')
                ->where('student_s_y.school_year', $computedSchoolYear)
                ->where('students.course', $student->course)
                ->where('students.year_section', $student->year_section)
                ->where('students.student_id', '!=', $studentId)
                ->select('students.student_id', 'students.f_name', 'students.l_name', 'students.email', 'students.p_picture', 'students.course', 'students.year_section', 'students.student_number', 'student_s_y.team_id')
                ->orderBy('students.l_name')
                ->get();
        }

        $classmates = $classmates->map(function ($classmate) use ($pendingInviteReceiverIds) {
            $avatar = '';
            if (filled($classmate->p_picture)) {
                if (filter_var($classmate->p_picture, FILTER_VALIDATE_URL)) {
                    $avatar = $classmate->p_picture;
                } elseif (array_key_exists($classmate->p_picture, config('avatars.presets', []))) {
                    $avatar = asset('images/avatars/'.$classmate->p_picture.'.svg');
                }
            }

            return [
                'student_id' => $classmate->student_id,
                'name' => trim(($classmate->f_name ?? '').' '.($classmate->l_name ?? '')),
                'email' => $classmate->email,
                'avatar' => $avatar,
                'course' => $classmate->course,
                'year_section' => $classmate->year_section,
                'student_number' => $classmate->student_number,
                'has_team' => $classmate->team_id !== null,
                'is_pending_invite' => in_array($classmate->student_id, $pendingInviteReceiverIds, true),
            ];
        })
            // Somebody already on a team is not somebody you can invite, so
            // they are not on the list. They used to be shown greyed out with
            // "Has team", which fills the list with names that are only there
            // to be scrolled past.
            //
            // Anyone holding an unanswered invitation from THIS team stays,
            // marked, so a leader can see they have already asked them rather
            // than wondering where they went.
            ->reject(fn ($c) => $c['has_team'])
            ->values();

        return response()->json([
            'classmates' => $classmates,
            'available_slots' => $availableSlots,
            'current_count' => $currentCount,
            'max_members' => $maxMembers,
        ]);
    }
}
