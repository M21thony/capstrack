<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelGrade extends Model
{
    protected $table = 'panel_grades';

    protected $primaryKey = 'grade_id';

    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    /**
     * The panelist declined to score this team (a conflict of interest, say),
     * with the reason in remarks. It counts as turned in, so the panel is not
     * held up, but it is left out of the team's average.
     */
    public const ABSTAINED = 'abstained';

    /**
     * How a grade typed in off the panel's paper sheets begins its remarks:
     * "Entered from paper by Rosa Villanueva (Panel Chair)." The panelist's
     * own save replaces the remarks, and with them the mark.
     */
    public const FROM_PAPER = 'Entered from paper by ';

    protected $fillable = [
        'defense_id',
        'expert_id',
        'graded_by_expert_id',
        'joined_at',
        'joined_set_by',
        'rubric_id',
        'scores',
        'total_score',
        'verdict',
        'remarks',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'total_score' => 'decimal:2',
            'submitted_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    public function isAbstained(): bool
    {
        return $this->status === self::ABSTAINED;
    }

    /** Typed in off the paper sheets by whoever records the decision, not by this panelist. */
    public function isFromPaper(): bool
    {
        return str_starts_with((string) $this->remarks, self::FROM_PAPER);
    }

    /** Another panelist grades for this one: the row is a copy of theirs. */
    public function isJoined(): bool
    {
        return $this->graded_by_expert_id !== null;
    }

    public function gradedBy()
    {
        return $this->belongsTo(CapstoneExpert::class, 'graded_by_expert_id', 'expert_id');
    }

    public function schedule()
    {
        return $this->belongsTo(DefenseSchedule::class, 'defense_id', 'defense_id');
    }

    public function expert()
    {
        return $this->belongsTo(CapstoneExpert::class, 'expert_id', 'expert_id');
    }

    public function rubric()
    {
        return $this->belongsTo(GradingRubric::class, 'rubric_id', 'rubric_id');
    }
}
