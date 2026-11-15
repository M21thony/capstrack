<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingRubric extends Model
{
    protected $table = 'grading_rubrics';

    protected $primaryKey = 'rubric_id';

    protected $fillable = [
        'rubric_name',
        'rubric_type',
        'program',
        'description',
        'total_points',
        'is_active',
        'is_archived',
    ];

    protected function casts(): array
    {
        return [
            'total_points' => 'decimal:2',
            'is_active' => 'boolean',
            'is_archived' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class, 'rubric_id', 'rubric_id');
    }

    /**
     * Rubrics panelists can grade a stage with.
     *
     * defense_schedule.defense_type and rubric_type were built by two
     * features that never agreed on exact wording ("Pre-proposal Defense" vs
     * "Pre-Proposal Defense"), so they are matched case-insensitively. An
     * archived rubric is out of use, whatever its active flag says.
     */
    public function scopeInUseFor(Builder $query, string $stage): Builder
    {
        return $query->whereRaw('LOWER(rubric_type) = ?', [mb_strtolower($stage)])
            ->where('is_active', true)
            ->where('is_archived', false);
    }

    /**
     * The rubric a team in $program is graded with at $stage.
     *
     * One written for the team's program wins; otherwise the stage's rubric
     * for every program. The newest wins a tie.
     */
    public static function forStage(string $stage, ?string $program): ?self
    {
        return self::inUseFor($stage)
            ->where(fn (Builder $q) => $q->whereNull('program')
                ->when($program, fn (Builder $q) => $q->orWhere('program', $program)))
            ->orderByRaw('program IS NULL')
            ->latest('rubric_id')
            ->first();
    }

    /**
     * The rubric's categories in sheet order, each with the most it can score.
     *
     * @return array<string, float>
     */
    public function categories(): array
    {
        return $this->criteria->sortBy('sort_order')->groupBy(fn ($c) => $c->category ?? 'General')
            ->map(fn ($items) => (float) ($items->first()->category_max_points ?? $items->sum('max_points')))
            ->all();
    }
}
