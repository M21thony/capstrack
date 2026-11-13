<?php

namespace App\Models;

use Database\Factories\ExpertEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A team leader's evaluation of one of the team's experts after a passed
 * defense: the CaPSA/TSA's questions, each answered 1-5, and the rating that is
 * their average. Anonymous to the CaPSA/TSA and the expert - student_id is kept
 * for the record and never shown. Rows from before evaluations followed
 * defenses carry a Progress Tracker phase and a comment instead.
 * See App\Support\ExpertEvaluations.
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
        'defense_id',
        'evaluation_template_id',
        'checklist_milestone_id',
        'role',
        'answers',
        'comment_approved',
        'rating',
        'comment',
        'student_id',
    ];

    protected function casts(): array
    {
        return [
            // The average of the answers, so 4.6 rather than a whole star.
            'rating' => 'float',
            // Each question as it was worded, with its 1-5 score.
            'answers' => 'array',
            // null: waiting for the CaPSA/TSA. true: the expert may read it. false: removed.
            'comment_approved' => 'boolean',
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

    public function defense(): BelongsTo
    {
        return $this->belongsTo(DefenseSchedule::class, 'defense_id', 'defense_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'evaluation_template_id')->withTrashed();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }
}
