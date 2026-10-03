<?php

namespace App\Models;

use App\Support\DefenseOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DefenseSchedule extends Model
{
    protected $table = 'defense_schedule';

    protected $primaryKey = 'defense_id';

    public $timestamps = false;

    protected $fillable = [
        'team_id',
        'defense_type',
        'attempt',
        'schedule_date',
        'end_date',
        'venue',
        'outcome',
        'outcome_remarks',
        'outcome_recorded_by',
        'outcome_recorded_at',
        'capa_answer',
        'capa_answered_at',
        'capa_answered_by',
    ];

    protected $casts = [
        'schedule_date' => 'datetime',
        'end_date' => 'datetime',
        'attempt' => 'integer',
        'outcome_recorded_at' => 'datetime',
        'capa_answered_at' => 'datetime',
    ];

    /** The CaPA's word that the team is ready to defend on this date. */
    public const CAPA_GO = 'go';

    /**
     * A defense takes its panel and grades with it. The tables are MyISAM,
     * so their foreign keys never cascade: removing a team's panel left the
     * grades behind, pointing at a defense that no longer existed.
     */
    protected static function booted(): void
    {
        static::deleting(function (DefenseSchedule $schedule) {
            $schedule->grades()->delete();
            $schedule->panels()->delete();
        });
    }

    /**
     * Delete these teams' defenses, with their panels and grades.
     *
     * A defense outliving its team showed on the scheduling board as a
     * nameless team that could still be given a slot and a room.
     *
     * @param  list<int>  $teamIds
     */
    public static function removeForTeams(array $teamIds): int
    {
        $defenseIds = self::whereIn('team_id', $teamIds)->pluck('defense_id');

        PanelGrade::whereIn('defense_id', $defenseIds)->delete();
        DefensePanel::whereIn('defense_id', $defenseIds)->delete();

        return self::whereIn('defense_id', $defenseIds)->delete();
    }

    /**
     * The current try at a stage.
     *
     * A team can defend the same stage more than once - a redefense is its
     * own row, with its own panel and its own grades, so the earlier attempt
     * survives. Almost everything that used to look a schedule up by
     * (team, defense_type) means "the one in play", which is the latest.
     */
    public function scopeLatestAttempt($query)
    {
        return $query->orderByDesc('attempt');
    }

    public function grades()
    {
        return $this->hasMany(PanelGrade::class, 'defense_id', 'defense_id');
    }

    public function isRedefense(): bool
    {
        return (int) $this->attempt > 1;
    }

    /** How many panelists sat on this attempt - normally three. */
    public function panelSize(): int
    {
        return $this->relationLoaded('panels') ? $this->panels->count() : $this->panels()->count();
    }

    /**
     * The grades with scores in them.
     *
     * @return Collection<int, PanelGrade>
     */
    public function submittedGrades()
    {
        return $this->gradesWithStatus([PanelGrade::SUBMITTED]);
    }

    /**
     * Panelists who declined to score this team.
     *
     * @return Collection<int, PanelGrade>
     */
    public function abstainedGrades()
    {
        return $this->gradesWithStatus([PanelGrade::ABSTAINED]);
    }

    /** How many panelists are done with this team: scored it, or abstained. */
    public function gradesInCount(): int
    {
        return $this->gradesWithStatus([PanelGrade::SUBMITTED, PanelGrade::ABSTAINED])->count();
    }

    /**
     * Whether every panelist has turned their grade in: scored it, or
     * abstained. A panel that all abstained is in too - the chair can still
     * record the decision (a redefense, say); the team's score just stays
     * blank.
     */
    public function allGradesIn(): bool
    {
        $size = $this->panelSize();

        return $size > 0 && $this->gradesInCount() === $size;
    }

    /**
     * The team's score for this defense: every scoring panelist's total added
     * up and divided by how many scored - three, normally.
     *
     * Divided by the panelists who scored, not by however many have submitted
     * so far: null until the whole panel is in, so a partial result is never
     * shown as a final one. A panelist who abstained is left out of both the
     * sum and the count, so they neither pull the average down nor stand in
     * for a score of zero.
     */
    public function panelAverage(): ?float
    {
        if (! $this->allGradesIn()) {
            return null;
        }

        $scored = $this->submittedGrades();

        // Everyone abstained: there is no score to give.
        if ($scored->isEmpty()) {
            return null;
        }

        return round($scored->sum(fn (PanelGrade $g) => (float) $g->total_score) / $scored->count(), 2);
    }

    /**
     * @param  array<int, string>  $statuses
     * @return Collection<int, PanelGrade>
     */
    private function gradesWithStatus(array $statuses)
    {
        return $this->relationLoaded('grades')
            ? $this->grades->whereIn('status', $statuses)->values()
            : $this->grades()->whereIn('status', $statuses)->get();
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function panels()
    {
        return $this->hasMany(DefensePanel::class, 'defense_id', 'defense_id');
    }

    /** The expert who recorded the panel's decision - the Panel Chair. */
    public function outcomeRecordedBy()
    {
        return $this->belongsTo(CapstoneExpert::class, 'outcome_recorded_by', 'expert_id');
    }

    public function chair()
    {
        return $this->hasOne(DefensePanel::class, 'defense_id', 'defense_id')
            ->where('panel_role', 'Panel Chair');
    }

    public function isChairedBy(int $expertId): bool
    {
        $panels = $this->relationLoaded('panels') ? $this->panels : $this->panels()->get();

        return $panels->contains(fn ($p) => $p->panel_role === 'Panel Chair' && (int) $p->expert_id === $expertId);
    }

    public function outcomeLabel(): string
    {
        return DefenseOutcome::label($this->outcome, $this->defense_type);
    }

    public function hasOutcome(): bool
    {
        return $this->outcome !== null;
    }
}
