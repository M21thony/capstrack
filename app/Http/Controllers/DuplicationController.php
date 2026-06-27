<?php

namespace App\Http\Controllers;

use App\Services\SimilarityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

        $path = null;

        try {
            // 1. Save uploaded PDF temporarily
            $path = $request->file('document')->store('temp-uploads');
            $fullPath = Storage::path($path);

            // 2. Extract text from PDF
            $pdfText = $this->service->extractPdfText($fullPath);

            if (empty(trim($pdfText))) {
                throw new \Exception('Could not extract text from this PDF. Make sure it is not a scanned image without a text layer.');
            }

            // 3. Extract key concepts via Ollama
            $concepts = $this->service->extractKeyConcepts($pdfText);

            // 4. Find top 10 semantically similar archived capstones
            $matches = $this->service->findSimilarDocuments($concepts, 10);

            // 5. Suggest instructors based on semantic expertise match
            $instructors = $this->service->suggestInstructors($concepts);

            // 6. Clean up temp file
            Storage::delete($path);

            // 7. Determine overall verdict from the top match
            $topScore = $matches[0]['score'] ?? 0;
            $verdict = $topScore >= 70 ? 'High Similarity'
                      : ($topScore >= 40 ? 'Moderate Similarity' : 'Passed');

            return response()->json([
                'success' => true,
                'concepts' => $concepts,
                'top_score' => $topScore,
                'verdict' => $verdict,
                'matches' => array_map(fn ($m) => [
                    'archived_id' => $m['document']->archived_id,
                    'title' => $m['document']->title,
                    'abstract' => $m['document']->abstract,
                    'academic_year' => $m['document']->academic_year,
                    'program' => $m['document']->program,
                    'score' => $m['score'],
                    'reason' => $m['reason'],
                    'matched_on' => $m['matched_on'] ?? [],
                ], $matches),
                'instructors' => $instructors,
            ]);

        } catch (\Exception $e) {
            // Clean up temp file even on failure
            if ($path) {
                Storage::delete($path);
            }

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
