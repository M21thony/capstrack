<?php

namespace App\Http\Controllers;

use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DuplicationController extends Controller
{
    public function __construct(private SimilarityService $service) {}

    public function index()
    {
        return view('student.duplication');
    }

    public function check(Request $request)
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf|max:20480',
        ]);

        // This chains warmup + up to ~4 more Ollama calls (concept
        // extraction, similarity search, instructor matching). PHP's
        // default max_execution_time (120s on this server) was never
        // raised for it, so a cold model load (~50s alone - confirmed from
        // the Ollama server log) plus several real calls routinely blew
        // past that and fataled uncatchably mid-request. 300s covers one
        // cold-start plus the rest of the chain running warm - matches
        // OllamaSSHClient's own curl --max-time, which must move together
        // with this or PHP's own limit fatals the request first regardless.
        set_time_limit(300);

        try {
            // 0. Warm up Ollama — loads model into GPU before real requests
            app(OllamaSSHClient::class)->warmup();

            // 1. Read the uploaded PDF where PHP left it - nothing is kept,
            // and file storage may not be a disk on this server at all
            $fullPath = $request->file('document')->getRealPath();

            // 2. Extract text from PDF
            $pdfText = $this->service->extractPdfText($fullPath);

            if (empty(trim($pdfText))) {
                throw new \Exception('Could not extract text from this PDF. Make sure it is not a scanned image without a text layer.');
            }

            // 2.5. Reject anything that isn't actually a pre-proposal before
            // spending the rest of the pipeline (concept extraction, vector
            // search, instructor matching) on a document that was never
            // going to produce a meaningful result.
            $gate = $this->service->isPreProposal($pdfText);

            if (! ($gate['is_pre_proposal'] ?? true)) {
                $reason = $gate['reason'] ?? 'it does not match the structure of a capstone pre-proposal';
                throw new \Exception("This doesn't look like a pre-proposal document ({$reason}). Please upload your capstone pre-proposal.");
            }

            // 3. Extract key concepts via Ollama
            $concepts = $this->service->extractKeyConcepts($pdfText);

            // 4. Find top 10 semantically similar archived capstones
            $matches = $this->service->findSimilarDocuments($concepts, 10, null, $pdfText);

            // 5. Suggest instructors based on semantic expertise match
            $instructors = $this->service->suggestInstructors($concepts);

            // 7. Determine overall verdict from top match score
            // Only a judged match may set the verdict. An unassessed one
            // carries a demoted embedding number, which is an ordering
            // device, not a similarity.
            $topScore = collect($matches)
                ->first(fn ($m) => $m['assessed'] ?? true)['score'] ?? 0;
            $verdict = $topScore >= 65 ? 'High Similarity'
                    : ($topScore >= 55 ? 'Moderate Similarity' : 'Passed');

            return response()->json([
                'success' => true,
                'concepts' => $concepts,
                'top_score' => $topScore,
                'verdict' => $verdict,
                'matches' => array_map(fn ($m) => [
                    'archived_id' => $m['document']->archived_id,
                    'title' => $m['document']->title,
                    'abstract' => $m['document']->abstract,
                    'authors' => $m['document']->authors,
                    'academic_year' => $m['document']->academic_year,
                    'program' => $m['document']->program,
                    'score' => $m['score'],
                    'reason' => $m['reason'],
                    'similarities' => $m['similarities'] ?? '',
                    'differences' => $m['differences'] ?? '',
                    'suggestion' => $m['suggestion'] ?? '',
                    'is_relevant' => $m['is_relevant'] ?? true,
                    'assessed' => $m['assessed'] ?? true,
                    'matched_on' => $m['matched_on'] ?? [],
                ], $matches),
                'instructors' => $instructors,
            ]);

        } catch (\Throwable $e) {
            // \Throwable, not \Exception - a slow/dead SSH tunnel to Ollama
            // can hit PHP's own max_execution_time and fatal with a
            // Symfony FatalError, which extends \Error, not \Exception.
            // Catching only \Exception let that escape uncaught straight to
            // Laravel's HTML error page, which the frontend can't parse as
            // JSON and reports as a generic "Server returned an error page".
            Log::error('Duplication check failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
