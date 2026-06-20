<?php

namespace App\Services;

use App\Models\ArchivedDocument;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class SimilarityService
{
    public function __construct(private OllamaSSHClient $ollama) {}

    // ── 1. Extract raw text from uploaded PDF ─────────────────────────
    public function extractPdfText(string $filePath): string
    {
        $parser = new Parser();
        $pdf    = $parser->parseFile($filePath);
        return preg_replace('/\s+/', ' ', trim($pdf->getText()));
    }

    // ── 2. Extract key concepts from PDF text ─────────────────────────
    public function extractKeyConcepts(string $pdfText): array
    {
        $truncated = mb_substr($pdfText, 0, 6000);

        $prompt = <<<PROMPT
You are an academic research analyzer. Read this capstone pre-proposal and extract key information.
Return ONLY a valid JSON object. No explanation. No markdown fences.

{
  "main_topic": "one sentence describing the core subject",
  "domain": "e.g. Web System, Mobile App, IoT, AI/ML, Desktop App, Management System",
  "keywords": ["up to 10 keywords"],
  "problem_statement": "2 sentences max about the problem being solved",
  "proposed_solution": "2 sentences max describing what the system will do",
  "technologies": ["list of technologies mentioned"],
  "target_users": "who will use this system"
}

Pre-proposal text:
{$truncated}
PROMPT;

        $raw     = $this->ollama->generate($prompt);
        $cleaned = trim(preg_replace('/```json|```/i', '', $raw));
        $data    = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('extractKeyConcepts parse error', ['raw' => $raw]);
            throw new \Exception('AI returned invalid JSON during concept extraction.');
        }

        return $data;
    }

    // ── 3. Batch score ALL archived docs, return top 10 ───────────────
    public function findSimilarDocuments(array $concepts, int $topN = 10): array
    {
        // ── Phase 1: Semantic keyword expansion via Ollama ────────────────
        $conceptText = implode(', ', $concepts['keywords'] ?? []);

        $expandPrompt = "Given this capstone pre-proposal topic, list 15 related search terms "
                    . "that could appear in similar capstone titles. Include synonyms, related domains, "
                    . "and common variations. Return ONLY a JSON array of strings. No markdown.\n\n"
                    . "Topic: {$concepts['main_topic']}\n"
                    . "Domain: {$concepts['domain']}\n"
                    . "Keywords: {$conceptText}\n\n"
                    . '["term1","term2",...]';

        try {
            $raw          = $this->ollama->generate($expandPrompt);
            $cleaned      = trim(preg_replace('/```json|```/i', '', $raw));
            preg_match('/\[.*\]/s', $cleaned, $m);
            $expandedTerms = json_decode($m[0] ?? $cleaned, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($expandedTerms)) {
                $expandedTerms = $concepts['keywords'] ?? [];
            }
        } catch (\Exception $e) {
            $expandedTerms = $concepts['keywords'] ?? [];
        }

        // Merge original keywords with expanded terms
        $allTerms = array_unique(array_merge(
            $concepts['keywords'] ?? [],
            $expandedTerms
        ));

        // ── Phase 2: SQL filter using expanded terms ──────────────────────
        $query = ArchivedDocument::query();
        $query->where(function ($q) use ($allTerms, $concepts) {
            foreach ($allTerms as $term) {
                if (strlen(trim($term)) < 3) continue;
                $q->orWhere('title',    'LIKE', '%' . $term . '%');
                $q->orWhere('abstract', 'LIKE', '%' . $term . '%');
            }
            // Always include domain words
            foreach (explode(' ', $concepts['domain']) as $word) {
                if (strlen($word) > 3) {
                    $q->orWhere('title', 'LIKE', '%' . $word . '%');
                }
            }
        });

        $filtered = $query->limit(80)->get();

        // Pad with recent records if too few results
        if ($filtered->count() < 20) {
            $filteredIds = $filtered->pluck('archived_id')->toArray();
            $extras      = ArchivedDocument::whereNotIn('archived_id', $filteredIds)
                            ->orderBy('academic_year', 'desc')
                            ->limit(20 - $filtered->count())
                            ->get();
            $filtered    = $filtered->merge($extras);
        }

        // ── Phase 3: Semantic scoring via Ollama in chunks of 20 ─────────
        $allScores = [];
        $chunks    = $filtered->chunk(10);

        foreach ($chunks as $chunkIndex => $chunk) {
            $docList = $chunk->map(fn($doc) => [
                'id'    => $doc->archived_id,
                'title' => mb_substr($doc->title, 0, 100),
                'abs'   => mb_substr($doc->abstract ?? '', 0, 150),
            ])->values()->toArray();

            $docsJson = json_encode($docList);

            $prompt = "Perform semantic similarity analysis between this capstone pre-proposal "
                    . "and the archived capstones below.\n"
                    . "Consider meaning, intent, domain, technology, and problem being solved — not just keywords.\n"
                    . "Score each 0-100. Return ONLY a JSON array. No markdown.\n"
                    . '[{"id":<id>,"score":<0-100>,"reason":"one sentence semantic explanation"}]'
                    . "\n\nPRE-PROPOSAL:"
                    . "\nTopic: {$concepts['main_topic']}"
                    . "\nDomain: {$concepts['domain']}"
                    . "\nKeywords: {$conceptText}"
                    . "\nProblem: {$concepts['problem_statement']}"
                    . "\nSolution: {$concepts['proposed_solution']}"
                    . "\n\nARCHIVED CAPSTONES:\n{$docsJson}";

            try {
                $raw     = $this->ollama->generate($prompt);
                $cleaned = trim(preg_replace('/```json|```/i', '', $raw));

                preg_match('/\[.*\]/s', $cleaned, $matches);
                $jsonStr = $matches[0] ?? $cleaned;
                $scores  = json_decode($jsonStr, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($scores)) {
                    $allScores = array_merge($allScores, $scores);
                } else {
                    Log::warning("Chunk {$chunkIndex} parse failed", ['raw' => substr($raw, 0, 200)]);
                }
            } catch (\Exception $e) {
                Log::warning("Chunk {$chunkIndex} failed", ['error' => $e->getMessage()]);
                continue;
            }
        }

        if (empty($allScores)) {
            throw new \Exception('Semantic analysis returned no scores.');
        }

        // Sort descending and take top N
        usort($allScores, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        $topScores = array_slice($allScores, 0, $topN);

        // Map back to document models
        $docMap  = $filtered->keyBy('archived_id');
        $results = [];

        foreach ($topScores as $item) {
            $doc = $docMap->get($item['id']);
            if (!$doc) continue;

            $results[] = [
                'document'   => $doc,
                'score'      => (int) ($item['score'] ?? 0),
                'reason'     => $item['reason']     ?? '',
                'matched_on' => $item['matched_on'] ?? [],
            ];
        }

        return $results;
    }

    // ── 4. Suggest instructors based on concepts ──────────────────────
    public function suggestInstructors(array $concepts): array
    {
        $experts = \DB::table('capstone_experts as e')
            ->leftJoin('expert_expertise as ex', 'e.expert_id', '=', 'ex.expert_id')
            ->where('e.approved_status', 1)
            ->select(
                'e.expert_id',
                'e.first_name',
                'e.last_name',
                'e.credentials',
                'e.department',
                \DB::raw('GROUP_CONCAT(ex.expertise_field SEPARATOR ", ") as expertise')
            )
            ->groupBy(
                'e.expert_id',
                'e.first_name',
                'e.last_name',
                'e.credentials',
                'e.department'
            )
            ->get();

        if ($experts->isEmpty()) {
            return [];
        }

        $validIds  = $experts->pluck('expert_id')->toArray();
        $expertMap = $experts->keyBy('expert_id');

        $domain      = $concepts['domain'];
        $keywords    = implode(', ', $concepts['keywords'] ?? []);
        $listJson    = json_encode($experts->map(fn($e) => [
            'id'        => $e->expert_id,
            'name'      => $e->first_name . ' ' . $e->last_name,
            'expertise' => $e->expertise ?? 'General',
        ])->values()->toArray());

        $prompt = "You are matching a capstone pre-proposal to instructors using semantic analysis.\n"
                . "Analyze the meaning and relevance of each instructor's expertise against the proposal.\n"
                . "IMPORTANT: You MUST only return instructors from the list below using their exact IDs.\n"
                . "Do NOT invent new instructors. If only 1 instructor exists, return only that 1.\n"
                . "Return ONLY a valid JSON array. No markdown. No explanation.\n\n"
                . '[{"id":<exact id from list>,"name":"exact name","reason":"semantic reason why expertise matches"}]'
                . "\n\nPRE-PROPOSAL:"
                . "\nDomain: {$domain}"
                . "\nKeywords: {$keywords}"
                . "\nTopic: {$concepts['main_topic']}"
                . "\nProblem: {$concepts['problem_statement']}"
                . "\n\nINSTRUCTOR LIST (ONLY use these exact IDs):\n{$listJson}";

        try {
            $raw     = $this->ollama->generate($prompt);
            $cleaned = trim(preg_replace('/```json|```/i', '', $raw));

            preg_match('/\[.*\]/s', $cleaned, $matches);
            $jsonStr  = $matches[0] ?? $cleaned;
            $suggests = json_decode($jsonStr, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($suggests)) {
                Log::error('suggestInstructors parse error', ['raw' => $raw]);
                $suggests = [];
            }

            // Strict validation — only keep real IDs
            $results = [];
            foreach ($suggests as $s) {
                $id = (int) ($s['id'] ?? 0);
                if (!in_array($id, $validIds)) continue;

                $expert    = $expertMap->get($id);
                $results[] = [
                    'id'        => $id,
                    'name'      => $expert->first_name . ' ' . $expert->last_name,
                    'expertise' => $expert->expertise ?? 'General',
                    'reason'    => $s['reason'] ?? '',
                ];
            }

            // If AI hallucinated all IDs, fall back to all real experts
            if (empty($results)) {
                foreach ($experts as $e) {
                    $results[] = [
                        'id'        => $e->expert_id,
                        'name'      => $e->first_name . ' ' . $e->last_name,
                        'expertise' => $e->expertise ?? 'General',
                        'reason'    => 'Semantically relevant based on expertise in ' . ($e->expertise ?? 'computing') . '.',
                    ];
                }
            }

            return array_slice($results, 0, 3);

        } catch (\Exception $e) {
            Log::error('suggestInstructors error', ['error' => $e->getMessage()]);
            return [];
        }
    }
}