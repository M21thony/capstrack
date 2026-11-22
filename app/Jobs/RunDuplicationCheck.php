<?php

namespace App\Jobs;

use App\Models\DuplicationCheck;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * One student's duplication check, run by the queue instead of inside the
 * web request.
 *
 * On a server without a GPU a check takes minutes, far longer than a request
 * can be held open, and every student checking at once ran at the same time
 * and timed out together. The "checks" queue has a worker of its own, so the
 * checks run one after another and mail and pushes are not stuck behind them.
 *
 * Where it stands is kept in the cache under the student (key()), which is
 * what the page asks every few seconds. A newer check or Cancel replaces or
 * removes that entry, and a job that is no longer the current one throws its
 * work away rather than saving a result nobody is waiting for.
 */
class RunDuplicationCheck implements ShouldQueue
{
    use Queueable;

    // ponytail: one whole check on a CPU-only server; raise it with OLLAMA_TIMEOUT if a check ever runs longer.
    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public int $studentId,
        public int $studentSyId,
        public ?int $teamId,
        public string $path,
        public string $token,
    ) {
        $this->onQueue('checks');
    }

    public static function key(int $studentId): string
    {
        return "duplication-check:{$studentId}";
    }

    public function handle(SimilarityService $service, OllamaSSHClient $ollama): void
    {
        // Cancelled, or replaced by a newer upload, while it waited its turn.
        if (! $this->isCurrent()) {
            Storage::delete($this->path);

            return;
        }

        // The upload may live on S3, and the PDF reader needs a file on disk.
        $pdf = tempnam(sys_get_temp_dir(), 'dup');

        try {
            file_put_contents($pdf, Storage::get($this->path));

            $ollama->warmup();

            $pdfText = $service->extractPdfText($pdf);

            if (empty(trim($pdfText))) {
                throw new \Exception('Could not extract text from this PDF. Make sure it is not a scanned image without a text layer.');
            }

            $gate = $service->isPreProposal($pdfText);

            if (! ($gate['is_pre_proposal'] ?? true)) {
                $reason = $gate['reason'] ?? 'it does not match the structure of a capstone pre-proposal';
                throw new \Exception("This doesn't look like a pre-proposal document ({$reason}). Please upload your capstone pre-proposal.");
            }

            $concepts = $service->extractKeyConcepts($pdfText);
            $matches = $service->findSimilarDocuments($concepts, 10, $this->teamId, $pdfText);
            $instructors = $service->suggestInstructors($concepts);

            // Only a judged match may set the verdict. An unassessed one
            // carries a demoted embedding number, which is an ordering
            // device, not a similarity.
            $topScore = collect($matches)
                ->first(fn ($m) => $m['assessed'] ?? true)['score'] ?? 0;
            $verdict = $topScore >= 65 ? 'High Similarity'
                    : ($topScore >= 55 ? 'Moderate Similarity' : 'Passed');

            $formattedMatches = array_map(fn ($m) => [
                'archived_id' => $m['document']->archived_id,
                'title' => $m['document']->title,
                'abstract' => $m['document']->abstract,
                // Who wrote it. A team told their topic is close to something
                // already done asks whose work it was first, and the report
                // named a year and a programme and stopped there.
                'authors' => $m['document']->authors,
                'academic_year' => $m['document']->academic_year,
                'program' => $m['document']->program,
                // Set when the match is another team's title recommended this year, not an archive study.
                'recommended' => $m['recommended'] ?? null,
                'pre_proposal_id' => $m['pre_proposal_id'] ?? null,
                'score' => $m['score'],
                // The numbers the score is worked out from, for the match page.
                'embedding_score' => $m['embedding_score'] ?? null,
                'judge_score' => $m['judge_score'] ?? null,
                // The judge's three answers that number is worked out from.
                'answers' => $m['answers'] ?? null,
                'exact_score' => $m['exact_score'] ?? null,
                'reason' => $m['reason'],
                'similarities' => $m['similarities'] ?? '',
                'differences' => $m['differences'] ?? '',
                'suggestion' => $m['suggestion'] ?? '',
                'is_relevant' => $m['is_relevant'] ?? true,
                'assessed' => $m['assessed'] ?? true,
                'matched_on' => $m['matched_on'] ?? [],
            ], $matches);

            // Cancelled while it ran.
            if (! $this->isCurrent()) {
                Storage::delete($this->path);

                return;
            }

            $check = DuplicationCheck::create([
                'student_s_y_id' => $this->studentSyId,
                'document_path' => $this->path,
                'concepts' => $concepts,
                'top_score' => $topScore,
                'verdict' => $verdict,
                'matches' => $formattedMatches,
                'instructors_suggested' => $instructors,
            ]);

            $this->finish(200, [
                'success' => true,
                'duplication_check_id' => $check->id,
                'concepts' => $concepts,
                'top_score' => $topScore,
                'verdict' => $verdict,
                'matches' => $formattedMatches,
                'instructors' => $instructors,
                // Words from the pre-proposal itself (title/topic/keywords)
                // worth highlighting inside a matched abstract - a literal,
                // student-verifiable signal instead of another AI sentence.
                'highlight_words' => $service->extractHighlightWords($concepts),
            ]);
        } catch (\Throwable $e) {
            Log::error('Pre-proposal duplication check failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Storage::delete($this->path);

            // A student was being shown the raw failure - "Cannot connect to
            // 127.0.0.1:22. Error 10061..." - which tells them nothing and
            // reads like they broke something. The checker being unreachable
            // is not their problem and not their mistake, so say that, and
            // answer 503 rather than 500: nothing here is wrong, the machine
            // that does the checking is simply not answering.
            if ($this->checkerIsUnreachable($e)) {
                $this->finish(503, [
                    'success' => false,
                    'message' => 'The similarity checker is not responding right now. '
                        .'Nothing you uploaded was lost - try again in a few minutes, '
                        .'and tell your CaPSA/TSA if it keeps happening.',
                ]);
            } else {
                $this->finish(500, ['success' => false, 'message' => $e->getMessage()]);
            }
        } finally {
            @unlink($pdf);
        }
    }

    /** Ran past $timeout, or the worker died under it. */
    public function failed(?\Throwable $e): void
    {
        Storage::delete($this->path);

        $this->finish(503, [
            'success' => false,
            'message' => 'The check took too long and was stopped. Nothing you uploaded was lost - '
                .'try again, and tell your CaPSA/TSA if it keeps happening.',
        ]);
    }

    private function isCurrent(): bool
    {
        return (Cache::get(self::key($this->studentId))['token'] ?? null) === $this->token;
    }

    /** The answer the page is waiting for, kept until it asks. */
    private function finish(int $code, array $body): void
    {
        $state = Cache::get(self::key($this->studentId));

        if (($state['token'] ?? null) !== $this->token) {
            return;
        }

        Cache::put(self::key($this->studentId), ['status' => 'finished', 'code' => $code, 'body' => $body] + $state, now()->addDay());
    }

    /**
     * Whether a failed check means the machine that does the checking is not
     * answering, rather than something about the document.
     *
     * The messages worth keeping are the ones thrown deliberately above - a
     * PDF with no text layer, a document that is not a pre-proposal. Those
     * tell a student what to do. Everything below is the SSH tunnel or Ollama
     * being unavailable, and none of it means anything to them.
     */
    private function checkerIsUnreachable(\Throwable $e): bool
    {
        $signatures = [
            'Cannot connect to',
            'Connection refused',
            'Empty response from Ollama',
            'Failed to decode Ollama JSON',
            'Failed to get embedding',
            'Unable to connect',
            'SSH',
            'cURL',
            'timed out',
            'Operation timed out',
        ];

        foreach ($signatures as $signature) {
            if (stripos($e->getMessage(), $signature) !== false) {
                return true;
            }
        }

        return false;
    }
}
