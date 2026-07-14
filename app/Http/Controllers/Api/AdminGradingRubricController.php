<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradingRubric;
use App\Models\RubricCriterion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $validated = $request->validate([
            'rubric_name' => ['required', 'string', 'max:255'],
            'rubric_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPE_MAP))],
            'description' => ['nullable', 'string', 'max:5000'],
            'total_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $dbType = self::TYPE_MAP[$validated['rubric_type']] ?? $validated['rubric_type'];

        $rubric = GradingRubric::create([
            'rubric_name' => $validated['rubric_name'],
            'rubric_type' => $dbType,
            'description' => $validated['description'] ?? null,
            'total_points' => $validated['total_points'] ?? 100.00,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rubric created successfully.',
            'data' => $this->formatRubric($rubric),
        ], 201);
    }

    public function update(Request $request, GradingRubric $gradingRubric): JsonResponse
    {
        $validated = $request->validate([
            'rubric_name' => ['required', 'string', 'max:255'],
            'rubric_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPE_MAP))],
            'description' => ['nullable', 'string', 'max:5000'],
            'total_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $dbType = self::TYPE_MAP[$validated['rubric_type']] ?? $validated['rubric_type'];

        $gradingRubric->update([
            'rubric_name' => $validated['rubric_name'],
            'rubric_type' => $dbType,
            'description' => $validated['description'] ?? null,
            'total_points' => $validated['total_points'] ?? 100.00,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $gradingRubric->load('criteria');

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
            'criterion_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'max_points' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'score_levels' => ['nullable', 'json'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $criterion = $gradingRubric->criteria()->create([
            'criterion_name' => $validated['criterion_name'],
            'description' => $validated['description'] ?? null,
            'max_points' => $validated['max_points'],
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
            'criterion_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'max_points' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'score_levels' => ['nullable', 'json'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $rubricCriterion->update([
            'criterion_name' => $validated['criterion_name'],
            'description' => $validated['description'] ?? null,
            'max_points' => $validated['max_points'],
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
            'criteria.*.criterion_name' => ['required', 'string', 'max:255'],
            'criteria.*.max_points' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'criteria.*.description' => ['nullable', 'string', 'max:5000'],
            'criteria.*.score_levels' => ['nullable', 'json'],
            'criteria.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $gradingRubric->criteria()->delete();

        $criteria = [];
        foreach ($validated['criteria'] as $i => $c) {
            $criteria[] = $gradingRubric->criteria()->create([
                'criterion_name' => $c['criterion_name'],
                'description' => $c['description'] ?? null,
                'max_points' => $c['max_points'],
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
