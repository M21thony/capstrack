<?php

namespace App\Models;

use Database\Factories\ExpertEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A team leader's rating of one of the team's experts for one phase of the
 * Progress Tracker: 1 to 5 stars and a comment. See App\Support\ExpertEvaluations.
 */
class ExpertEvaluation extends Model
{
    /** @use HasFactory<ExpertEvaluationFactory> */
    use HasFactory;

    /** The roles a leader evaluates, in the order the page lists them. */
    public const ROLES = ['adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian'];

    protected $fillable = [
        'team_id',
        'expert_id',
        'checklist_milestone_id',
        'role',
        'rating',
        'comment',
        'student_id',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function expert(): BelongsTo
    {
        return $this->belongsTo(CapstoneExpert::class, 'expert_id', 'expert_id')->withTrashed();
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ChecklistMilestone::class, 'checklist_milestone_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }
}
