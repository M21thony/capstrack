<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $table = 'team';

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
