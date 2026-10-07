<?php

namespace App\Models;

use Database\Factories\EvaluationTemplateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A CaPSA's questions about the experts, answered by a team leader after the
 * team passes a defense. Each question is answered from 1 to 5 - the stars -
 * and the expert's rating for that defense is the average of the answers.
 * Deleting one leaders already answered only hides it, so its answers stay
 * in the results. See App\Support\ExpertEvaluations.
 */
class EvaluationTemplate extends Model
{
    /** @use HasFactory<EvaluationTemplateFactory> */
    use HasFactory;

    use SoftDeletes;

    /** The defenses an evaluation follows, in the order a team meets them. */
    public const STAGES = ['Pre-proposal Defense', 'Title Defense', 'Pre Oral Defense', 'Final Oral Defense'];

    /** What each point on the scale means. */
    public const SCALE = [1 => 'Poor', 2 => 'Fair', 3 => 'Good', 4 => 'Very good', 5 => 'Excellent'];

    /** The questions a new evaluation starts from - five, which a CaPSA can keep, change or add to. */
    public const STANDARD_QUESTIONS = [
        'The expert was available and responded to our team within a reasonable time.',
        'The expert gave clear, specific and useful feedback on our work.',
        'The expert helped us prepare well for this defense.',
        'The expert treated every member of the team with respect and fairness.',
        'Overall, the expert contributed to the quality of our capstone project.',
    ];

    protected $fillable = ['capsa_id', 'title', 'defense_type', 'roles', 'is_active'];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(EvaluationTemplateQuestion::class)->orderBy('sort_order');
    }

    /** What team leaders answered with it. */
    public function evaluations(): HasMany
    {
        return $this->hasMany(ExpertEvaluation::class);
    }

    public function capsa(): BelongsTo
    {
        return $this->belongsTo(CapstoneExpert::class, 'capsa_id', 'expert_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function covers(string $role): bool
    {
        return in_array($role, $this->roles ?? [], true);
    }

    /** "Adviser, Consultant". */
    public function roleLabels(): string
    {
        return collect($this->roles ?? [])->map(fn ($r) => ExpertEvaluation::ROLES[$r] ?? ucfirst($r))->implode(', ');
    }
}
