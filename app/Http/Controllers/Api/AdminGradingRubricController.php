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

    private const TYPE_MAP_REVERSE = [
        'Pre-Proposal Defense' => 'pre_proposal',
        'Title Defense' => 'title_defense',
        'Pre Oral Defense' => 'pre_oral_defense',
        'Final Oral Defense' => 'final_oral_defense',
    ];

    // Transcribed verbatim from the school's actual Pre-Proposal Defense
    // Rubric (Manuscript / Topic Quality and Proposal Merit / Proposal
    // Content and Clarity), points included. Where the source document
    // repeats the exact same sentence across all four performance levels
    // for a row (differing only by point value - true of every row under
    // Topic Quality and Proposal Merit, and Proposal Content and Clarity),
    // that repetition is preserved rather than invented, since the goal is
    // reproducing the real document, not improving on it.
    //
    // The source scores each row by the panel picking ONE of its four
    // point values - the "excellent" value is that row's ceiling
    // (max_points), the other three are lower alternative scores for the
    // same criterion, not additional points to add on top.
    //
    // NOTE ON "Study Design Methodology": the source document's Poor value
    // for this row is (8.5pts), which is higher than its own Fair value
    // (3pts) - almost certainly a typo in the original, since "Poor"
    // scoring more than "Fair" defeats the point of the rubric. Using 1.5
    // here (continuing that row's roughly-halving pattern: 10, 5, 3, 1.5)
    // as a placeholder pending confirmation of the intended value.
    private const PRE_PROPOSAL_CRITERIA = [
        [
            'criterion_name' => 'Team Readiness and Presentability',
            'category' => 'Oral Examination/Presentation',
            'description' => 'Assesses whether the team appeared ready and presentable for the defense.',
            'levels' => [
                'excellent' => ['desc' => 'All the team members appeared to be ready and presentable.', 'score' => 5],
                'good' => ['desc' => 'All the team members appeared to be ready but not all are presentable.', 'score' => 3.75],
                'fair' => ['desc' => 'Only 50% of the team members appeared to be ready and presentable.', 'score' => 2.5],
                'poor' => ['desc' => 'Less than 50% of the team members appeared to be ready and not presentable.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Slide Usage',
            'category' => 'Oral Examination/Presentation',
            'description' => 'Assesses how intelligently and strategically the presentation slides were used.',
            'levels' => [
                'excellent' => ['desc' => 'All the presentation slides were intelligently and strategically used.', 'score' => 5],
                'good' => ['desc' => 'At least 75% of the presentation slides were intelligently and strategically used.', 'score' => 3.75],
                'fair' => ['desc' => 'Only 50% of the presentation slides were intelligently and strategically used.', 'score' => 2.5],
                'poor' => ['desc' => 'Less than 50% of the presentation slides were intelligently and strategically used.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Oral Delivery',
            'category' => 'Oral Examination/Presentation',
            'description' => 'Assesses clarity, confidence, and command of language during the defense.',
            'levels' => [
                'excellent' => ['desc' => 'Delivery is clear, confident, and articulate; excellent command of language.', 'score' => 5],
                'good' => ['desc' => 'Delivery is clear with minor lapses.', 'score' => 3.75],
                'fair' => ['desc' => 'Delivery is understandable but lacks confidence or clarity.', 'score' => 2.5],
                'poor' => ['desc' => 'Delivery is unclear and difficult to follow.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Panel Q&A Handling',
            'category' => 'Oral Examination/Presentation',
            'description' => 'Assesses how confidently and satisfactorily the team answered panel questions and queries.',
            'levels' => [
                'excellent' => ['desc' => 'All panel questions and queries were confidently and satisfactorily answered.', 'score' => 5],
                'good' => ['desc' => 'At least 75% of the panel questions and queries were confidently and satisfactorily answered.', 'score' => 3.75],
                'fair' => ['desc' => 'Only 50% of the panel questions and queries were confidently and satisfactorily answered.', 'score' => 2.5],
                'poor' => ['desc' => 'Less than 50% of the panel questions and queries were confidently and satisfactorily answered.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Project Achievability',
            'category' => 'Topic Quality and Proposal Merit',
            'description' => 'Assesses whether the project is achievable within time, skills, and resources.',
            'levels' => [
                'excellent' => ['desc' => 'Project is clearly achievable within time, skills, and resources; risks are well-considered.', 'score' => 6],
                'good' => ['desc' => 'Project is clearly achievable within time, skills, and resources; risks are well-considered.', 'score' => 4],
                'fair' => ['desc' => 'Project is clearly achievable within time, skills, and resources; risks are well-considered.', 'score' => 2.5],
                'poor' => ['desc' => 'Project is clearly achievable within time, skills, and resources; risks are well-considered.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Topic Engagement and Value',
            'category' => 'Topic Quality and Proposal Merit',
            'description' => 'Assesses the academic and practical value of the chosen topic.',
            'levels' => [
                'excellent' => ['desc' => 'Topic is highly engaging with strong academic and practical value.', 'score' => 6],
                'good' => ['desc' => 'Topic is highly engaging with strong academic and practical value.', 'score' => 4],
                'fair' => ['desc' => 'Topic is highly engaging with strong academic and practical value.', 'score' => 2.5],
                'poor' => ['desc' => 'Topic is highly engaging with strong academic and practical value.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Originality and Innovation',
            'category' => 'Topic Quality and Proposal Merit',
            'description' => 'Assesses the originality or innovative approach demonstrated by the proposal.',
            'levels' => [
                'excellent' => ['desc' => 'Demonstrates strong originality or innovative approach.', 'score' => 6],
                'good' => ['desc' => 'Demonstrates strong originality or innovative approach.', 'score' => 4],
                'fair' => ['desc' => 'Demonstrates strong originality or innovative approach.', 'score' => 2.5],
                'poor' => ['desc' => 'Demonstrates strong originality or innovative approach.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Ethical and Privacy Considerations',
            'category' => 'Topic Quality and Proposal Merit',
            'description' => 'Assesses whether ethical issues and data privacy are identified and properly addressed.',
            'levels' => [
                'excellent' => ['desc' => 'Ethical issues and data privacy are clearly identified and properly addressed.', 'score' => 6],
                'good' => ['desc' => 'Ethical issues and data privacy are clearly identified and properly addressed.', 'score' => 4],
                'fair' => ['desc' => 'Ethical issues and data privacy are clearly identified and properly addressed.', 'score' => 2.5],
                'poor' => ['desc' => 'Ethical issues and data privacy are clearly identified and properly addressed.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Alignment with Research Agenda',
            'category' => 'Topic Quality and Proposal Merit',
            'description' => 'Assesses alignment with the research agenda, SDGs, and stakeholder needs.',
            'levels' => [
                'excellent' => ['desc' => 'Highly aligned with research agenda, SDGs, and stakeholder needs.', 'score' => 6],
                'good' => ['desc' => 'Highly aligned with research agenda, SDGs, and stakeholder needs.', 'score' => 4],
                'fair' => ['desc' => 'Highly aligned with research agenda, SDGs, and stakeholder needs.', 'score' => 2.5],
                'poor' => ['desc' => 'Highly aligned with research agenda, SDGs, and stakeholder needs.', 'score' => 1.25],
            ],
        ],
        [
            'criterion_name' => 'Problem Clarity and Justification',
            'category' => 'Proposal Content and Clarity',
            'description' => 'Assesses whether the problem is clear, specific, well-justified, and evidence-based.',
            'levels' => [
                'excellent' => ['desc' => 'Problem is clear, specific, well-justified, and evidence-based.', 'score' => 20],
                'good' => ['desc' => 'Problem is clear, specific, well-justified, and evidence-based.', 'score' => 12.5],
                'fair' => ['desc' => 'Problem is clear, specific, well-justified, and evidence-based.', 'score' => 8.5],
                'poor' => ['desc' => 'Problem is clear, specific, well-justified, and evidence-based.', 'score' => 4.25],
            ],
        ],
        [
            'criterion_name' => 'Solution Scope and Definition',
            'category' => 'Proposal Content and Clarity',
            'description' => 'Assesses whether the solution addresses the problem with well-defined scope and limitations.',
            'levels' => [
                'excellent' => ['desc' => 'Solution directly addresses the problem; scope and limitations are well-defined.', 'score' => 20],
                'good' => ['desc' => 'Solution directly addresses the problem; scope and limitations are well-defined.', 'score' => 12.5],
                'fair' => ['desc' => 'Solution directly addresses the problem; scope and limitations are well-defined.', 'score' => 8.5],
                'poor' => ['desc' => 'Solution directly addresses the problem; scope and limitations are well-defined.', 'score' => 4.25],
            ],
        ],
        [
            'criterion_name' => 'Study Design Methodology',
            'category' => 'Proposal Content and Clarity',
            'description' => 'Assesses whether the study design methodology (SDM) is appropriate and clearly justified.',
            'levels' => [
                'excellent' => ['desc' => 'SDM is appropriate and clearly justified.', 'score' => 10],
                'good' => ['desc' => 'SDM is appropriate and clearly justified.', 'score' => 5],
                'fair' => ['desc' => 'SDM is appropriate and clearly justified.', 'score' => 3],
                'poor' => ['desc' => 'SDM is appropriate and clearly justified.', 'score' => 1.5],
            ],
        ],
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
            'criteria_count' => ['nullable', 'integer', 'min:3', 'max:15'],
        ]);

        $typeLabel = self::TYPE_MAP[$validated['rubric_type']];
        $isPreProposal = $validated['rubric_type'] === 'pre_proposal';
        // Pre-Proposal Defense has a real reference rubric, so there's
        // nothing to type and no count to pick - the AI is asked to
        // reproduce all 12 of its actual criteria, not invent a subset.
        $criteriaCount = $isPreProposal ? count(self::PRE_PROPOSAL_CRITERIA) : ($validated['criteria_count'] ?? 5);
        $topic = trim($validated['topic'] ?? '');
        $fixedCategories = $this->defaultCategoriesFor($validated['rubric_type']);

        $prompt = $isPreProposal
            ? $this->buildPreProposalReproductionPrompt()
            : $this->buildRubricPrompt($typeLabel, $criteriaCount, $topic, $fixedCategories);

        // The pre-proposal reproduction prompt embeds all 12 real criteria
        // and expects a similarly-sized echo back - needs more headroom
        // than the shorter free-generation prompts for the other types.
        // The free-generation budget scales with criteria_count (now up to
        // 15) so a large request doesn't get truncated mid-JSON the way a
        // fixed 1800-token cap would.
        $maxTokens = $isPreProposal ? 3000 : max(1800, $criteriaCount * 250);

        // PHP's default max_execution_time (120s on this server) doesn't
        // leave room for a cold model load (~50s alone) plus warmup() and
        // generate() as two sequential calls. 240s matches the same budget
        // given to the (longer) duplication-check chain.
        set_time_limit(240);

        try {
            // Loading the model from cold can take ~50s on its own (seen
            // directly in the Ollama server log). Warming up first spends
            // that cost on a trivial 1-token prompt instead of eating into
            // the real generate() call's own timeout budget - warmup()
            // swallows its own failures internally, so a slow/failed
            // warmup here doesn't abort the request; it just means the
            // model wasn't pre-loaded and the real call pays the cost.
            $this->ollama->warmup();

            $raw = $this->ollama->generate($prompt, $maxTokens);
        } catch (\Throwable $e) {
            // \Throwable, not \Exception - a slow/dead SSH tunnel can hit
            // PHP's max_execution_time and fatal with a Symfony FatalError
            // (extends \Error, not \Exception), which `catch (\Exception)`
            // doesn't catch - it would escape straight to Laravel's HTML
            // error page instead of a clean JSON response.
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

        if ($isPreProposal) {
            $criteria = $this->scorePreProposalCriteria(array_slice($parsed['criteria'], 0, $criteriaCount));
        } else {
            $categories = $fixedCategories ?? $this->extractCategories($parsed['categories'] ?? null);
            $criteria = $this->scoreCriteriaWithCategories(array_slice($parsed['criteria'], 0, $criteriaCount), $categories);
        }

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
            ['name' => 'Oral Examination/Presentation', 'weight' => 20],
            ['name' => 'Topic Quality and Proposal Merit', 'weight' => 30],
            ['name' => 'Proposal Content and Clarity', 'weight' => 50],
        ];
    }

    // Pulls real criteria straight from PRE_PROPOSAL_CRITERIA to use as
    // few-shot style examples for the free-generation prompt (the other 3
    // rubric types, which have no reference document of their own). Only
    // the 4 Manuscript rows have genuinely differentiated per-level wording
    // - the rest intentionally repeat one sentence across all 4 levels
    // (see PRE_PROPOSAL_CRITERIA's note), which would teach the model the
    // wrong lesson about *varying* wording per level. One example produced
    // vague hedging like "while clear, there are minor areas where the
    // presentation could be more concise" - multiple real anchors give it
    // less room to drift back into generic LLM phrasing.
    private function realStyleExamples(): array
    {
        return array_map(fn ($c) => [
            'criterion_name' => $c['criterion_name'],
            'description' => $c['description'],
            'levels' => array_map(fn ($lv) => $lv['desc'], $c['levels']),
        ], array_slice(self::PRE_PROPOSAL_CRITERIA, 0, 4));
    }

    // Rather than asking the model to invent a Pre-Proposal Defense rubric,
    // it's handed the real one (PRE_PROPOSAL_CRITERIA) as the answer and
    // asked to reproduce it verbatim in the required JSON shape - a much
    // easier, more reliable task than free generation, while still going
    // through the same AI call as every other rubric type. Point values are
    // deliberately left out of what's sent to the model (see
    // scorePreProposalCriteria()) - wording is the model's job, the real
    // numbers are looked up afterward from PRE_PROPOSAL_CRITERIA directly,
    // never trusted to a model echo.
    private function buildPreProposalReproductionPrompt(): string
    {
        $wordingOnly = array_map(fn ($c) => [
            'criterion_name' => $c['criterion_name'],
            'category' => $c['category'],
            'description' => $c['description'],
            'levels' => array_map(fn ($lv) => $lv['desc'], $c['levels']),
        ], self::PRE_PROPOSAL_CRITERIA);

        $reference = json_encode([
            'rubric_name' => 'Pre-Proposal Defense Rubric',
            'description' => "Evaluates a capstone team's pre-proposal defense across manuscript delivery, topic quality and proposal merit, and proposal content and clarity.",
            'criteria' => $wordingOnly,
        ], JSON_PRETTY_PRINT);

        return <<<PROMPT
        Reproduce the following capstone Pre-Proposal Defense Rubric exactly as given
        below - do not change any wording, criterion names, or category names, and do
        not add, remove, or reorder criteria. Some criteria intentionally repeat the
        same sentence across all four performance levels; keep that repetition exactly
        as given, do not invent varied wording for those.

        {$reference}

        Respond with ONLY this JSON object, no markdown code fences, no commentary.
        PROMPT;
    }

    // The AI's echo is only trusted for wording. Point values always come
    // straight from PRE_PROPOSAL_CRITERIA (matched by criterion name, or by
    // position if a name doesn't match - the model was asked for all 12 in
    // order), so the draft's numbers are guaranteed to be the school's real
    // ones regardless of how faithfully the model reproduced them.
    private function scorePreProposalCriteria(array $rawCriteria): array
    {
        $reference = self::PRE_PROPOSAL_CRITERIA;
        $byName = [];

        foreach ($reference as $ref) {
            $byName[$this->normalizeCategoryName($ref['criterion_name'])] = $ref;
        }

        $result = [];

        foreach (array_values($rawCriteria) as $i => $c) {
            $name = $this->normalizeCategoryName($c['criterion_name'] ?? null);
            $ref = ($name !== null ? $byName[$name] ?? null : null) ?? $reference[$i] ?? null;

            if ($ref === null) {
                continue;
            }

            $levels = array_map(fn ($key, $label) => [
                'label' => $label,
                'score' => $ref['levels'][$key]['score'],
                'desc' => $ref['levels'][$key]['desc'],
            ], self::LEVEL_KEYS, self::LEVEL_LABELS);

            $result[] = [
                'criterion_name' => $ref['criterion_name'],
                'description' => $ref['description'],
                'category' => $ref['category'],
                'category_max_points' => $this->categoryMaxPointsFor($ref['category']),
                'max_points' => $ref['levels']['excellent']['score'],
                'levels' => $levels,
                'sort_order' => $i,
            ];
        }

        return $result;
    }

    private function categoryMaxPointsFor(string $categoryName): float
    {
        foreach ($this->defaultCategoriesFor('pre_proposal') as $cat) {
            if ($cat['name'] === $categoryName) {
                return (float) $cat['weight'];
            }
        }

        return 0.0;
    }

    private function buildRubricPrompt(string $typeLabel, int $criteriaCount, string $topic, ?array $fixedCategories): string
    {
        $topicLine = $topic !== '' ? "Focus area / program context: {$topic}\n" : '';
        $examples = json_encode($this->realStyleExamples(), JSON_PRETTY_PRINT);

        if ($fixedCategories) {
            $categoryNames = implode(', ', array_map(fn ($c) => '"'.$c['name'].'"', $fixedCategories));
            $categoryInstruction = <<<TXT
            Group the criteria under exactly these categories (use these exact names verbatim, do not invent new ones or add a "categories" field of your own): {$categoryNames}.
            Distribute the {$criteriaCount} criteria across these categories as evenly as makes sense for this evaluation, and set each criterion's "category" field to exactly one of these names.
            TXT;
            $categoriesShape = '';
        } else {
            // A 3-criteria request paired with "2 to 4 categories" was
            // confusing the model into just giving up and using 1 category
            // for everything (confirmed by reproducing it directly against
            // the real model). Scaling the requested range down to something
            // achievable for the actual criteria count is still the right
            // instruction to give - but re-testing showed the model can
            // still ignore it and return 1 category anyway at low counts.
            // extractCategories() no longer discards that single category to
            // null when it happens, so the AI's real grouping (even if it's
            // just one) is preserved and shown instead of hidden.
            $minCategories = min(2, $criteriaCount);
            $maxCategories = max($minCategories, min(4, $criteriaCount - 1));
            $categoryInstruction = <<<TXT
            Group the criteria into {$minCategories} to {$maxCategories} meaningful categories of your own choosing (broad themes this evaluation covers). You MUST use more than one category - even with only {$criteriaCount} criteria, do not put every criterion under a single category. Include a top-level "categories" array with each category's relative importance as a weight, and set each criterion's "category" field to match one of these names exactly.
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

        Here are real criteria from this school's Pre-Proposal Defense rubric - a DIFFERENT
        evaluation stage from the "{$typeLabel}" you are drafting now. They are a STYLE reference
        only (match this level of specificity and academic tone), NOT content to reuse:
        {$examples}

        Do NOT copy these example criteria verbatim or near-verbatim (e.g. do not just reuse
        "Slide Usage", "Oral Delivery", "Team Readiness and Presentability", or "Panel Q&A Handling"
        as your own criteria names). Every criterion you write must be an ORIGINAL criterion
        genuinely specific to what a "{$typeLabel}" evaluation assesses.

        Do NOT write vague, hedging, AI-sounding filler. Specifically avoid patterns like:
        - "While [X], there are [some/minor] areas where [Y] could be more [Z]"
        - "could be more concise/specific/clear"
        - "in general", "overall", "for the most part", "somewhat", "relatively"
        - describing what's merely "acceptable" or "adequate" without saying what was actually observed
        Instead, describe an observable, concrete outcome the way the real examples above do -
        state what was or wasn't done/shown/covered, not a soft opinion about how it could be improved.

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
    // null (triggering the flat, unnamed single-category fallback in
    // scoreCriteriaWithCategories) only if NO valid category survives.
    // Previously required >= 2, silently discarding the AI's real category
    // name whenever it settled on just one (confirmed happening routinely
    // with low criteria counts against the real model, regardless of prompt
    // wording asking for more) - that turned "1 real category" into "0
    // categories shown at all", which is strictly worse.
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

        return count($clean) >= 1 ? $clean : null;
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
                    'levels' => $this->levelsForBudget($budget, $levels),
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

    // "Excellent" is the criterion's full budget (its max_points ceiling) -
    // a panel picks exactly ONE of these four values as the criterion's
    // score, so Good/Fair/Poor are lower ALTERNATIVE scores for the same
    // criterion, not additional points stacked on top of it. Ratios match
    // the pattern in the school's real rubric (Manuscript section: exactly
    // 100/75/50/25% of each row's ceiling).
    private function levelsForBudget(int $budget, array $levelDescriptions): array
    {
        $ratios = [1.0, 0.75, 0.5, 0.25];

        return array_map(function ($key, $label, $ratio) use ($levelDescriptions, $budget) {
            return [
                'label' => $label,
                'score' => round($budget * $ratio, 2),
                'desc' => trim((string) ($levelDescriptions[$key] ?? '')),
            ];
        }, self::LEVEL_KEYS, self::LEVEL_LABELS, $ratios);
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

        // max_points always comes from the levels themselves (never trusted
        // as an independent client-supplied value) - it's the ceiling of
        // whichever level scores highest, normally "Excellent".
        $newMaxPoints = $this->scoreLevelsMax($validated['score_levels'] ?? null);
        $total = $this->criteriaMaxPointsTotal($gradingRubric->criteria()->get()) + $newMaxPoints;

        if (abs($total - 100.0) > 0.0001) {
            return response()->json([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
            ], 422);
        }

        $criterion = $gradingRubric->criteria()->create([
            'criterion_name' => trim($validated['criterion_name']),
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'category_max_points' => $validated['category_max_points'] ?? null,
            'max_points' => $newMaxPoints,
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

        $newMaxPoints = $this->scoreLevelsMax($validated['score_levels'] ?? null);
        $total = $this->criteriaMaxPointsTotal(
            $gradingRubric->criteria()->where('criterion_id', '!=', $rubricCriterion->criterion_id)->get()
        ) + $newMaxPoints;

        if (abs($total - 100.0) > 0.0001) {
            return response()->json([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
            ], 422);
        }

        $rubricCriterion->update([
            'criterion_name' => trim($validated['criterion_name']),
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'category_max_points' => $validated['category_max_points'] ?? null,
            'max_points' => $newMaxPoints,
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
            ->sum(fn ($c) => $this->scoreLevelsMax($c['score_levels'] ?? null));

        if (abs($total - 100.0) > 0.0001) {
            return response()->json([
                'success' => false,
                'message' => 'Total criterion points must equal exactly 100 points.',
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
                    'max_points' => $this->scoreLevelsMax($c['score_levels'] ?? null),
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

    // A criterion is scored by picking exactly ONE of its performance
    // levels - its max_points ceiling is the highest of those levels
    // (normally "Excellent"), not their sum. Used to auto-derive max_points
    // when a request doesn't supply it explicitly.
    private function scoreLevelsMax(mixed $scoreLevels): float
    {
        $levels = is_array($scoreLevels) ? $scoreLevels : (json_decode((string) $scoreLevels, true) ?? []);

        $scores = collect($levels)
            ->filter(fn ($level) => is_array($level) && isset($level['score']) && is_numeric($level['score']))
            ->map(fn ($level) => (float) $level['score']);

        return $scores->isEmpty() ? 0.0 : (float) $scores->max();
    }

    // The rubric-wide "must total exactly 100" rule sums each criterion's
    // own max_points ceiling - not every level of every criterion, since a
    // panel only ever picks one level per criterion.
    private function criteriaMaxPointsTotal(iterable $criteria): float
    {
        $total = 0.0;

        foreach ($criteria as $criterion) {
            $total += (float) $criterion->max_points;
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
