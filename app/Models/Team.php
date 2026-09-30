<?php

namespace App\Models;

use App\Jobs\SyncConsultationCancellation;
use App\Services\GoogleCalendarService;
use App\Services\Notifier;
use App\Support\DefenseOutcome;
use App\Support\MilestoneProgress;
use App\Support\ProjectTags;
use App\Support\TeamChecklist;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Team extends Model
{
    protected $table = 'team';

    /**
     * A team that is gone - its last member left, or it was deleted - can
     * attend none of its consultations. Call them off and tell the expert,
     * or the time stays held for a team that no longer exists.
     */
    protected static function booted(): void
    {
        // Starting a team settles the founder's open invites and join requests,
        // the same as accepting one does. Every way of creating a team goes
        // through here, so none of them can leave the old ones showing.
        static::created(function (Team $team) {
            if ($team->leader_id) {
                TeamInvitation::where('status', 'pending')
                    ->where(fn ($q) => $q->where('receiver_id', $team->leader_id)->orWhere('sender_id', $team->leader_id))
                    ->update(['status' => 'declined']);
            }
        });

        static::deleting(function (Team $team) {
            foreach (ConsultationBooking::cancelForRemovedTeam($team->team_id) as $booking) {
                Notifier::toExpert(
                    $booking->schedule?->expert,
                    'consultation_cancelled',
                    'Consultation cancelled',
                    '<strong>'.e($team->team_name).'</strong> no longer exists, so its '
                        .$booking->session_date?->format('D, j M Y').' consultation is off.',
                    route('instructor.schedule'),
                    ['booking_id' => $booking->booking_id],
                );

                SyncConsultationCancellation::dispatch($booking->booking_id, ConsultationBooking::TEAM_REMOVED, (string) $team->team_name);
            }

            $upcomingPanels = DefensePanel::whereNotNull('google_event_id')
                ->whereIn('defense_id', DefenseSchedule::where('team_id', $team->team_id)->where('schedule_date', '>=', now())->select('defense_id'))
                ->get();

            foreach ($upcomingPanels as $panel) {
                try {
                    app(GoogleCalendarService::class)->cancelBookingEvent($panel->expert_id, $panel->google_event_id);
                } catch (\Exception $e) {
                    Log::warning('Defense panel Google Calendar cancel failed', ['defense_id' => $panel->defense_id, 'expert_id' => $panel->expert_id, 'error' => $e->getMessage()]);
                }
            }

            DefenseSchedule::removeForTeams([$team->team_id]);

            // The experts who took the team on hear it ended and are free of
            // it. The table is MyISAM, so its foreign key never cascades.
            foreach ($team->expertAssignments()->where('status', 'approved')->with('expert')->get() as $assignment) {
                Notifier::toExpert(
                    $assignment->expert,
                    'team_ended',
                    'Team ended',
                    '<strong>'.e($team->team_name).'</strong> no longer exists, so you are no longer its '
                        .($assignment->engaged_role_name ?: 'expert').'.',
                    route('instructor.teams'),
                    ['team_id' => $team->team_id],
                );
            }

            $team->expertAssignments()->delete();
        });
    }

    /** Whether an expert has already taken this team on. */
    public function hasApprovedExpert(): bool
    {
        return $this->expertAssignments()->where('status', 'approved')->exists();
    }

    /** This team's Title Defense attempts, fetched once per instance. */
    private ?Collection $titleDefenseAttempts = null;

    private function titleDefenseAttempts(): Collection
    {
        return $this->titleDefenseAttempts ??= DefenseSchedule::where('team_id', $this->team_id)
            ->where('defense_type', DefenseOutcome::TITLE_DEFENSE)
            ->get();
    }

    /**
     * Whether this team's members are locked: it has passed Title Defense.
     *
     * Read from the recorded outcome rather than stored as a flag. A Panel
     * Chair records an outcome once and it cannot be changed, so the pass is
     * the fact itself - there is no second copy to fall out of step with it.
     */
    public function isTeamLocked(): bool
    {
        return $this->titleDefenseAttempts()->contains(fn ($s) => DefenseOutcome::isPass($s->outcome));
    }

    /** When the members were locked - the day the pass was recorded. */
    public function teamLockedAt(): ?CarbonInterface
    {
        return $this->titleDefenseAttempts()
            ->filter(fn ($s) => DefenseOutcome::isPass($s->outcome))
            ->map(fn ($s) => $s->outcome_recorded_at ?? $s->schedule_date)
            ->filter()
            ->sort()
            ->first();
    }

    /**
     * Which Capstone this team is in.
     *
     * Passing Title Defense is what ends Capstone 1, so that decides it
     * first. A team scheduled for Title Defense that has not passed is still
     * in Capstone 1 - defending, or retaking it after missing the window -
     * whatever year its members' list now says, because their title is not
     * settled and neither are they.
     *
     * Only a team that has never been scheduled falls back to its members'
     * year: the section list is uploaded again as 4th year when it moves up.
     * Any member in 4th year settles the whole team, which closes the roster
     * rather than reopening it.
     */
    public function capstoneStage(): int
    {
        if ($this->isTeamLocked()) {
            return 2;
        }

        if ($this->titleDefenseAttempts()->isNotEmpty()) {
            return 1;
        }

        $years = $this->members->map(fn ($m) => $m->yearNumber())->filter();

        if ($years->isEmpty()) {
            return ($this->leader?->yearNumber() ?? 3) >= 4 ? 2 : 1;
        }

        return $years->max() >= 4 ? 2 : 1;
    }

    /** The experts a team must have before it can be given a defense panel. */
    public const REQUIRED_EXPERT_ROLES = ['adviser', 'consultant', 'grammarian'];

    /**
     * Which of those roles the team has not filled yet.
     *
     * Getting started on the checklist is securing all three. Until then
     * nobody has read the work, so there is nothing for a panel to defend.
     *
     * @return array<int, string>
     */
    public function missingExpertRoles(): array
    {
        $filled = TeamExpertAssignment::where('team_id', $this->team_id)
            ->where('status', 'approved')
            ->with('expertRole.role')
            ->get()
            ->map(fn (TeamExpertAssignment $a) => $a->engaged_role_name)
            ->filter()
            ->unique()
            ->all();

        return array_values(array_diff(self::REQUIRED_EXPERT_ROLES, $filled));
    }

    /**
     * Whether this team may still take members.
     *
     * Only during Capstone 1. Somebody joining in Capstone 2 was not there
     * for the title, the proposal, or any of the work being graded - which is
     * what the department's rule is about.
     */
    public function mayAddMembers(): bool
    {
        return $this->capstoneStage() === 1;
    }

    /** Said wherever a student is turned away by the CaPSA's Team Rules. */
    public const CREATION_CLOSED = 'Creating a team is closed right now - your CaPSA has turned it off.';

    public const JOINING_CLOSED = 'Joining a team is closed right now - your CaPSA has turned it off.';

    /** Members join in Capstone 1 (3rd year) only - said to a team and to a joiner alike. */
    public const MEMBERS_SETTLED = 'Capstone 2 has started, so a team keeps the members it '
        .'finished Capstone 1 with. Members cannot be added now.';

    /** Said to anyone trying to add a member to a team that passed Title Defense. */
    public const LOCKED_NO_JOINING = 'This team passed Title Defense, so its members are locked. No one can be added.';

    /** Said at every door a member could leave, or be removed, by. */
    public const TEAM_LOCKED = 'Your team passed Title Defense, so its members are locked. '
        .'A member can only leave with your CaPSA\'s approval - send the request from the Team page.';

    /** Said to a leader on their own whose team an expert already took on. */
    public const HAS_EXPERT = 'An expert has already taken on your team, so leaving needs your CaPSA\'s approval. '
        .'Use Ask CaPSA to Leave on the Team page.';

    public const NO_NEW_TEAMS = 'Capstone 2 has started. Teams keep the members they '
        .'finished Capstone 1 with, so new teams and recruitment posts are closed.';

    /**
     * Whether students may start a team, as set on the CaPSA's Team Rules.
     *
     * Open unless switched off. An unset rule is what every existing team was
     * formed under, and a fresh install must not start with teams closed.
     */
    public static function creationAllowed(): bool
    {
        return Setting::get('allow_team_creation', '1') !== '0';
    }

    /** Whether students may join a team - by request, by invitation, or by accepting one. */
    public static function joiningAllowed(): bool
    {
        return Setting::get('allow_team_joining', '1') !== '0';
    }

    /**
     * Why somebody cannot join this team at the moment they would, or null.
     *
     * Asked when an invitation is accepted, not when it was sent. One sent
     * while a seat was free can be accepted after the last seat went, after
     * Capstone 2 began, or after the CaPSA closed joining - and the accept
     * path the page uses checked none of the three, so two invitations for
     * one seat could both be accepted.
     *
     * Pending invitations are not counted here. They are the other people
     * racing for the seat, and one of them has to lose.
     */
    public function joinBlockReason(?Student $joiner = null): ?string
    {
        if (! static::joiningAllowed()) {
            return self::JOINING_CLOSED;
        }

        if ($this->isTeamLocked()) {
            return self::LOCKED_NO_JOINING;
        }

        // A 4th year joining a 3rd-year team is still a member added in Capstone 2.
        if (! $this->mayAddMembers() || $joiner?->capstoneStage() === 2) {
            return self::MEMBERS_SETTLED;
        }

        $limit = $this->memberLimit();

        if (StudentSchoolYear::where('team_id', $this->team_id)->count() >= $limit) {
            return 'This team already has the maximum of '.$limit.' members.';
        }

        return null;
    }

    /**
     * Why this team cannot take another invitation, or null if it can.
     *
     * On the model because there are two invitation controllers - a web one
     * and an api one - and the rules were written into only one of them. The
     * page posts to the other, so a team of two could be sent three
     * invitations for two seats and every one of them went through.
     *
     * @param  int  $adding  How many invitations are about to be sent at once.
     */
    public function inviteBlockReason(int $adding = 1, ?Student $invitee = null): ?string
    {
        if (! static::joiningAllowed()) {
            return self::JOINING_CLOSED;
        }

        if ($this->isTeamLocked()) {
            return self::LOCKED_NO_JOINING;
        }

        if (! $this->mayAddMembers() || $invitee?->capstoneStage() === 2) {
            return self::MEMBERS_SETTLED;
        }

        $limit = $this->memberLimit();
        $members = StudentSchoolYear::where('team_id', $this->team_id)->count();
        $pending = $this->pendingInvitationCount();
        $free = $this->seatsFree();

        if ($free >= $adding) {
            return null;
        }

        if ($free <= 0) {
            return $pending > 0
                ? 'Your team is full once the '.$pending.' invitation'.($pending === 1 ? '' : 's')
                    .' already out '.($pending === 1 ? 'is' : 'are').' answered - '.$members.' of '.$limit.' seats taken.'
                : 'Your team already has the maximum of '.$limit.' members.';
        }

        return 'Only '.$free.' seat'.($free === 1 ? '' : 's').' left, and you picked '.$adding.'.'
            .($pending > 0 ? ' '.$pending.' invitation'.($pending === 1 ? ' is' : 's are').' still waiting to be answered.' : '');
    }

    /**
     * Seats a leader may still fill.
     *
     * Invitations already out hold a seat. Three sent for one seat is the
     * same overflow arriving a day later, when the leader has forgotten and
     * the people involved have not.
     */
    public function seatsFree(): int
    {
        $taken = StudentSchoolYear::where('team_id', $this->team_id)->count()
            + $this->pendingInvitationCount();

        return max(0, $this->memberLimit() - $taken);
    }

    public function pendingInvitationCount(): int
    {
        return TeamInvitation::where('team_id', $this->team_id)
            ->where('status', 'pending')
            ->count();
    }

    /** The smallest and largest team a CaPSA may allow. */
    public const MIN_MEMBERS = 3;

    public const MAX_MEMBERS = 5;

    /**
     * How many members a team may have, as the CaPSA has set it.
     *
     * The number was written out as 5 in seven places - the invitation
     * check, two recruitment paths, the team page - so changing it meant
     * finding them all, and they had already drifted. This is the one
     * answer; a team may still carry its own member_count to override it.
     */
    public static function maxMembers(): int
    {
        $set = (int) Setting::get('max_team_members', self::MAX_MEMBERS);

        return max(self::MIN_MEMBERS, min(self::MAX_MEMBERS, $set ?: self::MAX_MEMBERS));
    }

    /**
     * This team's limit, which is the CaPSA's number.
     *
     * Not team.member_count. That column is written as a flat 5 whenever a
     * team or a recruitment post is made and never deliberately varied, so
     * reading it as a per-team override meant every team carried an
     * "override" of 5 and the CaPSA's setting changed nothing. It is kept on
     * the table because other code stores a headcount in it.
     */
    public function memberLimit(): int
    {
        return static::maxMembers();
    }

    /**
     * How far along this team is, counted from milestone items.
     *
     * Lives here because two pages ask the same question - Progress
     * Monitoring did, and Team Overview does now that the two are one page.
     * Counting from the items rather than milestones.status matters: that
     * column never moves off 'todo', so anything reading it reports every
     * team at zero however much work they have done.
     */
    /**
     * What on the checklist still stands between this team and a defense:
     * every line listed above that defense's own line that is not done. The
     * Pre-Proposal therefore waits on Getting started, and the Title Defense
     * on everything through the signed Hearing Notice - but never on what
     * comes after it, like the revised manuscript.
     *
     * A team whose checklist has no line for that defense, or no checklist at
     * all, is not held up: nobody has asked for this gate on it.
     *
     * @return array<int, string> titles, in checklist order
     */
    public function checklistBlockersFor(string $defenseType): array
    {
        $progress = $this->checklistProgress();

        if (! $progress) {
            return [];
        }

        $rows = $progress->items->values();
        $at = $rows->search(fn ($row) => $row->item->kind === Deliverable::DEFENSE
            && $row->item->defense_type === $defenseType);

        if ($at === false) {
            return [];
        }

        return $rows->take($at)
            ->reject(fn ($row) => in_array($row->state, MilestoneProgress::COMPLETE_STATES, true))
            ->map(fn ($row) => $row->item->title)
            ->values()
            ->all();
    }

    public function progressSummary(): object
    {
        $progress = $this->checklistProgress();

        return (object) [
            'total' => $progress->total ?? 0,
            'completed' => $progress->completed ?? 0,
            'overdue' => $progress->overdue ?? 0,
            'percent' => $progress->percent ?? 0,
        ];
    }

    /** This team's progress on its section CaPSA's checklist, or null when it has none. */
    public function checklistProgress(): ?object
    {
        $checklist = app(TeamChecklist::class)->for($this);

        return $checklist ? MilestoneProgress::forTeam($checklist, $this->team_id) : null;
    }

    protected $primaryKey = 'team_id';

    public $timestamps = false;

    protected $fillable = [
        'team_name',
        'project_title',
        'tags',
        'team_status',
        'leader_id',
        'is_confirmed',
        'member_count',
        'selected_pre_proposal_id',
    ];

    protected $casts = [
        'is_confirmed' => 'boolean',
        'member_count' => 'integer',
    ];

    // Some legacy rows have double-encoded JSON in `tags` (e.g. the literal
    // string "[]" instead of an actual empty array), which crashes any
    // foreach() over $team->tags. Decode defensively until we get an array.
    protected function tags(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                while (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return [];
                    }
                    $value = $decoded;
                }

                return is_array($value) ? $value : [];
            },
            set: fn ($value) => json_encode(is_array($value) ? $value : []),
        );
    }

    /**
     * Whether this team has turned their pre-proposals in for checking.
     *
     * This is the real question behind most of the old `team_status ===
     * 'complete'` checks. Reading it off team_status conflated two unrelated
     * things - whether a team is still recruiting, and whether they have
     * submitted - in one column, so a recruitment change could silently
     * affect verification and vice versa.
     *
     * Submitting is recorded on the pre-proposals themselves
     * (PreProposalController::submit), which is the only place that knows.
     */
    public function hasSubmittedPreProposals(): bool
    {
        return $this->relationLoaded('preProposals')
            ? $this->preProposals->whereNotNull('submitted_at')->isNotEmpty()
            : $this->preProposals()->whereNotNull('submitted_at')->exists();
    }

    /**
     * Query-scope form, for filtering a list of teams.
     *
     * Named differently from hasSubmittedPreProposals() on purpose: a scope
     * is reached statically (Team::submittedForChecking()), and PHP resolves
     * that to a same-named instance method - called statically, which is a
     * fatal error - instead of routing it through Eloquent's __callStatic.
     */
    public function scopeSubmittedForChecking($query)
    {
        return $query->whereHas('preProposals', fn ($q) => $q->whereNotNull('submitted_at'));
    }

    public function scopeNotSubmittedForChecking($query)
    {
        return $query->whereDoesntHave('preProposals', fn ($q) => $q->whereNotNull('submitted_at'));
    }

    /**
     * The program code (BSIT, BSCS, ...) the team is in, or null when no
     * member's course names one.
     *
     * Read off the members' courses first, since the enrolment row's program
     * column is often empty; that column is the fallback. Every member of a
     * team is in the same program, so the first one found is the team's.
     */
    public function program(): ?string
    {
        $enrolments = StudentSchoolYear::where('team_id', $this->team_id)->with('student')->get();

        foreach ($enrolments as $enrolment) {
            if ($program = Student::mapCourseToProgram((string) ($enrolment->student?->course ?? ''))) {
                return $program;
            }
        }

        foreach ($enrolments as $enrolment) {
            if ($program = Student::mapCourseToProgram((string) ($enrolment->program ?? ''))) {
                return $program;
            }
        }

        return null;
    }

    public function members()
    {
        return $this->hasManyThrough(
            Student::class,
            StudentSchoolYear::class,
            'team_id',
            'student_id',
            'team_id',
            'student_id'
        );
    }

    public function leader()
    {
        return $this->belongsTo(Student::class, 'leader_id', 'student_id');
    }

    public function expertAssignments()
    {
        return $this->hasMany(TeamExpertAssignment::class, 'team_id', 'team_id');
    }

    public function defenseSchedules()
    {
        return $this->hasMany(DefenseSchedule::class, 'team_id', 'team_id');
    }

    /** The titles in play. Ones set aside when the panel asked for a new title are left out. */
    public function preProposals()
    {
        return $this->allPreProposals()->whereNull('retired_at');
    }

    /** Every title the team has ever saved, the set-aside ones included. */
    public function allPreProposals()
    {
        return $this->hasMany(CapstonePreProposal::class, 'team_id', 'team_id')->orderBy('slot');
    }

    public function selectedPreProposal()
    {
        return $this->belongsTo(CapstonePreProposal::class, 'selected_pre_proposal_id');
    }

    /**
     * The pre-proposal the team's capstone title came from: the one chosen,
     * else the one whose title it carries. Null when there is no title, or
     * when the title was set without a pre-proposal behind it.
     */
    public function titleProposal(): ?CapstonePreProposal
    {
        if ($this->selectedPreProposal) {
            return $this->selectedPreProposal;
        }

        return $this->project_title
            ? $this->preProposals()->where('title', $this->project_title)->first()
            : null;
    }

    /**
     * The capstone title's tags, from the fixed list: its pre-proposal's
     * when there is one, else the team's own. Empty when there is no title.
     *
     * @return array<int, string>
     */
    public function titleTags(): array
    {
        if (! $this->project_title && ! $this->selectedPreProposal) {
            return [];
        }

        $tags = (array) ($this->titleProposal()?->tags ?: $this->tags);

        return array_values(array_intersect(ProjectTags::OPTIONS, array_map(fn ($t) => trim((string) $t), $tags)));
    }

    public static function nameExists(string $name, ?int $ignoreTeamId = null): bool
    {
        return static::query()
            ->when($ignoreTeamId, fn ($query) => $query->where('team_id', '!=', $ignoreTeamId))
            ->whereRaw('LOWER(team_name) = ?', [mb_strtolower(trim($name))])
            ->exists();
    }
}
