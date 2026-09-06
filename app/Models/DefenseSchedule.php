<?php

namespace App\Models;

use App\Support\DefenseOutcome;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'schedule_date' => 'datetime',
        'end_date' => 'datetime',
        'attempt' => 'integer',
        'outcome_recorded_at' => 'datetime',
    ];

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

    /** @return \Illuminate\Support\Collection<int, PanelGrade> */
    public function submittedGrades()
    {
        return $this->relationLoaded('grades')
            ? $this->grades->where('status', 'submitted')
            : $this->grades()->where('status', 'submitted')->get();
    }

    /** Whether every panelist has turned their grade in. */
    public function allGradesIn(): bool
    {
        $size = $this->panelSize();

        return $size > 0 && $this->submittedGrades()->count() === $size;
    }

    /**
     * The team's score for this defense: every panelist's total added up and
     * divided by the size of the panel - three, normally.
     *
     * Dividing by the panel rather than by however many have submitted is the
     * point. Averaging the grades that happen to be in reports a finished
     * looking score off two of three panelists, and that number then moves
     * when the third arrives. Null until everyone has graded, so a partial
     * result is never shown as a final one.
     */
    public function panelAverage(): ?float
    {
        if (! $this->allGradesIn()) {
            return null;
        }

        $total = $this->submittedGrades()->sum(fn (PanelGrade $g) => (float) $g->total_score);

        return round($total / $this->panelSize(), 2);
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
