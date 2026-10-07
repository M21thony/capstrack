<?php

namespace App\Support;

use App\Models\ExpertEvaluation;
use Illuminate\Support\Collection;

/**
 * What team leaders' evaluations add up to, per expert - for the CaPSA who
 * wrote the questions and for the expert being evaluated.
 *
 * Anonymous by construction: nothing here reads the team or the student, so
 * no page built from it can show who answered.
 */
class EvaluationResults
{
    /**
     * Each expert's average, how many evaluations, the average per question
     * and every response, best-known first.
     *
     * @param  Collection<int, ExpertEvaluation>  $evaluations  with expert and defense loaded
     * @return Collection<int, object{expert_id: int, name: string, roles: array<int, string>, average: float, total: int, questions: Collection, responses: Collection}>
     */
    public static function byExpert(Collection $evaluations): Collection
    {
        return $evaluations
            ->groupBy('expert_id')
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return (object) [
                    'expert_id' => (int) $first->expert_id,
                    'name' => $first->expert?->name ?? 'Former expert',
                    'expert' => $first->expert,
                    'roles' => $rows->pluck('role')->unique()->sortBy(fn ($r) => array_search($r, array_keys(ExpertEvaluation::ROLES), true))->values()->all(),
                    'average' => round((float) $rows->avg('rating'), 1),
                    'total' => $rows->count(),
                    'questions' => self::byQuestion($rows),
                    'responses' => self::responses($rows),
                ];
            })
            ->sortBy([['total', 'desc'], ['name', 'asc']])
            ->values();
    }

    /**
     * The average each question earned, by its wording.
     *
     * @param  Collection<int, ExpertEvaluation>  $evaluations
     * @return Collection<int, object{question: string, average: float, total: int}>
     */
    public static function byQuestion(Collection $evaluations): Collection
    {
        return $evaluations
            ->flatMap(fn (ExpertEvaluation $e) => $e->answers ?? [])
            ->filter(fn ($a) => isset($a['question'], $a['score']))
            ->groupBy('question')
            ->map(fn (Collection $answers, string $question) => (object) [
                'question' => $question,
                'average' => round((float) $answers->avg('score'), 1),
                'total' => $answers->count(),
            ])
            ->values();
    }

    /**
     * Each response as the pages show it: the stage, the role, the rating and
     * the answers - and no one's name.
     *
     * @param  Collection<int, ExpertEvaluation>  $evaluations
     * @return Collection<int, object{stage: string, role: string, role_label: string, rating: float, date: ?string, answers: array, comment: ?string}>
     */
    public static function responses(Collection $evaluations): Collection
    {
        return $evaluations
            ->sortByDesc('created_at')
            ->map(fn (ExpertEvaluation $e) => (object) [
                'stage' => $e->defense?->defense_type ?? 'Earlier phase',
                'role' => $e->role,
                'role_label' => ExpertEvaluation::ROLES[$e->role] ?? ucfirst((string) $e->role),
                'rating' => (float) $e->rating,
                'date' => $e->created_at?->format('M j, Y'),
                'answers' => $e->answers ?? [],
                'comment' => $e->comment,
            ])
            ->values();
    }

    /** 4.25 reads "4.3"; 4.0 reads "4". */
    public static function format(float $rating): string
    {
        return rtrim(rtrim(number_format($rating, 1), '0'), '.');
    }
}
