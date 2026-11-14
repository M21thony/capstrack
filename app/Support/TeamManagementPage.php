<?php

namespace App\Support;

use App\Http\Controllers\TeamRatingController;
use App\Models\DefenseSchedule;
use App\Models\MemberLeaveRequest;
use App\Models\Notification;
use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * What a student's Team Management page shows.
 *
 * The page has one of four screens, decided by where the student stands:
 * no team yet, a member of a team, or its leader - with seats still open or
 * with the team full. Everything the page shows for that screen is assembled
 * here, so the page itself only lays it out and the rules behind it are
 * asked of one place.
 */
class TeamManagementPage
{
    public const NO_TEAM = 'no_team';

    public const MEMBER = 'member';

    public const LEADER_OPEN = 'leader_open';

    public const LEADER_FULL = 'leader_full';

    /**
     * The page's data for this student, with the screen it opens on.
     *
     * @return array<string, mixed>
     */
    public static function for(Student $student, ?string $tab = null): array
    {
        $studentId = $student->student_id;

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

        // Whose Team Rules this student is under: their team's section, or their own before they have one.
        $rulesSection = $myTeam?->section() ?: $student?->sectionLabel();
        $memberCount = Team::maxMembers($rulesSection);
        $isLeader = $myTeam && (int) $myTeam->leader_id === (int) $studentId;
        $teamFull = $myTeam && ! $myTeam->is_confirmed && $isLeader && $teamMembers->count() >= $memberCount;
        $isTeamFull = $myTeam && $teamMembers->count() >= $memberCount;
        $isConfirmed = $myTeam && $myTeam->is_confirmed;
        $postHidden = $myTeam && $myTeam->team_status === 'Full';

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
        // Team selection: a leader with seats to fill starts on the students looking for a team.
        $selectionGoal = $myTeam && $isLeader && ! $isTeamFull ? 'find_a_team' : 'all';
        $feed = TeamSelectionFeed::for($student);
        $otherRecruitments = $feed->page($selectionGoal);

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

        // Leaving always waits on the CaPSA/TSA; after Title Defense so does
        // removing a member.
        $teamLocked = $myTeam?->isTeamLocked() ?? false;
        // What ran out of time is declined before it is shown as waiting.
        if ($myTeam) {
            MemberLeaveRequest::expireOverdue();
        }
        $leaveRequests = $myTeam
            ? MemberLeaveRequest::with(['student', 'requester'])
                ->pending()
                ->where('team_id', $myTeam->team_id)
                ->oldest()
                ->get()
            : collect();
        // The last week's requests that declined themselves, so the team sees
        // it was the clock and not the CaPSA/TSA - and that they can ask again.
        $autoDeclinedRequests = $myTeam
            ? MemberLeaveRequest::with(['student', 'requester'])
                ->where('team_id', $myTeam->team_id)
                ->where('auto_declined', true)
                ->where('decided_at', '>=', now()->subDays(7))
                ->latest('decided_at')
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
        $roleChoices = $needsRole ? TeamSeating::for($myTeam)->choices() : [];

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
        $stage = null;

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

        $screen = self::screenFor($myTeam, $isLeader);

        return self::forCards($student) + [
            'screen' => $screen,
            'selection' => [
                'page' => $otherRecruitments,
                'goal' => $selectionGoal,
                'counts' => $feed->counts(),
                'subtitle' => $selectionGoal === 'find_a_team'
                    ? 'Classmates looking for a team can be invited to your open slots'
                    : 'Find teammates or a capstone team within your section',
            ],
            // A member, or a leader with nobody left to find, has Team selection folded into one bar.
            'selectionFolded' => in_array($screen, [self::MEMBER, self::LEADER_FULL], true),
            // The defense the team is working towards; null once every stage is passed.
            'currentStage' => $stage,
            // A leader on their own ends the team by leaving; once an expert took it on, that is the CaPSA/TSA's call.
            'leaderAsksCapsa' => $isLeader && $teamMembers->count() === 1 && (bool) $myTeam?->hasApprovedExpert(),
            // What a leader with free seats still needs, from the seats left in each role.
            'openRoles' => $screen === self::LEADER_OPEN ? TeamSeating::for($myTeam)->openRoles() : [],
            // What the page used to ask the database for from inside its markup.
            'mySchoolYear' => $studentSy,
            'sectionLabel' => $rulesSection ?: '',
            'inviteDetails' => self::inviteDetails($invites),
            'teamSize' => $teamMembers->count(),
            'capstoneStage' => $myTeam?->capstoneStage() ?? $student->capstoneStage(),
            'teamSeatsFree' => $myTeam?->seatsFree() ?? 0,
            'teamHasApprovedExpert' => (bool) $myTeam?->hasApprovedExpert(),
            'teamHasSubmittedPreProposals' => (bool) $myTeam?->hasSubmittedPreProposals(),
            'approvedProposalCount' => $myTeam ? $myTeam->preProposals()->where('approved_status', true)->count() : 0,
            'titleCanBeReworded' => $myTeam && $myTeam->project_title && TitleRevision::canRevise($myTeam),
            'teamHasTitle' => (bool) ($myTeam?->project_title || $myTeam?->selectedPreProposal),
            'titleTags' => $myTeam?->titleTags() ?? [],
            'previousLeader' => self::previousLeader($pendingLeadershipTransfer),
            'myTeam' => $myTeam,
            'panel' => $panel,
            'panelDefenseType' => $panelDefenseType,
            // The CaPSA/TSA's Team Rules, so a closed door is said before anyone fills in a form.
            'creationAllowed' => Team::creationAllowed($rulesSection),
            'joiningAllowed' => Team::joiningAllowed($rulesSection),
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
            'autoDeclinedRequests' => $autoDeclinedRequests,
            'leaderIds' => $leaderIds,
            'userRole' => $userRole,
            'expertRemovedNotifications' => $expertRemovedNotifications,
            'pendingLeadershipTransfer' => $pendingLeadershipTransfer,
            // Financial Records and Team Rating are the page's other tabs; ?tab= opens on one.
            'tab' => in_array($tab, ['financial', 'rating'], true) ? $tab : 'team',
            'rating' => TeamRatingController::panel($student, $myTeam, $teamMembers),
            'financialState' => app(FeeBoard::class)->forStudent($student),
        ];
    }

    /**
     * What a Team selection card needs to know about the viewer: whether they
     * can ask to join a team or invite a student, and whom they already have.
     *
     * @return array{viewerTeamId: ?int, viewerCanRequest: bool, viewerCanInvite: bool, requestedTeamIds: list<int>, invitedStudentIds: list<int>, joiningAllowed: bool}
     */
    public static function forCards(Student $student): array
    {
        $teamId = StudentTeam::idFor($student->student_id);
        $team = $teamId ? Team::find($teamId) : null;
        $isLeader = $team && (int) $team->leader_id === (int) $student->student_id;
        $section = $team?->section() ?: $student->sectionLabel();
        $joiningAllowed = Team::joiningAllowed($section);

        $pending = TeamInvitation::where('sender_id', $student->student_id)->where('status', 'pending')->get(['type', 'team_id', 'receiver_id']);
        $requestedTeamIds = $pending->where('type', 'request')->pluck('team_id')->map(fn ($id) => (int) $id)->values()->all();
        $goal = Recruitment::where('student_id', $student->student_id)->latest()->value('recruitment_goal');

        return [
            'viewerTeamId' => $team?->team_id,
            'viewerIsLeader' => $isLeader,
            // Whose Team Rules the viewer is under, to name it on a closed post.
            'viewerSection' => $section,
            'viewerCanRequest' => ! $team && $joiningAllowed && $requestedTeamIds === [] && $goal !== 'build_a_team' && $student->capstoneStage() === 1,
            'viewerCanInvite' => $isLeader && $joiningAllowed && $team->inviteBlockReason() === null,
            'requestedTeamIds' => $requestedTeamIds,
            'invitedStudentIds' => $pending->where('type', 'invite')->pluck('receiver_id')->map(fn ($id) => (int) $id)->values()->all(),
            'joiningAllowed' => $joiningAllowed,
        ];
    }

    /**
     * What an invite's details show: the team's size against its limit, its
     * leader, its title and the roles its recruitment post asks for.
     *
     * @return array<int, array{members: int, limit: int, leader: string, title: ?string, roles: list<string>}>
     */
    private static function inviteDetails(Collection $invites): array
    {
        return $invites->mapWithKeys(function (TeamInvitation $invite) {
            $team = $invite->team;
            $leader = $team ? Student::find($team->leader_id) : $invite->sender;

            return [$invite->id => [
                'members' => $team ? StudentSchoolYear::where('team_id', $team->team_id)->count() : 0,
                'limit' => $team ? $team->memberLimit() : Team::maxMembers(),
                'leader' => trim(($leader->f_name ?? '').' '.($leader->l_name ?? '')) ?: 'The leader',
                'title' => $team?->project_title,
                'roles' => $team ? TeamSeating::for($team)->openRoles() : [],
            ]];
        })->all();
    }

    /** Who asked to hand this student the leadership, for the transfer pop-up. */
    private static function previousLeader(?Notification $transfer): ?Student
    {
        $id = (int) ($transfer?->data['old_leader_id'] ?? 0);

        return $id ? Student::find($id) : null;
    }

    private static function screenFor(?Team $team, bool $isLeader): string
    {
        if (! $team) {
            return self::NO_TEAM;
        }

        if (! $isLeader) {
            return self::MEMBER;
        }

        return $team->isFull() ? self::LEADER_FULL : self::LEADER_OPEN;
    }
}
