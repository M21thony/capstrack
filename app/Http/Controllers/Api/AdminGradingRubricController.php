<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GradingRubric;
use App\Models\RubricCriterion;
use App\Services\OllamaSSHClient;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminGradingRubricController extends Controller
{
    private const TYPE_MAP = [
        'pre_proposal' => 'Pre-Proposal Defense',
        'title_defense' => 'Title Defense',
        'pre_oral_defense' => 'Pre Oral Defense',
        'final_oral_defense' => 'Final Oral Defense',
    ];

    private const LEVEL_KEYS = ['excellent', 'good', 'fair', 'poor'];

    private const LEVEL_LABELS = ['Excellent', 'Good', 'Fair', 'Poor'];

    // Descending share of each criterion's point budget assigned to each
    // level, used to deterministically compute scores (see aiGenerate()) -
    // never trust an LLM to do the arithmetic that has to sum to exactly
    // 100, only its wording.
    private const LEVEL_WEIGHTS = [0.4, 0.3, 0.2, 0.1];

    private const TYPE_MAP_REVERSE = [
        'Pre-Proposal Defense' => 'pre_proposal',
        'Title Defense' => 'title_defense',
        'Pre Oral Defense' => 'pre_oral_defense',
        'Final Oral Defense' => 'final_oral_defense',
    ];

    public function __construct(private OllamaSSHClient $ollama) {}

    // POST /api/admin/grading-rubrics/ai-generate — drafts a rubric (name,
    // description, criteria with per-level narrative descriptions) via the
    // same Ollama model used by the duplication checker. Scores are NOT
    // taken from the model; they're computed deterministically afterward
    // so the draft always satisfies the "all score-levels sum to exactly
    // 100" rule the rest of this controller enforces. Returns a draft for
    // the admin to review/edit in the modal - nothing is saved here.
    public function aiGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rubric_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPE_MAP))],
            'topic' => ['nullable', 'string', 'max:255'],
            'criteria_count' => ['nullable', 'integer', 'min:3', 'max:8'],
        ]);

        $typeLabel = self::TYPE_MAP[$validated['rubric_type']];
        $criteriaCount = $validated['criteria_count'] ?? 5;
        $topic = trim($validated['topic'] ?? '');
        $fixedCategories = $this->defaultCategoriesFor($validated['rubric_type']);

        $prompt = $this->buildRubricPrompt($typeLabel, $criteriaCount, $topic, $fixedCategories);

        try {
            $raw = $this->ollama->generate($prompt, 1800);
        } catch (\Exception $e) {
            Log::error('Rubric AI generation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Could not reach the AI model. Please try again.',
            ], 502);
        }

        $parsed = $this->extractRubricJson($raw);

        if (! $parsed || empty($parsed['criteria']) || ! is_array($parsed['criteria'])) {
            Log::error('Rubric AI generation returned unparseable output', ['raw' => $raw]);

            return response()->json([
                'success' => false,
                'message' => 'The AI response could not be parsed into a rubric. Please try again.',
            ], 502);
        }

        $categories = $fixedCategories ?? $this->extractCategories($parsed['categories'] ?? null);
        $criteria = $this->scoreCriteriaWithCategories(array_slice($parsed['criteria'], 0, $criteriaCount), $categories);

        return response()->json([
            'success' => true,
            'message' => 'Draft rubric generated. Review and edit before saving.',
            'data' => [
                'rubric_name' => trim((string) ($parsed['rubric_name'] ?? '')) ?: "{$typeLabel} Rubric",
                'description' => trim((string) ($parsed['description'] ?? '')),
                'categories' => $this->summarizeCategories($criteria),
                'criteria' => $criteria,
            ],
        ]);
    }

    // The real category breakdown from the school's actual Pre-Proposal
    // Defense Rubric (Manuscript / Topic Quality and Proposal Merit /
    // Proposal Content and Clarity, weighted 20/30/50). Used verbatim for
    // that rubric type so the AI draft's structure - not just its wording -
    // matches the real thing; other types let the model propose its own
    // categories since no reference structure exists for them.
    private function defaultCategoriesFor(string $rubricTypeSlug): ?array
    {
        if ($rubricTypeSlug !== 'pre_proposal') {
            return null;
        }

        return [
            ['name' => 'Manuscript', 'weight' => 20],
            ['name' => 'Topic Quality and Proposal Merit', 'weight' => 30],
            ['name' => 'Proposal Content and Clarity', 'weight' => 50],
        ];
    }

    // The excellent/good/fair/poor wording below is lifted near-verbatim
    // from the school's actual Pre-Proposal Defense Rubric (indicator:
    // "Oral Delivery" under Manuscript > Oral Examination/Presentation) so
    // the model has a real example of the specificity and tone expected,
    // instead of drifting toward generic filler descriptions.
    private const EXAMPLE_CRITERION = <<<'JSON'
    {
      "criterion_name": "Oral Delivery",
      "description": "Assesses clarity, confidence, and command of language during the defense.",
      "levels": {
        "excellent": "Delivery is clear, confident, and articulate; excellent command of language.",
        "good": "Delivery is clear with minor lapses.",
        "fair": "Delivery is understandable but lacks confidence or clarity.",
        "poor": "Delivery is unclear and difficult to follow."
      }
    }
    JSON;

    private function buildRubricPrompt(string $typeLabel, int $criteriaCount, string $topic, ?array $fixedCategories): string
    {
        $topicLine = $topic !== '' ? "Focus area / program context: {$topic}\n" : '';
        $example = self::EXAMPLE_CRITERION;

        if ($fixedCategories) {
            $categoryNames = implode(', ', array_map(fn ($c) => '"'.$c['name'].'"', $fixedCategories));
            $categoryInstruction = <<<TXT
            Group the criteria under exactly these categories (use these exact names verbatim, do not invent new ones or add a "categories" field of your own): {$categoryNames}.
            Distribute the {$criteriaCount} criteria across these categories as evenly as makes sense for this evaluation, and set each criterion's "category" field to exactly one of these names.
            TXT;
            $categoriesShape = '';
        } else {
            $categoryInstruction = <<<TXT
            Group the criteria into 2 to 4 meaningful categories of your own choosing (broad themes this evaluation covers). Include a top-level "categories" array with each category's relative importance as a weight, and set each criterion's "category" field to match one of these names exactly.
            TXT;
            $categoriesShape = <<<'TXT'

              "categories": [
                { "name": "category name", "weight": 40 }
              ],
            TXT;
        }

        return <<<PROMPT
        You are drafting a capstone project grading rubric for a "{$typeLabel}" evaluation.
        {$topicLine}Produce exactly {$criteriaCount} distinct scoring criteria appropriate for this evaluation stage.

        {$categoryInstruction}

        Here is a real criterion from this school's actual rubric - match this level of
        specificity and academic tone for every criterion you write, not generic filler:
        {$example}

        Respond with ONLY raw JSON, no markdown code fences, no commentary, matching this exact shape:
        {
          "rubric_name": "short rubric title",
          "description": "one or two sentence description of what this rubric evaluates",{$categoriesShape}
          "criteria": [
            {
              "criterion_name": "short criterion name",
              "category": "must match one of the category names above",
              "description": "one sentence describing what this criterion assesses",
              "levels": {
                "excellent": "one sentence describing excellent performance on this criterion",
                "good": "one sentence describing good performance on this criterion",
                "fair": "one sentence describing fair performance on this criterion",
                "poor": "one sentence describing poor performance on this criterion"
              }
            }
          ]
        }

        Do not include point values or scores anywhere - only names, categories, and descriptions. Output the JSON object and nothing else.
        PROMPT;
    }

    // LLMs frequently wrap JSON in ```json fences or add a sentence before
    // it despite instructions not to - pull out the first {...} block
    // rather than assuming the whole response is clean JSON.
    private function extractRubricJson(string $raw): ?array
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }

    // Validates the AI's proposed category list ({name, weight}); returns
    // null (triggering the flat single-category fallback in
    // scoreCriteriaWithCategories) if fewer than 2 categories survive
    // validation - a single "category" isn't really grouping anything.
    private function extractCategories(mixed $categories): ?array
    {
        if (! is_array($categories) || empty($categories)) {
            return null;
        }

        $clean = [];

        foreach ($categories as $c) {
            if (! is_array($c) || ! isset($c['name']) || ! is_string($c['name']) || trim($c['name']) === '') {
                continue;
            }

            if (! isset($c['weight']) || ! is_numeric($c['weight']) || (float) $c['weight'] <= 0) {
                continue;
            }

            $clean[] = ['name' => trim($c['name']), 'weight' => (float) $c['weight']];
        }

        return count($clean) >= 2 ? $clean : null;
    }

    // Groups the raw criteria under the given categories (or a single
    // implicit category spanning everything, if $categories is null - the
    // original flat behavior), then splits 100 points across categories by
    // weight, each category's budget across its own criteria, and each
    // criterion's budget across its 4 levels. All pure integer math with
    // largest-remainder rounding, so the grand total is exactly 100 no
    // matter how the model grouped things or how many entries end up where.
    private function scoreCriteriaWithCategories(array $rawCriteria, ?array $categories): array
    {
        if (empty($rawCriteria)) {
            return [];
        }

        $categories = $categories ?: [['name' => null, 'weight' => 1]];
        $names = array_map(fn ($c) => $this->normalizeCategoryName($c['name'] ?? null), $categories);

        $buckets = array_fill(0, count($categories), []);

        foreach (array_values($rawCriteria) as $i => $c) {
            $catName = $this->normalizeCategoryName($c['category'] ?? null);
            $idx = $catName !== null ? array_search($catName, $names, true) : false;

            // Unmatched/missing category (or the single implicit category,
            // where $catName is always null) round-robins across the
            // category list instead of being dropped.
            if ($idx === false) {
                $idx = $i % count($categories);
            }

            $buckets[$idx][] = $c;
        }

        // Only categories that actually received a criterion get a point
        // budget - weights are renormalized across just those so the grand
        // total still lands on exactly 100 even if the model left one empty.
        $usedIdx = array_keys(array_filter($buckets, fn ($b) => ! empty($b)));

        if (empty($usedIdx)) {
            return [];
        }

        $usedWeights = array_map(fn ($i) => (float) ($categories[$i]['weight'] ?? 1), $usedIdx);
        $categoryBudgets = $this->allocateByWeights($usedWeights, 100);

        $result = [];
        $sortOrder = 0;

        foreach (array_values($usedIdx) as $pos => $catIdx) {
            $categoryBudget = $categoryBudgets[$pos];
            $categoryName = $categories[$catIdx]['name'] ?? null;
            $criteriaInCategory = $buckets[$catIdx];

            $perCriterionBudgets = $this->allocateByWeights(
                array_fill(0, count($criteriaInCategory), 1),
                $categoryBudget
            );

            foreach (array_values($criteriaInCategory) as $j => $c) {
                $budget = $perCriterionBudgets[$j];
                $levels = is_array($c['levels'] ?? null) ? $c['levels'] : [];

                $result[] = [
                    'criterion_name' => trim((string) ($c['criterion_name'] ?? 'Criterion '.($sortOrder + 1))),
                    'description' => trim((string) ($c['description'] ?? '')),
                    'category' => $categoryName,
                    'category_max_points' => $categoryName !== null ? $categoryBudget : null,
                    'max_points' => $budget,
                    'levels' => $this->splitBudgetAcrossLevels($budget, $levels),
                    'sort_order' => $sortOrder,
                ];
                $sortOrder++;
            }
        }

        return $result;
    }

    private function normalizeCategoryName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        $trimmed = trim($name);

        return $trimmed === '' ? null : mb_strtolower($trimmed);
    }

    // Distributes $total across entries proportional to $weights using
    // largest-remainder rounding (floor each share, then hand out the
    // leftover one point at a time to the entries with the biggest
    // fractional part) - the standard way to round percentages so they
    // still sum to exactly the target instead of drifting off by rounding.
    private function allocateByWeights(array $weights, int $total): array
    {
        $n = count($weights);

        if ($n === 0 || $total <= 0) {
            return array_fill(0, $n, 0);
        }

        $sumWeights = array_sum($weights);

        if ($sumWeights <= 0) {
            $weights = array_fill(0, $n, 1);
            $sumWeights = $n;
        }

        $raw = array_map(fn ($w) => $total * $w / $sumWeights, $weights);
        $floors = array_map(fn ($r) => (int) floor($r), $raw);
        $remainder = $total - array_sum($floors);

        $fractions = [];
        foreach ($raw as $i => $r) {
            $fractions[$i] = $r - $floors[$i];
        }
        arsort($fractions);
        $orderedKeys = array_keys($fractions);

        for ($i = 0; $i < $remainder; $i++) {
            $floors[$orderedKeys[$i % $n]]++;
        }

        return $floors;
    }

    private function summarizeCategories(array $scoredCriteria): array
    {
        $seen = [];
        $result = [];

        foreach ($scoredCriteria as $c) {
            $name = $c['category'];

            if ($name === null || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $result[] = ['name' => $name, 'max_points' => $c['category_max_points']];
        }

        return $result;
    }

    private function splitBudgetAcrossLevels(int $budget, array $levelDescriptions): array
    {
        $raw = array_map(fn ($w) => (int) floor($budget * $w), self::LEVEL_WEIGHTS);
        $leftover = $budget - array_sum($raw);

        // Hand any rounding leftover to the top levels first, one point
        // each, so "Excellent" never ends up worth less than "Good".
        for ($i = 0; $i < $leftover; $i++) {
            $raw[$i % count($raw)]++;
        }

        return array_map(function ($key, $label, $score) use ($levelDescriptions) {
            return [
                'label' => $label,
                'score' => $score,
                'desc' => trim((string) ($levelDescriptions[$key] ?? '')),
            ];
        }, self::LEVEL_KEYS, self::LEVEL_LABELS, $raw);
    }

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
            'total_points' => $this->totalPointsRule(),
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
            'total_points' => $this->totalPointsRule(),
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
            'criterion_name' => $this->criterionNameRule(),
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:150'],
            'category_max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
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
            'category' => $validated['category'] ?? null,
            'category_max_points' => $validated['category_max_points'] ?? null,
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
            'category' => ['nullable', 'string', 'max:150'],
            'category_max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
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
            'category' => $validated['category'] ?? null,
            'category_max_points' => $validated['category_max_points'] ?? null,
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
            'criteria.*.category' => ['nullable', 'string', 'max:150'],
            'criteria.*.category_max_points' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
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
                    'category' => $c['category'] ?? null,
                    'category_max_points' => $c['category_max_points'] ?? null,
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
            'category' => $criterion->category,
            'category_max_points' => $criterion->category_max_points !== null ? (float) $criterion->category_max_points : null,
            'max_points' => (float) $criterion->max_points,
            'score_levels' => $levels,
            'sort_order' => (int) $criterion->sort_order,
        ];
    }
}
