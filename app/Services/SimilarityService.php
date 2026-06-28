<?php

namespace App\Services;

use App\Models\ArchivedDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;

class SimilarityService
{
    public function __construct(private OllamaSSHClient $ollama) {}

    // ── 1. Extract raw text from uploaded PDF ─────────────────────────
    public function extractPdfText(string $filePath): string
    {
        $parser = new Parser;
        $pdf = $parser->parseFile($filePath);

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

        $raw = $this->ollama->generate($prompt);
        $cleaned = trim(preg_replace('/```json|```/i', '', $raw));

        preg_match('/\{.*\}/s', $cleaned, $m);
        $data = json_decode($m[0] ?? $cleaned, true);

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
            'a', 'an', 'the', 'of', 'for', 'and', 'or', 'in', 'to', 'is', 'are',
            'with', 'that', 'this', 'on', 'at', 'by', 'from', 'it', 'its', 'be',
            'as', 'was', 'were', 'has', 'have', 'had', 'do', 'does', 'did', 'not',
            'its', 'their', 'our', 'your', 'his', 'her',
            'web', 'mobile', 'desktop', 'online', 'electronic', 'digital',
            'internet', 'android', 'ios', 'app', 'application', 'platform', 'portal',
            'proposed', 'computerized', 'system', 'management', 'records', 'record',
            'using', 'based', 'automated', 'implementation', 'development', 'design',
            'modified', 'integrated', 'centralized', 'computer', 'computing',
            'software', 'hardware', 'database', 'information', 'technology',
            'generation', 'data', 'file', 'document', 'report', 'form',
            'camarines', 'sur', 'nabua', 'cspc', 'iriga', 'city', 'colleges',
            'polytechnic', 'philippines', 'bicol', 'naga', 'buhi', 'bato',
            'rinconada', 'ocampo', 'polangui', 'albay', 'laguna', 'manila',
            'college', 'department', 'university', 'school', 'cict', 'bsit',
            'bscs', 'institute', 'campus', 'high', 'national', 'junior', 'senior',
            'elementary', 'secondary', 'tertiary', 'academy',
            'version', 'study', 'case', 'research', 'project', 'capstone',
            'thesis', 'proposal', 'pre', 'post', 'new', 'old', 'local', 'global',
        ];

        $text = preg_replace('/[\/\\\,\.\(\)\[\]\{\}:;!?"\'\-_]/', ' ', strtolower($text));
        $words = explode(' ', $text);
        $filtered = array_filter(
            $words,
            fn ($w) => ! in_array(trim($w), $stopWords)
                && strlen(trim($w)) > 2
                && ! is_numeric(trim($w))
        );

        return implode(' ', array_values($filtered));
    }

    // ── Shared AI scoring prompt ───────────────────────────────────────
    private function buildScoringPrompt(array $concepts, string $docsJson): string
    {
        $conceptText = implode(', ', $concepts['keywords'] ?? []);
        $targetUsers = $concepts['target_users'] ?? 'not specified';

        return "You are an academic similarity checker comparing archived capstone projects to a new pre-proposal.\n\n"
        ."Score each archived capstone. Return ONLY valid JSON.\n\n"
        ."SCORING:\n"
        ."- 60-100: Two or more significant overlaps (domain + function, domain + users)\n"
        ."- 40-59: One substantial overlap (not just shared users)\n"
        ."- Below 40: Trivial overlap (shared users only) or no overlap — exclude\n\n"
        ."Example: If pre-proposal is capstone tracking:\n"
        ."- Student record tracking system = 60-70 (domain + function)\n"
        ."- Student attendance system = 40-50 (function overlap only)\n"
        ."- Project mentioning \"students\" = below 40 (trivial user mention)\n\n"
        ."Ignore: platform (web/mobile/desktop), school names.\n\n"
        .'[{"id":<id>,"score":<40-100>,"reason":"one sentence"}]'
        ."\n\nPRE-PROPOSAL:\n"
        ."Topic: {$concepts['main_topic']}\n"
        ."Keywords: {$conceptText}\n"
        ."Target Users: {$targetUsers}\n"
        ."Problem: {$concepts['problem_statement']}\n"
        ."Solution: {$concepts['proposed_solution']}\n\n"
        ."ARCHIVED CAPSTONES:\n{$docsJson}";
    }

    // ── 3. Main similarity search ──────────────────────────────────────
    public function findSimilarDocuments(array $concepts, int $topN = 10): array
    {
        $rawQueryText = implode("\n", array_filter([
            $concepts['main_topic'] ?? '',
            implode(', ', $concepts['keywords'] ?? []),
            $concepts['problem_statement'] ?? '',
            $concepts['proposed_solution'] ?? '',
            $concepts['target_users'] ?? '',
        ]));

        $queryText = $this->removeStopWords($rawQueryText);

        // ── Phase 1: Embedding pre-filter
        try {
            $queryEmbedding = $this->ollama->embed($queryText);
            $docs = ArchivedDocument::whereNotNull('embedding')->get();

            if ($docs->count() < 5) {
                return $this->findSimilarDocumentsLegacy($concepts, $topN);
            }

            $scored = [];
            foreach ($docs as $doc) {
                $stored = json_decode($doc->embedding, true);
                if (! is_array($stored) || empty($stored)) {
                    continue;
                }

                $similarity = $this->cosineSimilarity($queryEmbedding, $stored);
                if ($similarity < 0.25) {
                    continue;
                }

                $scored[] = ['document' => $doc, 'similarity' => $similarity];
            }

            usort($scored, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);
            $candidates = collect(array_map(fn ($s) => $s['document'], array_slice($scored, 0, 10)));

            if ($candidates->count() < 3) {
                Log::info('Too few embedding candidates, merging SQL fallback', ['count' => $candidates->count()]);
                $sqlCandidates = $this->sqlPreFilter($concepts);
                $existingIds = $candidates->pluck('archived_id')->toArray();
                $extras = $sqlCandidates->whereNotIn('archived_id', $existingIds)->take(10);
                $candidates = $candidates->merge($extras);
            }

        } catch (\Exception $e) {
            Log::warning('Embedding failed, using SQL fallback', ['error' => $e->getMessage()]);
            $candidates = $this->sqlPreFilter($concepts);
        }

        if ($candidates->isEmpty()) {
            return [];
        }

        // ── Phase 2: AI semantic scoring (chunked to respect context window)
        $allScores = [];

        foreach ($candidates->chunk(5) as $chunkIndex => $chunk) {
            $docList = $chunk->map(fn ($doc) => [
                'id' => $doc->archived_id,
                'title' => mb_substr($doc->title, 0, 100),
                'abs' => mb_substr($doc->abstract ?? '', 0, 150),
            ])->values()->toArray();

            $docsJson = json_encode($docList);
            $prompt = $this->buildScoringPrompt($concepts, $docsJson);

            try {
                $raw = $this->ollama->generate($prompt);

                // Remove markdown code fences
                $cleaned = trim(preg_replace('/```json|```|\`\`/i', '', $raw));

                // Find JSON array - extract from first [ to last ]
                $start = strpos($cleaned, '[');
                $end = strrpos($cleaned, ']');

                if ($start !== false && $end !== false && $end > $start) {
                    $jsonStr = substr($cleaned, $start, $end - $start + 1);
                } else {
                    Log::warning("Chunk {$chunkIndex} parse failed - no array brackets found", ['raw' => substr($raw, 0, 300)]);
                    continue;
                }

                $scores = json_decode($jsonStr, true);

                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($scores)) {
                    Log::warning("Chunk {$chunkIndex} parse failed - JSON decode error", ['raw' => substr($raw, 0, 300), 'json' => substr($jsonStr, 0, 200), 'error' => json_last_error_msg()]);
                    continue;
                }

                $scores = array_filter($scores, fn ($s) => isset($s['id']) && is_numeric($s['id']) &&
                    isset($s['score']) && (int) $s['score'] >= 40
                );
                $allScores = array_merge($allScores, array_values($scores));

                // Debug log for chunk results
                Log::debug("Chunk {$chunkIndex} scored", ['count' => count($scores), 'ids' => array_column($scores, 'id')]);

            } catch (\Exception $e) {
                Log::warning("Chunk {$chunkIndex} failed", ['error' => $e->getMessage()]);

                continue;
            }
        }

        if (empty($allScores)) {
            Log::info('No AI scores returned from any chunk', ['candidate_count' => $candidates->count()]);

            return [];
        }

        usort($allScores, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $docMap = $candidates->keyBy('archived_id');
        $results = [];

        foreach (array_slice($allScores, 0, $topN) as $item) {
            if (! isset($item['id']) || ! is_numeric($item['id'])) {
                continue;
            }

            $doc = $docMap->get((int) $item['id']);
            if (! $doc) {
                continue;
            }

            $results[] = [
                'document' => $doc,
                'score' => (int) ($item['score'] ?? 0),
                'reason' => $item['reason'] ?? '',
                'matched_on' => [],
            ];
        }

        return $results;
    }

    // ── 4. SQL pre-filter fallback ────────────────────────────────────
    private function sqlPreFilter(array $concepts): Collection
    {
        $platformWords = ['web', 'mobile', 'app', 'system', 'online', 'digital', 'desktop', 'internet'];

        $allTerms = array_unique(array_merge(
            $concepts['keywords'] ?? [],
            explode(' ', $concepts['target_users'] ?? '')
        ));

        $query = ArchivedDocument::query();
        $query->where(function ($q) use ($allTerms, $platformWords) {
            foreach ($allTerms as $term) {
                $term = trim((string) $term);
                if (strlen($term) < 3) {
                    continue;
                }
                if (in_array(strtolower($term), $platformWords)) {
                    continue;
                }
                $q->orWhere('title', 'LIKE', '%'.$term.'%');
                $q->orWhere('abstract', 'LIKE', '%'.$term.'%');
            }
        });

        $filtered = $query->limit(50)->get();

        if ($filtered->count() < 10) {
            $ids = $filtered->pluck('archived_id')->toArray();
            $extras = ArchivedDocument::whereNotIn('archived_id', $ids)
                ->orderBy('academic_year', 'desc')
                ->limit(50 - $filtered->count())
                ->get();
            $filtered = $filtered->merge($extras);
        }

        return $filtered;
    }

    // ── 5. Legacy keyword + Ollama scoring (fallback) ─────────────────
    public function findSimilarDocumentsLegacy(array $concepts, int $topN = 10): array
    {
        $platformWords = ['web', 'mobile', 'app', 'system', 'online', 'digital', 'desktop', 'internet'];
        $conceptText = implode(', ', $concepts['keywords'] ?? []);

        // Expand search terms via AI
        $expandPrompt = "List 15 search terms related to this capstone topic that could appear in similar capstone titles.\n"
                      ."Focus on the PROBLEM DOMAIN and TARGET USERS only. No platform words like web, mobile, desktop.\n"
                      ."Return ONLY a JSON array of strings. No markdown.\n\n"
                      ."Topic: {$concepts['main_topic']}\n"
                      .'Target Users: '.($concepts['target_users'] ?? '')."\n"
                      ."Keywords: {$conceptText}\n\n"
                      .'["term1","term2",...]';

        try {
            $raw = $this->ollama->generate($expandPrompt);
            $cleaned = trim(preg_replace('/```json|```/i', '', $raw));
            preg_match('/\[.*\]/s', $cleaned, $m);
            $expandedTerms = json_decode($m[0] ?? $cleaned, true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($expandedTerms)) {
                $expandedTerms = $concepts['keywords'] ?? [];
            }
        } catch (\Exception $e) {
            $expandedTerms = $concepts['keywords'] ?? [];
        }

        $allTerms = array_unique(array_merge($concepts['keywords'] ?? [], $expandedTerms));

        $query = ArchivedDocument::query();
        $query->where(function ($q) use ($allTerms, $platformWords) {
            foreach ($allTerms as $term) {
                $term = trim((string) $term);
                if (strlen($term) < 3) {
                    continue;
                }
                if (in_array(strtolower($term), $platformWords)) {
                    continue;
                }
                $q->orWhere('title', 'LIKE', '%'.$term.'%');
                $q->orWhere('abstract', 'LIKE', '%'.$term.'%');
            }
        });

        $filtered = $query->limit(30)->get();

        if ($filtered->count() < 10) {
            $filteredIds = $filtered->pluck('archived_id')->toArray();
            $extras = ArchivedDocument::whereNotIn('archived_id', $filteredIds)
                ->orderBy('academic_year', 'desc')
                ->limit(10 - $filtered->count())
                ->get();
            $filtered = $filtered->merge($extras);
        }

        $allScores = [];

foreach ($filtered->chunk(5) as $chunkIndex => $chunk) {
            $docList  = $chunk->map(fn ($doc) => [
                'id'    => $doc->archived_id,
                'title' => mb_substr($doc->title, 0, 100),
                'abs'   => mb_substr($doc->abstract ?? '', 0, 150),
            ])->values()->toArray();

            $docsJson = json_encode($docList);
            $prompt = $this->buildScoringPrompt($concepts, $docsJson);

            try {
                $raw = $this->ollama->generate($prompt);

                // Remove markdown code fences and extract JSON array
                $cleaned = trim(preg_replace('/```json|```/i', '', $raw));

                $start = strpos($cleaned, '[');
                $end = strrpos($cleaned, ']');

                if ($start !== false && $end !== false && $end > $start) {
                    $jsonStr = substr($cleaned, $start, $end - $start + 1);
                } else {
                    $jsonStr = $cleaned;
                }

                $scores = json_decode($jsonStr, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($scores)) {
                    $scores = array_filter($scores, fn ($s) => isset($s['id']) && is_numeric($s['id']) &&
                        isset($s['score']) && (int) $s['score'] >= 40
                    );
                    $allScores = array_merge($allScores, array_values($scores));
                } else {
                    Log::warning("Chunk {$chunkIndex} parse failed", ['raw' => substr($raw, 0, 200), 'error' => json_last_error_msg()]);
                }
            } catch (\Exception $e) {
                Log::warning("Chunk {$chunkIndex} failed", ['error' => $e->getMessage()]);

                continue;
            }
        }

        if (empty($allScores)) {
            return [];
        }

        usort($allScores, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $docMap = $filtered->keyBy('archived_id');
        $results = [];

        foreach (array_slice($allScores, 0, $topN) as $item) {
            if (! isset($item['id']) || ! is_numeric($item['id'])) {
                continue;
            }

            $doc = $docMap->get((int) $item['id']);
            if (! $doc) {
                continue;
            }

            $results[] = [
                'document' => $doc,
                'score' => (int) ($item['score'] ?? 0),
                'reason' => $item['reason'] ?? '',
                'matched_on' => [],
            ];
        }

        return $results;
    }

    // ── 6. Cosine similarity between two float vectors ─────────────────
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    // ── 7. Suggest instructors ─────────────────────────────────────────
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

        $validIds = $experts->pluck('expert_id')->toArray();
        $expertMap = $experts->keyBy('expert_id');

        $domain = $concepts['domain'];
        $keywords = implode(', ', $concepts['keywords'] ?? []);
        $listJson = json_encode($experts->map(fn ($e) => [
            'id' => $e->expert_id,
            'name' => $e->first_name.' '.$e->last_name,
            'expertise' => $e->expertise ?? 'General',
        ])->values()->toArray());

        $prompt = "You are matching a capstone pre-proposal to instructors using semantic analysis.\n"
                ."Analyze the meaning and relevance of each instructor's expertise against the proposal.\n"
                ."IMPORTANT: You MUST only return instructors from the list below using their exact IDs.\n"
                ."Do NOT invent new instructors. If only 1 instructor exists, return only that 1.\n"
                ."Return ONLY a valid JSON array. No markdown. No explanation.\n\n"
                .'[{"id":<exact id from list>,"name":"exact name","reason":"semantic reason why expertise matches"}]'
                ."\n\nPRE-PROPOSAL:"
                ."\nDomain: {$domain}"
                ."\nKeywords: {$keywords}"
                ."\nTopic: {$concepts['main_topic']}"
                ."\nProblem: {$concepts['problem_statement']}"
                ."\n\nINSTRUCTOR LIST (ONLY use these exact IDs):\n{$listJson}";

        try {
            $raw = $this->ollama->generate($prompt);
            $cleaned = trim(preg_replace('/```json|```/i', '', $raw));

            preg_match('/\[.*\]/s', $cleaned, $matches);
            $suggests = json_decode($matches[0] ?? $cleaned, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($suggests)) {
                Log::error('suggestInstructors parse error', ['raw' => $raw]);
                $suggests = [];
            }

            $results = [];
            foreach ($suggests as $s) {
                if (! isset($s['id']) || ! is_numeric($s['id'])) {
                    continue;
                }

                $id = (int) $s['id'];
                if (! in_array($id, $validIds)) {
                    continue;
                }

                $expert = $expertMap->get($id);
                if (! $expert) {
                    continue;
                }

                $results[] = [
                    'id' => $id,
                    'name' => $expert->first_name.' '.$expert->last_name,
                    'expertise' => $expert->expertise ?? 'General',
                    'reason' => $s['reason'] ?? '',
                ];
            }

            if (empty($results)) {
                foreach ($experts as $e) {
                    $results[] = [
                        'id' => $e->expert_id,
                        'name' => $e->first_name.' '.$e->last_name,
                        'expertise' => $e->expertise ?? 'General',
                        'reason' => 'Semantically relevant based on expertise in '.($e->expertise ?? 'computing').'.',
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
