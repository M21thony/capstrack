<?php

namespace App\Services;

use App\Models\ArchivedDocument;
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

    // ── Stop words filter ─────────────────────────────────────────────
    private function removeStopWords(string $text): string
    {
        $stopWords = [
            // Standard
            'a', 'an', 'the', 'of', 'for', 'and', 'or', 'in', 'to', 'is', 'are',
            'with', 'that', 'this', 'on', 'at', 'by', 'from', 'it', 'its', 'be',
            'as', 'was', 'were', 'has', 'have', 'had', 'do', 'does', 'did', 'not',

            // Generic capstone words (appear in almost every title)
            'proposed', 'computerized', 'system', 'management', 'records',
            'using', 'based', 'web', 'online', 'automated', 'digital', 'application',
            'information', 'technology', 'development', 'design', 'implementation',

            // Location noise
            'camarines', 'sur', 'nabua', 'cspc', 'iriga', 'city', 'colleges',
            'polytechnic', 'philippines',

            // School/dept noise
            'college', 'department', 'university', 'school', 'cict', 'bsit', 'bscs',
            'institute', 'campus',
        ];

        $words    = explode(' ', strtolower($text));
        $filtered = array_filter($words, fn($w) => !in_array(trim($w), $stopWords) && strlen(trim($w)) > 2);

        return implode(' ', $filtered);
    }

    // ── 3. TRUE RAG: embed query → cosine similarity → top N ──────────
    public function findSimilarDocuments(array $concepts, int $topN = 10): array
    {
        // Build a rich text representation — strip stop words before embedding
        $rawQueryText = implode("\n", array_filter([
            $concepts['main_topic']        ?? '',
            $concepts['domain']            ?? '',
            implode(', ', $concepts['keywords'] ?? []),
            $concepts['problem_statement'] ?? '',
            $concepts['proposed_solution'] ?? '',
        ]));

        // FIX: Apply stop words to query text for cleaner embedding
        $queryText = $this->removeStopWords($rawQueryText);

        // ── Phase 1: Generate embedding for the uploaded document ─────
        try {
            $queryEmbedding = $this->ollama->embed($queryText);
        } catch (\Exception $e) {
            Log::warning('RAG embed failed, falling back to keyword search', ['error' => $e->getMessage()]);
            return $this->findSimilarDocumentsLegacy($concepts, $topN);
        }

        // ── Phase 2: Load all archived docs that have embeddings ──────
        $docs = ArchivedDocument::whereNotNull('embedding')->get();

        if ($docs->count() < 5) {
            Log::info('Too few embedded docs, using legacy search', ['count' => $docs->count()]);
            return $this->findSimilarDocumentsLegacy($concepts, $topN);
        }

        // ── Phase 3: Cosine similarity against every stored embedding ─
        $scored = [];

        foreach ($docs as $doc) {
            $storedEmbedding = json_decode($doc->embedding, true);

            if (!is_array($storedEmbedding) || empty($storedEmbedding)) {
                continue;
            }

            $similarity = $this->cosineSimilarity($queryEmbedding, $storedEmbedding);

            // Convert cosine similarity (–1 to 1) → percentage (0–100)
            $score = (int) round((($similarity + 1) / 2) * 100);

            // FIX: Skip weak matches — below 40% are likely noise from generic words
            if ($score < 40) {
                continue;
            }

            $scored[] = [
                'document' => $doc,
                'score'    => $score,
                'reason'   => '',
            ];
        }

        if (empty($scored)) {
            // No strong matches found — return empty rather than noise
            Log::info('No matches above 40% threshold — returning empty result.');
            return [];
        }

        // Sort by score descending, take top candidates for enrichment
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        $topCandidates = array_slice($scored, 0, min(20, count($scored)));

        // ── Phase 4: Enrich top matches with AI-generated reasons ─────
        $enriched = $this->enrichWithReasons($concepts, $topCandidates);

        return array_slice($enriched, 0, $topN);
    }

    // ── 4. Add human-readable reasons to top vector matches ───────────
    private function enrichWithReasons(array $concepts, array $topCandidates): array
    {
        $conceptText = implode(', ', $concepts['keywords'] ?? []);

        $docList = array_map(fn($item) => [
            'id'    => $item['document']->archived_id,
            'title' => mb_substr($item['document']->title, 0, 100),
            'abs'   => mb_substr($item['document']->abstract ?? '', 0, 150),
            'score' => $item['score'],
        ], $topCandidates);

        $docsJson = json_encode(array_values($docList));

        // FIX: Better prompt that focuses on core concepts, not generic words
        $prompt = "You are an academic similarity checker. These archived capstones were flagged as "
                . "potentially similar to the pre-proposal below using vector embeddings.\n\n"
                . "STRICT RULES:\n"
                . "- IGNORE generic words: proposed, computerized, system, management, records, web, online, automated, digital\n"
                . "- IGNORE location names: Camarines Sur, Nabua, CSPC, Iriga, Philippines\n"
                . "- IGNORE school/dept names: college, department, polytechnic, CICT, BSIT\n"
                . "- Focus ONLY on: core domain, problem being solved, target users, system purpose\n"
                . "- If they are NOT actually similar in core concept, say so clearly\n\n"
                . "For each, write ONE sentence explaining if and why the core concept matches or does not match.\n"
                . "Return ONLY a JSON array. No markdown.\n"
                . '[{"id":<id>,"reason":"one sentence about core concept match or mismatch"}]'
                . "\n\nPRE-PROPOSAL:"
                . "\nTopic: {$concepts['main_topic']}"
                . "\nDomain: {$concepts['domain']}"
                . "\nKeywords: {$conceptText}"
                . "\n\nMATCHED DOCUMENTS:\n{$docsJson}";

        try {
            $raw     = $this->ollama->generate($prompt);
            $cleaned = trim(preg_replace('/```json|```/i', '', $raw));

            preg_match('/\[.*\]/s', $cleaned, $matches);
            $reasons = json_decode($matches[0] ?? $cleaned, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($reasons)) {
                $reasonMap = [];
                foreach ($reasons as $r) {
                    $reasonMap[(int) $r['id']] = $r['reason'] ?? '';
                }

                foreach ($topCandidates as &$item) {
                    $id = $item['document']->archived_id;
                    $item['reason'] = $reasonMap[$id]
                        ?? 'Semantically similar based on vector embedding analysis.';
                }
                unset($item);
            }
        } catch (\Exception $e) {
            Log::warning('enrichWithReasons failed', ['error' => $e->getMessage()]);
            foreach ($topCandidates as &$item) {
                $item['reason'] = 'Semantically similar based on vector embedding analysis.';
            }
            unset($item);
        }

        return $topCandidates;
    }

    // ── 5. Cosine similarity between two float vectors ─────────────────
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot    = 0.0;
        $normA  = 0.0;
        $normB  = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot   += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    // ── 6. Legacy keyword + Ollama scoring ─────────────────
    public function findSimilarDocumentsLegacy(array $concepts, int $topN = 10): array
    {
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

        $allTerms = array_unique(array_merge($concepts['keywords'] ?? [], $expandedTerms));

        $query = ArchivedDocument::query();
        $query->where(function ($q) use ($allTerms, $concepts) {
            foreach ($allTerms as $term) {
                if (strlen(trim($term)) < 3) continue;
                $q->orWhere('title',    'LIKE', '%' . $term . '%');
                $q->orWhere('abstract', 'LIKE', '%' . $term . '%');
            }
            foreach (explode(' ', $concepts['domain']) as $word) {
                if (strlen($word) > 3) {
                    $q->orWhere('title', 'LIKE', '%' . $word . '%');
                }
            }
        });

        $filtered = $query->limit(80)->get();

        if ($filtered->count() < 20) {
            $filteredIds = $filtered->pluck('archived_id')->toArray();
            $extras      = ArchivedDocument::whereNotIn('archived_id', $filteredIds)
                            ->orderBy('academic_year', 'desc')
                            ->limit(20 - $filtered->count())
                            ->get();
            $filtered    = $filtered->merge($extras);
        }

        $allScores = [];
        $chunks    = $filtered->chunk(10);

        foreach ($chunks as $chunkIndex => $chunk) {
            $docList = $chunk->map(fn($doc) => [
                'id'    => $doc->archived_id,
                'title' => mb_substr($doc->title, 0, 100),
                'abs'   => mb_substr($doc->abstract ?? '', 0, 150),
            ])->values()->toArray();

            $docsJson = json_encode($docList);

            // FIX: Better prompt with strict rules to ignore generic noise words
            $prompt = "You are an academic similarity checker. Compare this capstone pre-proposal "
                    . "against the archived capstones below.\n\n"
                    . "STRICT RULES:\n"
                    . "- IGNORE generic words: proposed, computerized, system, management, records, web, online, automated, digital\n"
                    . "- IGNORE location names: Camarines Sur, Nabua, CSPC, Iriga, Philippines\n"
                    . "- IGNORE school/dept names: college, department, polytechnic, CICT, BSIT\n"
                    . "- Focus ONLY on: core domain, problem being solved, target users, and system purpose\n"
                    . "- Score 70-100 ONLY if the core domain AND purpose are the same\n"
                    . "- Score 40-69 if the domain matches but purpose differs\n"
                    . "- Score 0-39 if they are fundamentally different topics\n\n"
                    . "Return ONLY a JSON array. No markdown.\n"
                    . '[{"id":<id>,"score":<0-100>,"reason":"one sentence focusing on core concept match or mismatch"}]'
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
                    // FIX: Only keep scores >= 40 from legacy path too
                    $scores = array_filter($scores, fn($s) => ($s['score'] ?? 0) >= 40);
                    $allScores = array_merge($allScores, array_values($scores));
                } else {
                    Log::warning("Chunk {$chunkIndex} parse failed", ['raw' => substr($raw, 0, 200)]);
                }
            } catch (\Exception $e) {
                Log::warning("Chunk {$chunkIndex} failed", ['error' => $e->getMessage()]);
                continue;
            }
        }

        if (empty($allScores)) {
            Log::info('Legacy search: no matches above 40% threshold.');
            return [];
        }

        usort($allScores, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        $topScores = array_slice($allScores, 0, $topN);

        $docMap  = $filtered->keyBy('archived_id');
        $results = [];

        foreach ($topScores as $item) {
            $doc = $docMap->get($item['id']);
            if (!$doc) continue;

            $results[] = [
                'document'   => $doc,
                'score'      => (int) ($item['score'] ?? 0),
                'reason'     => $item['reason'] ?? '',
                'matched_on' => $item['matched_on'] ?? [],
            ];
        }

        return $results;
    }

    // ── 7. Suggest instructors ────────────────────────────
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
            ->groupBy('e.expert_id', 'e.first_name', 'e.last_name', 'e.credentials', 'e.department')
            ->get();

        if ($experts->isEmpty()) {
            return [];
        }

        $validIds  = $experts->pluck('expert_id')->toArray();
        $expertMap = $experts->keyBy('expert_id');

        $domain   = $concepts['domain'];
        $keywords = implode(', ', $concepts['keywords'] ?? []);
        $listJson = json_encode($experts->map(fn($e) => [
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
            $raw      = $this->ollama->generate($prompt);
            $cleaned  = trim(preg_replace('/```json|```/i', '', $raw));

            preg_match('/\[.*\]/s', $cleaned, $matches);
            $jsonStr  = $matches[0] ?? $cleaned;
            $suggests = json_decode($jsonStr, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($suggests)) {
                Log::error('suggestInstructors parse error', ['raw' => $raw]);
                $suggests = [];
            }

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