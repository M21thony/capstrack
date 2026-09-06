<?php

namespace App\Console\Commands;

use App\Models\ArchivedDocument;
use App\Services\OllamaSSHClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Generates a fine-tuning dataset for distilling the duplication checker's
 * three qwen2.5:7b prompts (see SimilarityService) down onto a smaller
 * local model. Reuses the exact prompt wording already in production so the
 * fine-tuned model learns the same task shape, not a paraphrased one.
 *
 * Each output line is {"task", "prompt", "response"} — response is the raw
 * (but validated-parseable) JSON the 7b model actually returned, so bad
 * generations never make it into the training set.
 */
class ExportDuplicationTrainingData extends Command
{
    protected $signature = 'duplication:export-training-data
        {--limit=100 : Max archived documents to use as source pre-proposals}
        {--candidates=6 : Candidate documents per similarity-verdict example}
        {--tasks=concepts,similarity,instructors : Comma-separated subset to generate}
        {--positive-ratio=0.4 : Fraction of similarity examples that should be a real (paraphrased) duplicate}
        {--output=storage/app/training-data/dataset.jsonl}';

    protected $description = 'Export prompt/response pairs from the live duplication checker for fine-tuning a smaller model';

    public function handle(OllamaSSHClient $ollama): void
    {
        $tasks = array_map('trim', explode(',', $this->option('tasks')));
        $limit = (int) $this->option('limit');
        $candidateCount = (int) $this->option('candidates');
        $positiveRatio = (float) $this->option('positive-ratio');
        $outputPath = base_path($this->option('output'));

        if (! is_dir(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0755, true);
        }

        $docs = ArchivedDocument::whereNotNull('abstract')
            ->where('abstract', '!=', '')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        if ($docs->isEmpty()) {
            $this->error('No archived documents with an abstract to build examples from.');

            return;
        }

        // Loaded once and reused for every pickCandidates() call — decoding
        // and re-querying all embeddings per source document would be
        // wasteful, and this set doesn't change during the run. Keyed by
        // archived_id so a candidate's embedding can be looked up directly.
        $embeddingPool = ArchivedDocument::whereNotNull('embedding')
            ->get(['archived_id', 'embedding'])
            ->mapWithKeys(fn ($d) => [$d->archived_id => json_decode($d->embedding, true)])
            ->filter(fn ($e) => is_array($e) && ! empty($e));

        $experts = in_array('instructors', $tasks) ? $this->loadExperts() : collect();

        if (in_array('instructors', $tasks) && $experts->isEmpty()) {
            $this->warn('No approved experts found — skipping the "instructors" task.');
            $tasks = array_diff($tasks, ['instructors']);
        }

        $fh = fopen($outputPath, 'w');
        $written = ['concepts' => 0, 'similarity' => 0, 'instructors' => 0];
        $skipped = 0;
        $positivesMade = 0;

        $this->info('Generating training examples for tasks: '.implode(', ', $tasks));
        $bar = $this->output->createProgressBar($docs->count());
        $bar->start();

        foreach ($docs as $doc) {
            // A genuine near-duplicate of an existing archived document,
            // reworded so it isn't just a verbatim string match — without
            // some fraction of these, real archived docs almost never rank
            // as a true conceptual match against each other (duplicates are
            // supposed to be rare!), so the similarity task would only ever
            // see "false" verdicts and learn to always say no.
            $makePositive = in_array('similarity', $tasks) && (mt_rand() / mt_getrandmax()) < $positiveRatio;

            if ($makePositive) {
                $pdfText = $this->paraphraseAbstract($ollama, $doc);
                $positivesMade++;
            } else {
                $pdfText = trim($doc->title.'. '.$doc->abstract);
            }

            // ── Task 1: concept extraction ──────────────────────────
            $concepts = null;

            if (in_array('concepts', $tasks) || in_array('similarity', $tasks) || in_array('instructors', $tasks)) {
                [$prompt, $raw] = $this->extractConcepts($ollama, $pdfText);
                $concepts = $this->tryDecode($raw);

                if ($concepts === null) {
                    $skipped++;
                    $bar->advance();

                    continue; // everything else below needs valid concepts
                }

                // Only banked for genuinely fresh (non-paraphrased) source
                // text — a paraphrase's concept extraction is a means to an
                // end for the similarity task below, not itself a clean
                // example of extracting from an original document.
                if (in_array('concepts', $tasks) && ! $makePositive) {
                    $this->writeLine($fh, 'concepts', $prompt, $raw);
                    $written['concepts']++;
                }
            }

            // ── Task 2: similarity verdicts ─────────────────────────
            if (in_array('similarity', $tasks)) {
                $forceIncludeId = $makePositive ? $doc->archived_id : null;
                $candidates = $this->pickCandidates($doc, $candidateCount, $embeddingPool, $forceIncludeId);

                if ($candidates->isNotEmpty()) {
                    [$prompt, $raw] = $this->enrichWithReasons($ollama, $concepts, $candidates);

                    if ($this->tryDecode($raw) !== null) {
                        $this->writeLine($fh, 'similarity', $prompt, $raw);
                        $written['similarity']++;
                    } else {
                        $skipped++;
                    }
                }
            }

            // ── Task 3: instructor suggestions ──────────────────────
            if (in_array('instructors', $tasks)) {
                [$prompt, $raw] = $this->suggestInstructors($ollama, $concepts, $experts);

                if ($this->tryDecode($raw) !== null) {
                    $this->writeLine($fh, 'instructors', $prompt, $raw);
                    $written['instructors']++;
                } else {
                    $skipped++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        fclose($fh);
        $this->newLine();

        $this->info(sprintf(
            'Done. concepts=%d similarity=%d (%d paraphrased-positive) instructors=%d | skipped (bad JSON)=%d',
            $written['concepts'],
            $written['similarity'],
            $positivesMade,
            $written['instructors'],
            $skipped
        ));
        $this->info("Wrote {$outputPath}");
        $this->comment('Review a sample of this file by hand before using it to fine-tune — this is raw 7b output, not ground truth.');
    }

    // Reworded version of a real archived document — same domain, problem,
    // and solution, different phrasing — so it stands in for "a different
    // student independently proposed the same thing," which is exactly the
    // case the duplication checker exists to catch.
    private function paraphraseAbstract(OllamaSSHClient $ollama, ArchivedDocument $doc): string
    {
        $prompt = 'Rewrite the following capstone title and abstract in different wording, as if a '
                ."different student proposed the same underlying system independently. Keep the exact "
                ."same domain, problem, target users, and proposed solution — only change surface "
                ."phrasing, sentence structure, and word choice. Do not add new features or change scope.\n"
                ."Return ONLY the rewritten title and abstract as plain text. No labels, no markdown.\n\n"
                ."Title: {$doc->title}\nAbstract: {$doc->abstract}";

        return trim($ollama->generate($prompt, 500));
    }

    // ── Prompt builders — kept in exact sync with SimilarityService ────

    private function extractConcepts(OllamaSSHClient $ollama, string $pdfText): array
    {
        $truncated = mb_substr($pdfText, 0, 6000);

        $prompt = <<<PROMPT
You are an academic research analyzer. Read this capstone pre-proposal and extract key information.
Return ONLY a valid JSON object. No explanation. No markdown fences.

{
  "title": "the pre-proposal's title, as written",
  "main_topic": "one sentence describing the core subject",
  "domain": "e.g. Web System, Mobile App, IoT, AI/ML, Desktop App, Management System",
  "keywords": ["up to 10 keywords"],
  "problem_statement": "2 sentences max about the problem being solved",
  "proposed_solution": "2 sentences max describing what the system will do",
  "scope": "2 sentences max on what the system will and won't cover",
  "technologies": ["list of technologies mentioned"],
  "target_users": "who will use this system"
}

Pre-proposal text:
{$truncated}
PROMPT;

        $raw = $ollama->generate($prompt, 700);

        return [$prompt, $this->stripFences($raw)];
    }

    private function enrichWithReasons(OllamaSSHClient $ollama, array $concepts, $candidates): array
    {
        $conceptText = implode(', ', $concepts['keywords'] ?? []);

        $docList = $candidates->map(fn ($d) => [
            'id' => $d->archived_id,
            'title' => mb_substr($d->title, 0, 100),
            'abs' => mb_substr($d->abstract ?? '', 0, 150),
            'score' => rand(45, 90), // placeholder — the checker's real vector score isn't needed to train the judgment step
        ])->values();

        $docsJson = json_encode($docList);

        $prompt = 'You are an academic similarity checker. These archived capstones were flagged as '
                ."potentially similar to the pre-proposal below using vector embeddings.\n\n"
                ."STRICT RULES:\n"
                ."- IGNORE generic words: proposed, computerized, system, management, records, web, web-based, mobile, online, automated, digital\n"
                ."- IGNORE location names: Camarines Sur, Nabua, CSPC, Iriga, Philippines\n"
                ."- IGNORE school/dept names and specific institution names: college, department, polytechnic, CICT, BSIT, high school, national high school, elementary school, academy, institute, learning center\n"
                ."- Focus ONLY on: core domain, problem being solved, target users, system purpose\n"
                ."- If they are NOT actually similar in core concept, say so clearly\n\n"
                ."For each, decide whether the core concept genuinely matches, and write ONE sentence explaining why or why not.\n"
                ."Return ONLY a JSON array. No markdown.\n"
                .'[{"id":<id>,"matches":true|false,"reason":"one sentence about core concept match or mismatch"}]'
                ."\n\nPRE-PROPOSAL:"
                ."\nTopic: {$concepts['main_topic']}"
                ."\nDomain: {$concepts['domain']}"
                ."\nKeywords: {$conceptText}"
                ."\n\nMATCHED DOCUMENTS:\n{$docsJson}";

        $raw = $ollama->generate($prompt, 2000);

        return [$prompt, $this->stripFences($raw)];
    }

    private function suggestInstructors(OllamaSSHClient $ollama, array $concepts, $experts): array
    {
        $domain = $concepts['domain'] ?? '';
        $keywords = implode(', ', $concepts['keywords'] ?? []);
        $listJson = json_encode($experts->map(fn ($e) => [
            'id' => $e->expert_id,
            'name' => $e->first_name.' '.$e->last_name,
            'expertise' => $e->expertise ?? 'General',
        ])->values());

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

        $raw = $ollama->generate($prompt, 800);

        return [$prompt, $this->stripFences($raw)];
    }

    private function loadExperts()
    {
        return DB::table('capstone_experts as e')
            ->leftJoin('expert_expertise as ex', 'e.expert_id', '=', 'ex.expert_id')
            ->where('e.approved_status', 1)
            ->select(
                'e.expert_id',
                'e.first_name',
                'e.last_name',
                DB::raw('GROUP_CONCAT(ex.expertise_field SEPARATOR ", ") as expertise')
            )
            ->groupBy('e.expert_id', 'e.first_name', 'e.last_name')
            ->get();
    }

    // Ranks every other document by real cosine similarity against the
    // source's own embedding — the same signal the production checker
    // actually searches with — and takes half from the top (genuinely
    // close, likely real matches) and half from the bottom (genuinely far,
    // reliable non-matches). An earlier version of this used same-program
    // vs. different-program as a proxy for "likely similar," but two
    // capstones sharing a program are usually about completely different
    // topics — that produced 429 "false" verdicts against only 12 "true"
    // ones, which would have taught the model to always say no.
    //
    // $forceIncludeId guarantees a specific document is in the candidate
    // list regardless of embedding rank — used when $source's text is
    // actually a paraphrase of that document, so it's a genuine positive.
    private function pickCandidates(ArchivedDocument $source, int $count, $embeddingPool, ?int $forceIncludeId = null)
    {
        $sourceEmbedding = $embeddingPool->get($source->archived_id);

        if (! $sourceEmbedding) {
            return collect();
        }

        $ranked = $embeddingPool
            ->except($source->archived_id)
            ->map(fn ($embedding) => $this->cosineSimilarity($sourceEmbedding, $embedding))
            ->sortDesc();

        $slots = $forceIncludeId ? $count - 1 : $count;
        $half = (int) ceil($slots / 2);
        $closeIds = $ranked->take($half)->keys();
        $farIds = $ranked->reverse()->take($slots - $closeIds->count())->keys();

        $candidateIds = $closeIds->merge($farIds);

        if ($forceIncludeId) {
            $candidateIds->push($forceIncludeId);
        }

        return ArchivedDocument::whereIn('archived_id', $candidateIds)->get()->shuffle();
    }

    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }

    private function stripFences(string $raw): string
    {
        return trim(preg_replace('/```json|```/i', '', $raw));
    }

    private function tryDecode(string $json): ?array
    {
        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : null;
    }

    private function writeLine($fh, string $task, string $prompt, string $response): void
    {
        fwrite($fh, json_encode([
            'task' => $task,
            'prompt' => $prompt,
            'response' => $response,
        ], JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
}
