<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $table = 'team';

    /**
     * Which Capstone this team is in, read from its members' year.
     *
     * A CaPSA uploads a section's list when it starts capstone and uploads it
     * again as 4th year when it moves up, so the year on the roster turns
     * over exactly when the stage does. That makes it the signal - no
     * separate declaration to keep in step with the lists.
     *
     * Any member in 4th year settles the whole team. That is the safe way
     * round: it closes the roster rather than reopening it.
     */
    public function capstoneStage(): int
    {
        $years = $this->members->map(fn ($m) => $m->yearNumber())->filter();

        if ($years->isEmpty()) {
            return ($this->leader?->yearNumber() ?? 3) >= 4 ? 2 : 1;
        }

        return $years->max() >= 4 ? 2 : 1;
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
    public function progressSummary(): object
    {
        $total = 0;
        $completed = 0;
        $overdue = 0;

        foreach (Milestone::where('team_id', $this->team_id)->with('items')->get() as $milestone) {
            $progress = $milestone->progress();
            $total += $progress->total;
            $completed += $progress->completed;
            $overdue += $progress->overdue;
        }

        return (object) [
            'total' => $total,
            'completed' => $completed,
            'overdue' => $overdue,
            'percent' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
        ];
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

    public function preProposals()
    {
        return $this->hasMany(CapstonePreProposal::class, 'team_id', 'team_id')->orderBy('slot');
    }

    public function selectedPreProposal()
    {
        return $this->belongsTo(CapstonePreProposal::class, 'selected_pre_proposal_id');
    }

    public static function nameExists(string $name, ?int $ignoreTeamId = null): bool
    {
        return static::query()
            ->when($ignoreTeamId, fn ($query) => $query->where('team_id', '!=', $ignoreTeamId))
            ->whereRaw('LOWER(team_name) = ?', [mb_strtolower(trim($name))])
            ->exists();
    }
}
