<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $table = 'team';

    /**
     * Which half of the subject the department is currently running.
     *
     * Capstone 1 is the second semester of 3rd year; Capstone 2 is the first
     * semester of 4th year. So it is not something a student's year answers -
     * a 3rd year in the first semester has not started Capstone 1 yet, and
     * the year label alone cannot tell those apart.
     *
     * The active term already carries it, declared by the Admin, and that is
     * the only thing that knows. Capstone 1 when nothing is set up, because a
     * fresh install has no terms and refusing every member would be worse
     * than allowing them.
     */
    public static function capstoneStage(): int
    {
        return (int) (Term::active()?->capstone_stage ?: 1);
    }

    /**
     * Whether teams may still take members.
     *
     * Only during Capstone 1. Somebody joining in Capstone 2 was not there
     * for the title, the proposal, or any of the work being graded - which is
     * what the department's rule is about.
     */
    public function mayAddMembers(): bool
    {
        return static::capstoneStage() === 1;
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
