<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradingRubric;
use App\Models\RubricCriterion;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminGradingRubricController extends Controller
{
    private const TYPE_MAP = [
        'pre_proposal' => 'Pre-Proposal Defense',
        'title_defense' => 'Title Defense',
        'pre_oral_defense' => 'Pre Oral Defense',
        'final_oral_defense' => 'Final Oral Defense',
    ];

    private const TYPE_MAP_REVERSE = [
        'Pre-Proposal Defense' => 'pre_proposal',
        'Title Defense' => 'title_defense',
        'Pre Oral Defense' => 'pre_oral_defense',
        'Final Oral Defense' => 'final_oral_defense',
    ];

    public function index(): JsonResponse
    {
        $rubrics = GradingRubric::with('criteria')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($r) => $this->formatRubric($r));

        return response()->json([
            'success' => true,
            'message' => 'Rubrics retrieved successfully.',
            'data' => $rubrics,
        ]);
    }

    public function show(GradingRubric $gradingRubric): JsonResponse
    {
        $gradingRubric->load('criteria');

        return response()->json([
            'success' => true,
            'message' => 'Rubric retrieved successfully.',
            'data' => $this->formatRubric($gradingRubric),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rubricRules());

        $dbType = self::TYPE_MAP[$validated['rubric_type']] ?? $validated['rubric_type'];

        $rubric = DB::transaction(function () use ($validated, $dbType): GradingRubric {
            $rubric = GradingRubric::create([
                'rubric_name' => $validated['rubric_name'],
                'rubric_type' => $dbType,
                'description' => $validated['description'] ?? null,
                'total_points' => $validated['total_points'] ?? 100.00,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $this->storeCriteria($rubric, $validated['criteria'] ?? null);

            return $rubric->load('criteria');
        });

        return response()->json([
            'success' => true,
            'message' => 'Rubric created successfully.',
            'data' => $this->formatRubric($rubric),
        ], 201);
    }

    public function update(Request $request, GradingRubric $gradingRubric): JsonResponse
    {
        $validated = $request->validate($this->rubricRules());

        $dbType = self::TYPE_MAP[$validated['rubric_type']] ?? $validated['rubric_type'];

        $gradingRubric = DB::transaction(function () use ($gradingRubric, $validated, $dbType): GradingRubric {
            $gradingRubric->update([
                'rubric_name' => $validated['rubric_name'],
                'rubric_type' => $dbType,
                'description' => $validated['description'] ?? null,
                'total_points' => $validated['total_points'] ?? 100.00,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            $this->storeCriteria($gradingRubric, $validated['criteria'] ?? null);

            return $gradingRubric->load('criteria');
        });

        return response()->json([
            'success' => true,
            'message' => 'Rubric updated successfully.',
            'data' => $this->formatRubric($gradingRubric),
        ]);
    }

    public function destroy(GradingRubric $gradingRubric): JsonResponse
    {
        $gradingRubric->criteria()->delete();
        $gradingRubric->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rubric deleted successfully.',
        ]);
    }

    public function storeCriterion(Request $request, GradingRubric $gradingRubric): JsonResponse
    {
        $validated = $request->validate([
            'criterion_name' => $this->criterionNameRule(),
            'description' => ['nullable', 'string', 'max:5000'],
            'max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'score_levels' => $this->scoreLevelsRule(),
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $total = $this->criteriaLevelTotal($gradingRubric->criteria()->get())
            + $this->scoreLevelsTotal($validated['score_levels'] ?? null);

        if (abs($total - 100.0) > 0.0001) {
            return response()->json([
                'success' => false,
                'message' => 'Total score-level points must equal exactly 100 points.',
            ], 422);
        }

        $criterion = $gradingRubric->criteria()->create([
            'criterion_name' => trim($validated['criterion_name']),
            'description' => $validated['description'] ?? null,
            'max_points' => $validated['max_points'] ?? $this->scoreLevelsTotal($validated['score_levels'] ?? null),
            'score_levels' => $validated['score_levels'] ?? '[]',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Criterion created successfully.',
            'data' => $this->formatCriterion($criterion),
        ], 201);
    }

    public function updateCriterion(Request $request, GradingRubric $gradingRubric, RubricCriterion $rubricCriterion): JsonResponse
    {
        if ($rubricCriterion->rubric_id !== $gradingRubric->rubric_id) {
            return response()->json([
                'success' => false,
                'message' => 'Criterion does not belong to this rubric.',
            ], 404);
        }

        $validated = $request->validate([
            'criterion_name' => $this->criterionNameRule(),
            'description' => ['nullable', 'string', 'max:5000'],
            'max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'score_levels' => $this->scoreLevelsRule(),
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $total = $this->criteriaLevelTotal(
            $gradingRubric->criteria()->where('criterion_id', '!=', $rubricCriterion->criterion_id)->get()
        ) + $this->scoreLevelsTotal($validated['score_levels'] ?? null);

        if (abs($total - 100.0) > 0.0001) {
            return response()->json([
                'success' => false,
                'message' => 'Total score-level points must equal exactly 100 points.',
            ], 422);
        }

        $rubricCriterion->update([
            'criterion_name' => trim($validated['criterion_name']),
            'description' => $validated['description'] ?? null,
            'max_points' => $validated['max_points'] ?? $this->scoreLevelsTotal($validated['score_levels'] ?? null),
            'score_levels' => $validated['score_levels'] ?? '[]',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Criterion updated successfully.',
            'data' => $this->formatCriterion($rubricCriterion),
        ]);
    }

    public function destroyCriterion(GradingRubric $gradingRubric, RubricCriterion $rubricCriterion): JsonResponse
    {
        if ($rubricCriterion->rubric_id !== $gradingRubric->rubric_id) {
            return response()->json([
                'success' => false,
                'message' => 'Criterion does not belong to this rubric.',
            ], 404);
        }

        $rubricCriterion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Criterion deleted successfully.',
        ]);
    }

    public function syncCriteria(Request $request, GradingRubric $gradingRubric): JsonResponse
    {
        $validated = $request->validate([
            'criteria' => ['required', 'array'],
            'criteria.*.criterion_name' => $this->criterionNameRule(),
            'criteria.*.max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'criteria.*.description' => ['nullable', 'string', 'max:5000'],
            'criteria.*.score_levels' => $this->scoreLevelsRule(),
            'criteria.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $total = collect($validated['criteria'])
            ->sum(fn ($c) => $this->scoreLevelsTotal($c['score_levels'] ?? null));

        if (abs($total - 100.0) > 0.0001) {
            return response()->json([
                'success' => false,
                'message' => 'Total score-level points must equal exactly 100 points.',
            ], 422);
        }

        return DB::transaction(function () use ($gradingRubric, $validated): JsonResponse {
            $gradingRubric->criteria()->delete();

            foreach ($validated['criteria'] as $i => $c) {
                $gradingRubric->criteria()->create([
                    'criterion_name' => trim($c['criterion_name']),
                    'description' => $c['description'] ?? null,
                    'max_points' => $c['max_points'] ?? $this->scoreLevelsTotal($c['score_levels'] ?? null),
                    'score_levels' => $c['score_levels'] ?? '[]',
                    'sort_order' => $c['sort_order'] ?? $i,
                ]);
            }

            $gradingRubric->load('criteria');

            return response()->json([
                'success' => true,
                'message' => 'Criteria synced successfully.',
                'data' => $this->formatRubric($gradingRubric),
            ]);
        });
    }

    private function rubricRules(): array
    {
        return [
            'rubric_name' => ['required', 'string', 'max:255'],
            'rubric_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPE_MAP))],
            'description' => ['nullable', 'string', 'max:5000'],
            'total_points' => $this->totalPointsRule(),
            'is_active' => ['nullable', 'boolean'],
            'criteria' => ['nullable', 'array'],
            'criteria.*.criterion_name' => $this->criterionNameRule(),
            'criteria.*.description' => ['nullable', 'string', 'max:5000'],
            'criteria.*.max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'criteria.*.score_levels' => $this->scoreLevelsRule(),
            'criteria.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function storeCriteria(GradingRubric $rubric, mixed $criteria): void
    {
        if (! is_array($criteria) || $criteria === []) {
            return;
        }

        $total = collect($criteria)
            ->sum(fn ($c) => $this->scoreLevelsTotal($c['score_levels'] ?? null));

        if (abs($total - 100.0) > 0.0001) {
            abort(422, 'Total score-level points must equal exactly 100 points.');
        }

        $rubric->criteria()->delete();

        foreach ($criteria as $i => $c) {
            $rubric->criteria()->create([
                'criterion_name' => trim($c['criterion_name']),
                'description' => $c['description'] ?? null,
                'max_points' => $c['max_points'] ?? $this->scoreLevelsTotal($c['score_levels'] ?? null),
                'score_levels' => $c['score_levels'] ?? '[]',
                'sort_order' => $c['sort_order'] ?? $i,
            ]);
        }
    }

    private function totalPointsRule(): array
    {
        return [
            'nullable',
            'numeric',
            'min:0',
            'max:999999.99',
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== null && (float) $value !== 100.0) {
                    $fail('Total Criteria points must equal exactly 100 points.');
                }
            },
        ];
    }

    private function criterionNameRule(): array
    {
        return [
            'required',
            'string',
            'max:255',
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || trim($value) === '') {
                    $fail('Criterion Name is required for every criterion.');
                }
            },
        ];
    }

    private function scoreLevelsRule(): array
    {
        return [
            'nullable',
            'json',
            function (string $attribute, mixed $value, Closure $fail): void {
                $levels = json_decode($value, true);

                if (! is_array($levels)) {
                    return;
                }

                foreach ($levels as $level) {
                    if (! is_array($level) || ! array_key_exists('label', $level)) {
                        continue;
                    }

                    if (! is_string($level['label']) || mb_strlen($level['label']) > 255) {
                        $fail('Each score level label must be a string of at most 255 characters.');
                    }

                    if (! array_key_exists('desc', $level) || ! is_string($level['desc']) || trim($level['desc']) === '') {
                        $fail('Performance level description is required for every performance level.');
                    }
                }
            },
        ];
    }

    private function scoreLevelsTotal(mixed $scoreLevels): float
    {
        $levels = is_array($scoreLevels) ? $scoreLevels : (json_decode((string) $scoreLevels, true) ?? []);

        return (float) collect($levels)
            ->filter(fn ($level) => is_array($level) && isset($level['score']) && is_numeric($level['score']))
            ->sum(fn ($level) => (float) $level['score']);
    }

    private function criteriaLevelTotal(iterable $criteria): float
    {
        $total = 0.0;

        foreach ($criteria as $criterion) {
            $total += $this->scoreLevelsTotal($criterion->score_levels);
        }

        return $total;
    }

    private function formatRubric(GradingRubric $rubric): array
    {
        $slug = self::TYPE_MAP_REVERSE[$rubric->rubric_type] ?? $rubric->rubric_type;

        return [
            'rubric_id' => $rubric->rubric_id,
            'rubric_name' => $rubric->rubric_name,
            'rubric_type' => $slug,
            'rubric_type_label' => $rubric->rubric_type,
            'description' => $rubric->description,
            'total_points' => (float) ($rubric->total_points ?? 100),
            'is_active' => (bool) $rubric->is_active,
            'criteria' => $rubric->relationLoaded('criteria')
                ? $rubric->criteria->map(fn ($c) => $this->formatCriterion($c))->values()->toArray()
                : [],
            'criteria_count' => $rubric->relationLoaded('criteria') ? $rubric->criteria->count() : 0,
            'created_at' => $rubric->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $rubric->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function formatCriterion(RubricCriterion $criterion): array
    {
        $levelsJson = $criterion->score_levels;
        $levels = [];

        if (is_string($levelsJson)) {
            $levels = json_decode($levelsJson, true) ?? [];
        } elseif (is_array($levelsJson)) {
            $levels = $levelsJson;
        }

        foreach ($levels as &$lv) {
            if (isset($lv['score'])) {
                $lv['score'] = is_numeric($lv['score']) ? (float) $lv['score'] : $lv['score'];
            }
        }

        return [
            'criterion_id' => $criterion->criterion_id,
            'rubric_id' => $criterion->rubric_id,
            'criterion_name' => $criterion->criterion_name,
            'description' => $criterion->description,
            'max_points' => (float) $criterion->max_points,
            'score_levels' => $levels,
            'sort_order' => (int) $criterion->sort_order,
        ];
    }
}
