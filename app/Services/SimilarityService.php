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
The document is a standard Pre-Proposal Title form - extract each section as written, don't summarize
sections that already exist verbatim in the text (problem_statement, proposed_solution, scope,
limitations, sdg_goals, research_agenda, sdm should be copied/lightly trimmed from their matching
form section, not paraphrased from scratch).
Return ONLY a valid JSON object. No explanation. No markdown fences.

{
  "title": "the pre-proposal's title, as written",
  "main_topic": "one sentence describing the core subject",
  "domain": "e.g. Web System, Mobile App, IoT, AI/ML, Desktop App, Management System",
  "keywords": ["up to 10 keywords"],
  "problem_statement": "copy the Problem Statement section, 2-3 sentences",
  "proposed_solution": "copy the Proposed Solution section, 2-3 sentences or bullet points joined by '; '",
  "scope": "copy the Scope of the Proposed Solution section",
  "limitations": "copy the Limitations of the Proposed Solution section, bullet points joined by '; '",
  "sdg_goals": "the Applicable UN Sustainable Development Goal(s), formatted exactly like 'SDG 4 – Quality Education' - if the document only gives a number or a short name, expand it to that exact 'SDG N – Full Name' format",
  "research_agenda": ["each Applicable Research Agenda and Priority Area item as its own array entry, as a PLAIN STRING - never a nested object"],
  "sdm": "the Software Development Methodology named in the document, or 'Agile Development Methodology' if none is stated",
  "technologies": ["list of technologies mentioned"],
  "target_users": "who will use this system"
}

Pre-proposal text:
{$truncated}
PROMPT;

        $raw = $this->ollama->generate($prompt, 700);
        $cleaned = trim(preg_replace('/```json|```/i', '', $raw));
        $data = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('extractKeyConcepts parse error', ['raw' => $raw]);
            throw new \Exception('AI returned invalid JSON during concept extraction.');
        }

        if (isset($data['research_agenda'])) {
            $data['research_agenda'] = $this->normalizeToStringList($data['research_agenda']);
        }

        return $data;
    }

    // The local model occasionally returns research_agenda entries as nested
    // objects (e.g. {"agenda": "..."}) instead of the plain strings the
    // prompt asks for. Left unnormalized, that shape flows straight through
    // to the browser, gets frozen into a hidden input's string value as the
    // literal text "[object Object]", and gets saved to the database that
    // way - permanently losing the real content. Coerce every entry to a
    // plain string here so a shape slip never reaches the page at all.
    private function normalizeToStringList($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $strings = array_map(function ($item) {
            if (is_string($item)) {
                return trim($item);
            }

            if (is_array($item)) {
                foreach (['name', 'title', 'agenda', 'label', 'text', 'value', 'priority_area'] as $key) {
                    if (! empty($item[$key]) && is_string($item[$key])) {
                        return trim($item[$key]);
                    }
                }

                $scalarValues = array_filter($item, 'is_string');
                if ($scalarValues) {
                    return trim(implode(', ', $scalarValues));
                }
            }

            return null;
        }, $value);

        return array_values(array_filter($strings, fn ($s) => $s !== null && $s !== ''));
    }

    // ── 1.5. Reject uploads that aren't actually a pre-proposal ────────
    // Kept as its own small call rather than folded into extractKeyConcepts'
    // schema — that prompt's exact shape is what the fine-tuned local model
    // was trained on, and adding a field it never saw in training would
    // make it less reliable at the one thing it was specifically tuned for.
    public function isPreProposal(string $pdfText): array
    {
        $truncated = mb_substr($pdfText, 0, 4000);

        $prompt = <<<PROMPT
You are a document classifier for a capstone project system. Determine whether
the text below is a CAPSTONE PRE-PROPOSAL — an academic proposal document for
an undergraduate capstone/thesis project. A genuine pre-proposal typically
includes some combination of: a title, introduction/background, problem
statement, project objectives, scope and limitations, or proposed methodology.

It is NOT a pre-proposal if it is, for example: a resume/CV, an unrelated
document, a completed full thesis manuscript, a form, a receipt, a scanned
image with no real academic content, or blank/gibberish text.

Return ONLY a valid JSON object. No explanation. No markdown fences.
{"is_pre_proposal": true|false, "reason": "one sentence explaining the verdict"}

Document text:
{$truncated}
PROMPT;

        $raw = $this->ollama->generate($prompt, 150);
        $cleaned = trim(preg_replace('/```json|```/i', '', $raw));
        $data = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            Log::warning('isPreProposal parse error — failing open (treating as a valid pre-proposal)', ['raw' => $raw]);

            // A classifier hiccup shouldn't block a legitimate submission —
            // same fail-open philosophy already used for missing "matches"
            // verdicts in enrichWithReasons().
            return ['is_pre_proposal' => true, 'reason' => ''];
        }

        return $data;
    }

    // ── Stop words filter ─────────────────────────────────────────────
    //
    // IMPORTANT: only true filler goes here - grammar, delivery-platform
    // words ("web", "mobile" - describe HOW something is delivered, not
    // WHAT it does), and institution/location names. Domain-descriptive
    // words like "management", "system", "monitoring", "capstone", or
    // "thesis" must NOT be added here even though they appear in almost
    // every title: testing against the real archive showed that stripping
    // them made a genuinely-related document ("Capstone Class Records")
    // score the same 72-82% as a completely unrelated one (a smart door
    // lock) - the embedding model needs those words to tell projects
    // apart; leaving them in spread the same comparison to 90% vs 78-82%.
    // Must match GenerateEmbeddings::cleanText()'s list exactly, since
    // archived documents' stored embeddings were generated with it.
    private function removeStopWords(string $text): string
    {
        $stopWords = $this->stopWordsList();

        // Strip punctuation (including hyphens) before splitting, so a
        // compound like "web-based" or "cross-platform" breaks into its
        // individual words instead of surviving as one unmatched token —
        // this is why "web-based" wasn't actually being filtered before.
        $text = preg_replace('/[\/\\\,\.\(\)\[\]\{\}:;!?"\'\-_]/', ' ', $text);

        $words = preg_split('/\s+/', strtolower(trim($text)));
        $filtered = array_filter($words, fn ($w) => ! in_array(trim($w), $stopWords) && strlen(trim($w)) > 2);

        return implode(' ', $filtered);
    }

    // Single source of truth for what counts as filler - shared by
    // removeStopWords() (embeddings) and extractHighlightWords() (the
    // literal word-highlighting shown on a matched abstract), so the two
    // features never disagree about which words are "generic noise" vs.
    // genuine domain content.
    private function stopWordsList(): array
    {
        return array_merge([
            // Standard
            'a', 'an', 'the', 'of', 'for', 'and', 'or', 'in', 'to', 'is', 'are',
            'with', 'that', 'this', 'on', 'at', 'by', 'from', 'it', 'its', 'be',
            'as', 'was', 'were', 'has', 'have', 'had', 'do', 'does', 'did', 'not',
            'its', 'their', 'our', 'your', 'his', 'her',

            // Delivery-platform words (how it's delivered, not what it does)
            'using', 'based', 'web', 'mobile', 'desktop', 'online',
            'electronic', 'digital', 'internet', 'android', 'ios', 'app',
            'application', 'platform', 'portal',
        ], $this->locationNames(), [
            // School/dept noise
            'college', 'department', 'university', 'school', 'cict', 'bsit', 'bscs',
            'institute', 'campus', 'high', 'national', 'junior', 'senior',
            'elementary', 'secondary', 'tertiary', 'academy', 'learning',
            'center', 'centre', 'colegio', 'graduate', 'provincial', 'vocational',
            'technical', 'community',

            // Common filler (not domain content)
            'pre', 'post',
        ]);
    }

    // Specific place/institution names only - a NARROWER list than the full
    // stopWordsList() above (that one also strips generic institution-TYPE
    // words like "university"/"elementary" that can legitimately describe
    // WHO a system is for). This one is reused by redactLocations() to
    // guarantee the AI reasoning step never even sees a location or specific
    // institution name, not just told to disregard one.
    private function locationNames(): array
    {
        return [
            'camarines', 'sur', 'nabua', 'cspc', 'iriga', 'city', 'colleges',
            'polytechnic', 'philippines', 'bicol', 'naga', 'buhi', 'bato',
            'rinconada', 'ocampo', 'polangui', 'albay', 'laguna', 'manila',
        ];
    }

    // Removes specific place/institution names from text before it reaches
    // the AI's own reasoning prompt. The embedding step already had these
    // stripped via removeStopWords(), but judgeOneCandidate() was still
    // handing the model raw text containing them, and just TELLING it to
    // disregard location/institution names in the prompt has proven
    // unreliable - confirmed live: the model named "specific institution -
    // CSPC" as its own stated reason for a match, despite that exact rule
    // being in the prompt it was given. This makes it structurally
    // impossible rather than hoping the model follows an instruction.
    private function redactLocations(?string $text): string
    {
        if (! $text) {
            return '';
        }

        $pattern = '/\b('.implode('|', array_map(fn ($w) => preg_quote($w, '/'), $this->locationNames())).')\b/i';

        return trim(preg_replace('/\s{2,}/', ' ', preg_replace($pattern, '', $text)));
    }

    // ── Literal word-highlighting for the "compare similarities" abstract ──
    // Pulls the pre-proposal's own significant words (title + main topic +
    // AI-extracted keywords) so the frontend can highlight where those exact
    // words appear in a matched archived abstract - a plain, mechanical
    // signal the student can verify with their own eyes, instead of trusting
    // another AI-generated similarities/differences sentence (which kept
    // being wrong - see enrichWithReasons()'s history).
    public function extractHighlightWords(array $concepts): array
    {
        $stopWords = $this->stopWordsList();

        $blob = trim(($concepts['title'] ?? '').' '.($concepts['main_topic'] ?? ''));
        $blob = preg_replace('/[\/\\\,\.\(\)\[\]\{\}:;!?"\'\-_]/', ' ', $blob);
        $words = preg_split('/\s+/', strtolower(trim($blob)), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, fn ($w) => ! in_array($w, $stopWords) && strlen($w) > 2);

        // Keywords are already AI-curated short phrases - keep multi-word
        // ones intact (e.g. "patient records") so the highlight reads as a
        // meaningful unit rather than two separately-lit single words, but
        // drop any keyword that's nothing but filler once split.
        $keywordPhrases = array_filter(array_map(function ($kw) use ($stopWords) {
            $kw = trim((string) $kw);
            if ($kw === '') {
                return null;
            }
            $kwWords = preg_split('/\s+/', strtolower(preg_replace('/[\/\\\,\.\(\)\[\]\{\}:;!?"\']/', ' ', $kw)), -1, PREG_SPLIT_NO_EMPTY);
            $meaningful = array_filter($kwWords, fn ($w) => ! in_array($w, $stopWords) && strlen($w) > 2);

            return $meaningful ? strtolower($kw) : null;
        }, $concepts['keywords'] ?? []));

        $all = array_unique(array_merge(array_values($words), array_values($keywordPhrases)));

        // Longest first so a multi-word phrase gets highlighted as one unit
        // before its own individual words would otherwise match separately.
        usort($all, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_values($all);
    }

    // ── 3. TRUE RAG: embed query → cosine similarity → top N ──────────
    public function findSimilarDocuments(array $concepts, int $topN = 10): array
    {
        // Build a rich text representation — strip stop words before embedding
        $rawQueryText = implode("\n", array_filter([
            $concepts['title'] ?? '',
            $concepts['main_topic'] ?? '',
            $concepts['domain'] ?? '',
            implode(', ', $concepts['keywords'] ?? []),
            $concepts['problem_statement'] ?? '',
            $concepts['proposed_solution'] ?? '',
            $concepts['scope'] ?? '',
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

            if (! is_array($storedEmbedding) || empty($storedEmbedding)) {
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
                'score' => $score,
                'reason' => '',
                'similarities' => '',
                'differences' => '',
                'suggestion' => '',
            ];
        }

        if (empty($scored)) {
            // No strong matches found — return empty rather than noise
            Log::info('No matches above 40% threshold — returning empty result.');

            return [];
        }

        // Sort by score descending, take top candidates for enrichment.
        // Capped at 10 (was 20): 20 candidates each needing their own
        // id/matches/score/reason was producing a response long enough to
        // risk truncation or a slow generation that tripped the timeout.
        // 10 keeps the response reliable.
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $topCandidates = array_slice($scored, 0, min(10, count($scored)));

        // ── Phase 4: Enrich top matches with AI-generated reasons ─────
        $enriched = $this->enrichWithReasons($concepts, $topCandidates);

        return array_slice($enriched, 0, $topN);
    }

    // ── 4. Add human-readable reasons to top vector matches ───────────
    //
    // Judged ONE candidate per Ollama call, not as one big batch - confirmed
    // directly against the live model that batching distorts the verdict:
    // the same real document scored a stable, correct ~25 when asked about
    // alone, but a stable, wrong 70-84 the moment even one or two other
    // candidates rode along in the same request (a real, reproduced
    // comparison/contrast bias - the model starts ranking candidates against
    // EACH OTHER instead of judging each one purely against the
    // pre-proposal). One call per candidate costs more time but is the only
    // version that actually held up under repeat testing. As a side benefit,
    // there's no id to echo back anymore, which also removes a real bug
    // where the model would return the wrong id (or "0") and silently lose
    // its own verdict during matching.
    private function enrichWithReasons(array $concepts, array $topCandidates): array
    {
        $verdicts = $this->judgeCandidates($concepts, $topCandidates);

        foreach ($topCandidates as $position => &$item) {
            $verdict = $verdicts[$position] ?? null;
            $hasVerdict = $verdict !== null;

            $item['reason'] = $verdict['reason']
                ?? 'Semantically similar based on vector embedding analysis.';
            $item['similarities'] = $verdict['similarities'] ?? '';
            $item['differences'] = $verdict['differences'] ?? '';
            $item['suggestion'] = $verdict['suggestion'] ?? '';
            // Default true (rather than silently dropping it to the bottom)
            // if the model omits the field or the call failed - an unknown
            // verdict isn't treated as a rejection there. The SCORE shown is
            // a separate question, handled below: not "should this be
            // buried?" but "is this number trustworthy enough to display?"
            $item['is_relevant'] = $verdict['matches'] ?? true;

            // Whether a "false" verdict's own score is even worth
            // considering: only a HIGH score contradicts a rejection (the
            // original bug this was written for - the model writing a
            // correct rejection reason while attaching a self-contradictory
            // high number, e.g. matches:false + score:86). A LOW-to-moderate
            // score on a "false" verdict isn't contradictory at all - it's
            // the model expressing exactly the "related but not the same"
            // middle ground the prompt's own calibration asks for (e.g.
            // matches:false + score:30 for a same-subject-different-features
            // case). 55 matches the app's own Moderate-Similarity threshold
            // used everywhere else a verdict gets bucketed.
            $rejectionScoreIsContradictory = ! $item['is_relevant']
                && $verdict['score'] !== null
                && $verdict['score'] >= 55;

            if ($hasVerdict && $verdict['score'] !== null && ! $rejectionScoreIsContradictory) {
                // Trust the AI's own calibrated score whenever it gave one
                // and it isn't self-contradictory - true or false, moderate
                // or high. A better read of core-concept overlap than raw
                // embedding math, which reflects shared VOCABULARY -
                // "web-based management system" - almost as much as shared
                // PURPOSE.
                $item['score'] = $verdict['score'];
            } else {
                // No verdict at all, no score in the verdict, or a
                // self-contradictory high score on a rejection - in none of
                // these cases is there a trustworthy AI number, so fall back
                // to the raw embedding score, scaled down as a caution
                // (0.4 for an explicit or contradictory rejection; untouched
                // for a confirmed match that simply gave no number).
                $item['score'] = $hasVerdict && $item['is_relevant']
                    ? $item['score']
                    : (int) round($item['score'] * 0.4);
            }
        }
        unset($item);

        // Now that the AI provides its own calibrated score for most
        // candidates, sort by that directly rather than by the is_relevant
        // boolean - this naturally ranks a rejected-but-still-close match
        // (e.g. another capstone titling system) above one that shares
        // nothing but generic wording (e.g. a smart door lock), instead of
        // just bucketing everything into two tiers.
        usort($topCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $topCandidates;
    }

    // Judges exactly one archived document against the pre-proposal. Both
    // sides get their real substance now, not a title-level summary: the
    // archived side gets its FULL abstract (previously cut to 150 characters
    // - barely past the title-restating opening sentence of a typical
    // abstract, well before any of the actual distinguishing detail), and
    // the pre-proposal side gets its problem statement, proposed solution,
    // and scope (previously just topic/domain/keywords) - comparing actual
    // proposal content against title-and-abstract, not summaries of both.
    private function judgeOneCandidate(array $concepts, ArchivedDocument $doc, int $embeddingScore): ?array
    {
        $prompt = $this->buildJudgePrompt($concepts, $doc, $embeddingScore);

        try {
            return $this->readVerdict($this->ollama->generate($prompt, 700));
        } catch (\Exception $e) {
            Log::warning('judgeOneCandidate failed', ['archived_id' => $doc->archived_id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Judge the candidates worth judging, in one trip.
     *
     * Two things were making a check slow. Every candidate was judged, and
     * each judgement was its own round trip to the AI machine - ten of them,
     * one after another. On a good link that is about two seconds each; on a
     * weak one it is minutes, because the cost is the network, not the model.
     *
     * So: only the candidates near enough to be worth explaining are judged -
     * the rest already fall back to the embedding line, which is what they
     * were showing anyway - and those are asked for together, in a single
     * round trip that runs them in parallel on the far side. Each candidate
     * is still judged on its own, which is what keeps the scoring honest;
     * only the waiting is shared.
     *
     * @return array<int, array|null> keyed by position in $topCandidates
     */
    private function judgeCandidates(array $concepts, array $topCandidates): array
    {
        $limit = (int) config('services.ollama.judge_limit', 4);
        $floor = (int) config('services.ollama.judge_min_score', 40);

        $worthJudging = [];

        foreach ($topCandidates as $position => $item) {
            if (count($worthJudging) >= $limit) {
                break;
            }

            // The top one is always explained, however it scored: a check
            // that says nothing about its own best match is not much of a
            // check.
            if ($position === 0 || $item['score'] >= $floor) {
                $worthJudging[$position] = $this->buildJudgePrompt($concepts, $item['document'], $item['score']);
            }
        }

        if ($worthJudging === []) {
            return [];
        }

        $positions = array_keys($worthJudging);
        $verdicts = [];

        try {
            $answers = $this->ollama->generateMany(array_values($worthJudging), 700);

            foreach ($answers as $i => $raw) {
                $verdicts[$positions[$i]] = $this->readVerdict($raw);
            }
        } catch (\Throwable $e) {
            // One trip failing should not cost every verdict - fall back to
            // asking one at a time, which is what this did before.
            Log::warning('Batched judging failed, falling back one at a time', ['error' => $e->getMessage()]);
        }

        // Anything the batch did not answer for is asked individually, so a
        // partial batch degrades to slower rather than to nothing.
        foreach ($positions as $position) {
            if (! array_key_exists($position, $verdicts)) {
                $verdicts[$position] = $this->judgeOneCandidate(
                    $concepts,
                    $topCandidates[$position]['document'],
                    $topCandidates[$position]['score']
                );
            }
        }

        return $verdicts;
    }

    /** The prompt that asks for one candidate's verdict. */
    private function buildJudgePrompt(array $concepts, ArchivedDocument $doc, int $embeddingScore): string
    {
        $conceptText = implode(', ', $concepts['keywords'] ?? []);

        $prompt = 'You are an academic similarity checker. This archived capstone was flagged as '
                ."potentially similar to the pre-proposal below using vector embeddings.\n\n"
                ."STRICT RULES:\n"
                ."- IGNORE generic words: proposed, computerized, system, management, records, web, web-based, mobile, online, automated, digital\n"
                // No "ignore location names" rule needed here anymore -
                // specific place/institution names (CSPC, Camarines Sur,
                // Nabua, etc.) are redacted out of the text below before
                // this prompt is even built (see redactLocations()), so the
                // model structurally cannot see or use one - a mechanical
                // guarantee instead of an instruction it has been observed
                // to ignore (it once cited "specific institution - CSPC" as
                // its own stated reason for a match, despite this exact
                // rule being in the prompt at the time).
                ."- IGNORE generic institution-TYPE words (they only describe HOW something is organized, not WHAT it does or WHO for): college, department, polytechnic, CICT, BSIT, high school, national high school, elementary school, academy, institute, learning center\n"
                ."- Focus ONLY on: core domain, problem being solved, target users, system purpose\n"
                ."- If they are NOT actually similar in core concept, say so clearly\n"
                ."- Be precise about WHAT KIND of difference you're citing - don't default to the word \"domain\" "
                ."for everything, it sounds vague and technical. Plainer, more accurate words: \"area\" for the "
                ."broad subject (education vs. retail vs. healthcare - two capstone-administration systems are the "
                ."SAME area even if their specific features differ), \"scope\" for what the system actually covers "
                ."or leaves out, \"users\" when who it's built for differs, \"institution\" when it's the same kind "
                ."of system just for a different school. Say whichever one is actually true, not whichever sounds "
                ."most decisive.\n\n"
                ."Decide whether the core concept genuinely matches, give your OWN similarity score from 0 to 100 "
                ."based on core concept overlap (NOT on shared generic wording), and write ONE sentence explaining "
                ."why or why not. Also give a short phrase (under 12 words) on what genuinely overlaps "
                ."(\"similarities\") and what specific features/scope actually differ (\"differences\"), plus ONE "
                ."clear, specific sentence of actionable advice (\"suggestion\") - name the actual feature/angle "
                ."the student should lean into or cut, never a generic \"differentiate your proposal further\".\n"
                ."A score near 100 means essentially the same project idea. A score near 0 means a completely "
                ."different purpose despite superficially similar wording (e.g. both being \"a management "
                ."system\"). Sharing only generic words should score low (under 30); solving the same underlying "
                ."problem with the same specific features should score high (over 65) even if worded differently. "
                ."But same SUBJECT and users with a genuinely different FEATURE SET (not just different wording "
                ."for the same features) is a real middle case - moderate, not high: score in the 40s.\n\n"
                ."WORKED EXAMPLES - the deciding question is always \"a management/tracking/monitoring system "
                ."FOR WHAT, and used by WHO?\", never just \"is it a management system?\":\n"
                ."- \"Student Management System\" vs. \"Employee Management System\": BOTH are \"a management "
                ."system\", but one manages students and the other manages employees - completely different "
                ."subjects and users. Score ~5-10, NOT high.\n"
                ."- \"CapsTrack: Capstone Project Management System\" vs. \"Web-Based Management System for "
                ."Production and Auxiliary Services with Inventory and Sales Tracking\": one manages capstone "
                ."project workflow (advisers, submissions, duplication checking) for students; the other manages "
                ."physical inventory and sales for an office. Sharing the phrase \"web-based management system\" "
                ."is NOT core-concept overlap. Score ~5-10, NOT high.\n"
                ."- \"CapsTrack: Capstone Project Management System\" vs. \"Capstone Project and Thesis Class "
                ."Information Management System\": both manage the SAME subject (capstone/thesis projects) for "
                ."the same kind of users (students and advisers/panelists) - genuine core-concept overlap, not "
                ."just shared wording. But the actual FEATURES differ: one focuses on adviser-matching and AI "
                ."duplication checking, the other on grading/participation rating and honorarium computation - "
                ."real, substantive scope differences, not the same system relabeled. Same subject + different "
                ."features = moderate score, around 40-50, not near-0 and not 80+.\n"
                ."Silently ask yourself \"a management system FOR WHAT?\" for both the pre-proposal and the "
                ."archived document - if the WHAT differs (students vs. inventory, capstones vs. door access, "
                ."records vs. events), the score must be low regardless of how many words they share. Base this "
                ."on what each one actually DOES, per its problem statement/solution/abstract below - not just "
                ."its title.\n\n"
                ."Return ONLY a JSON object. No markdown, no array, no extra text.\n"
                .'{"matches":true|false,"score":<0-100>,"reason":"one sentence about core concept match or mismatch",'
                .'"similarities":"short phrase, under 12 words, on what genuinely overlaps - or that there is none '
                .'beyond generic wording","differences":"short phrase, under 12 words, on what specific features/scope '
                .'actually differ","suggestion":"one clear, specific sentence of actionable advice"}'
                ."\n\nPRE-PROPOSAL:"
                ."\nTitle: ".$this->redactLocations($concepts['title'] ?? '')
                ."\nTopic: ".$this->redactLocations($concepts['main_topic'] ?? '')
                ."\nDomain: {$concepts['domain']}"
                ."\nKeywords: {$conceptText}"
                ."\nProblem Statement: ".$this->redactLocations(mb_substr($concepts['problem_statement'] ?? '', 0, 600))
                ."\nProposed Solution: ".$this->redactLocations(mb_substr($concepts['proposed_solution'] ?? '', 0, 600))
                ."\nScope: ".$this->redactLocations(mb_substr($concepts['scope'] ?? '', 0, 400))
                ."\n\nARCHIVED DOCUMENT:"
                ."\nTitle: ".$this->redactLocations(mb_substr($doc->title, 0, 150))
                ."\nAbstract: ".$this->redactLocations(mb_substr($doc->abstract ?? '', 0, 1200));

        return $prompt;
    }

    /**
     * Read one verdict out of whatever the model wrote.
     *
     * Split out so a batched answer and a single one are read the same way -
     * there is no second parser to drift from this one.
     */
    private function readVerdict(string $raw): ?array
    {
        $cleaned = trim(preg_replace('/```json|```/i', '', $raw));

        preg_match('/\{.*\}/s', $cleaned, $matches);
        $r = json_decode($matches[0] ?? $cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($r)) {
            return null;
        }

        $aiScore = isset($r['score']) && is_numeric($r['score'])
            ? max(0, min(100, (int) round($r['score'])))
            : null;

        return [
            'reason' => $r['reason'] ?? '',
            'similarities' => $r['similarities'] ?? '',
            'differences' => $r['differences'] ?? '',
            'suggestion' => $r['suggestion'] ?? '',
            'matches' => array_key_exists('matches', $r) ? (bool) $r['matches'] : true,
            'score' => $aiScore,
        ];
    }

    // ── 5. Cosine similarity between two float vectors ─────────────────
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

    // ── 6. Legacy keyword + Ollama scoring ─────────────────
    public function findSimilarDocumentsLegacy(array $concepts, int $topN = 10): array
    {
        $conceptText = implode(', ', $concepts['keywords'] ?? []);

        $expandPrompt = 'Given this capstone pre-proposal topic, list 15 related search terms '
                    .'that could appear in similar capstone titles. Include synonyms, related domains, '
                    ."and common variations. Return ONLY a JSON array of strings. No markdown.\n\n"
                    ."Topic: {$concepts['main_topic']}\n"
                    ."Domain: {$concepts['domain']}\n"
                    ."Keywords: {$conceptText}\n\n"
                    .'["term1","term2",...]';

        try {
            $raw = $this->ollama->generate($expandPrompt, 600);
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
        $query->where(function ($q) use ($allTerms, $concepts) {
            foreach ($allTerms as $term) {
                if (strlen(trim($term)) < 3) {
                    continue;
                }
                $q->orWhere('title', 'LIKE', '%'.$term.'%');
                $q->orWhere('abstract', 'LIKE', '%'.$term.'%');
            }
            foreach (explode(' ', $concepts['domain']) as $word) {
                if (strlen($word) > 3) {
                    $q->orWhere('title', 'LIKE', '%'.$word.'%');
                }
            }
        });

        $filtered = $query->limit(80)->get();

        if ($filtered->count() < 20) {
            $filteredIds = $filtered->pluck('archived_id')->toArray();
            $extras = ArchivedDocument::whereNotIn('archived_id', $filteredIds)
                ->orderBy('academic_year', 'desc')
                ->limit(20 - $filtered->count())
                ->get();
            $filtered = $filtered->merge($extras);
        }

        $allScores = [];
        $chunks = $filtered->chunk(10);

        foreach ($chunks as $chunkIndex => $chunk) {
            $docList = $chunk->map(fn ($doc) => [
                'id' => $doc->archived_id,
                'title' => mb_substr($doc->title, 0, 100),
                'abs' => mb_substr($doc->abstract ?? '', 0, 150),
            ])->values()->toArray();

            $docsJson = json_encode($docList);

            // FIX: Better prompt with strict rules to ignore generic noise words
            $prompt = 'You are an academic similarity checker. Compare this capstone pre-proposal '
                    ."against the archived capstones below.\n\n"
                    ."STRICT RULES:\n"
                    ."- IGNORE generic words: proposed, computerized, system, management, records, web, web-based, mobile, online, automated, digital\n"
                    ."- IGNORE location names: Camarines Sur, Nabua, CSPC, Iriga, Philippines\n"
                    ."- IGNORE school/dept names and specific institution names: college, department, polytechnic, CICT, BSIT, high school, national high school, elementary school, academy, institute, learning center\n"
                    ."- Focus ONLY on: core domain, problem being solved, target users, and system purpose\n"
                    ."- Score 70-100 ONLY if the core domain AND purpose are the same\n"
                    ."- Score 40-69 if the domain matches but purpose differs\n"
                    ."- Score 0-39 if they are fundamentally different topics\n\n"
                    ."Return ONLY a JSON array. No markdown.\n"
                    .'[{"id":<id>,"score":<0-100>,"reason":"one sentence focusing on core concept match or mismatch"}]'
                    ."\n\nPRE-PROPOSAL:"
                    ."\nTopic: {$concepts['main_topic']}"
                    ."\nDomain: {$concepts['domain']}"
                    ."\nKeywords: {$conceptText}"
                    ."\nProblem: {$concepts['problem_statement']}"
                    ."\nSolution: {$concepts['proposed_solution']}"
                    ."\n\nARCHIVED CAPSTONES:\n{$docsJson}";

            try {
                // Chunks of 10 docs, each needing id/score/reason — same
                // truncation risk as enrichWithReasons().
                $raw = $this->ollama->generate($prompt, 1200);
                $cleaned = trim(preg_replace('/```json|```/i', '', $raw));

                preg_match('/\[.*\]/s', $cleaned, $matches);
                $jsonStr = $matches[0] ?? $cleaned;
                $scores = json_decode($jsonStr, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($scores)) {
                    // FIX: Only keep scores >= 40 from legacy path too
                    $scores = array_filter($scores, fn ($s) => ($s['score'] ?? 0) >= 40);
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

        // Sort descending and take top N
        usort($allScores, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        $topScores = array_slice($allScores, 0, $topN);

        // Map back to document models
        $docMap = $filtered->keyBy('archived_id');
        $results = [];

        foreach ($topScores as $item) {
            $doc = $docMap->get($item['id']);
            if (! $doc) {
                continue;
            }

            $results[] = [
                'document' => $doc,
                'score' => (int) ($item['score'] ?? 0),
                'reason' => $item['reason'] ?? '',
                'matched_on' => $item['matched_on'] ?? [],
            ];
        }

        return $results;
    }

    // ── 7. Suggest instructors ────────────────────────────
    public function suggestInstructors(array $concepts): array
    {
        // Only experts a team can actually engage: approved, and holding at
        // least one student-facing role. Suggesting an Admin or a CAPSA-only
        // account gives the student a name they cannot apply to.
        $experts = \DB::table('capstone_experts as e')
            ->leftJoin('expert_expertise as ex', 'e.expert_id', '=', 'ex.expert_id')
            ->where('e.approved_status', 1)
            ->whereExists(function ($q) {
                $q->select(\DB::raw(1))
                    ->from('expert_roles as er')
                    ->join('roles as r', 'r.role_id', '=', 'er.role_id')
                    ->whereColumn('er.expert_id', 'e.expert_id')
                    ->whereIn('r.role_name', \App\Models\CapstoneExpert::STUDENT_FACING_ROLES);
            })
            ->select(
                'e.expert_id',
                'e.first_name',
                'e.last_name',
                'e.credentials',
                'e.program',
                \DB::raw('GROUP_CONCAT(ex.expertise_field SEPARATOR ", ") as expertise')
            )
            ->groupBy('e.expert_id', 'e.first_name', 'e.last_name', 'e.credentials', 'e.program')
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
            $raw = $this->ollama->generate($prompt, 800);
            $cleaned = trim(preg_replace('/```json|```/i', '', $raw));

            preg_match('/\[.*\]/s', $cleaned, $matches);
            $jsonStr = $matches[0] ?? $cleaned;
            $suggests = json_decode($jsonStr, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($suggests)) {
                Log::error('suggestInstructors parse error', ['raw' => $raw]);
                $suggests = [];
            }

            $results = [];
            foreach ($suggests as $s) {
                $id = (int) ($s['id'] ?? 0);
                if (! in_array($id, $validIds)) {
                    continue;
                }

                $expert = $expertMap->get($id);
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
