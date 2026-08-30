<?php

namespace App\Http\Controllers;

use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PreProposalController extends Controller
{
    public function __construct(private SimilarityService $service) {}

    public function edit(Request $request): View|RedirectResponse
    {
        $team = $this->currentTeam($request);

        if (! $team) {
            return redirect()->route('student.team')
                ->with('error', 'Join or create a team before submitting a Pre-Proposal.');
        }

        $proposals = $team->preProposals()->with('duplicationCheck')->get()->keyBy('slot');

        return view('student.duplication', [
            'team' => $team->load('members', 'leader', 'selectedPreProposal'),
            'proposals' => $proposals,
            'approvedProposals' => $proposals->filter(fn ($p) => $p->approved_status),
            'canSubmitMore' => $this->findAvailableSlot($proposals) !== null,
            'isLeader' => (int) $team->leader_id === (int) $request->user()->student_id,
        ]);
    }

    // AJAX: upload a PDF, run the same check DuplicationController::check()
    // runs, and return the extracted concepts + report so the page can fill
    // the pre-proposal fields in place - no navigation, no retyping. Which
    // slot this becomes is only decided later, at store() time.
    public function runCheck(Request $request): JsonResponse
    {
        $team = $this->currentTeam($request);

        if (! $team) {
            return response()->json(['success' => false, 'message' => 'Join or create a team first.'], 422);
        }

        $request->validate([
            'document' => 'required|file|mimes:pdf|max:20480',
        ]);

        $path = null;
        // Matches OllamaSSHClient's own curl --max-time (300s) - these two
        // must move together, since PHP's own limit would otherwise fatal
        // the request before curl's timeout ever gets a chance to matter.
        set_time_limit(300);

        try {
            app(OllamaSSHClient::class)->warmup();

            $path = $request->file('document')->store('temp-uploads');
            $fullPath = Storage::path($path);

            $pdfText = $this->service->extractPdfText($fullPath);

            if (empty(trim($pdfText))) {
                throw new \Exception('Could not extract text from this PDF. Make sure it is not a scanned image without a text layer.');
            }

            $gate = $this->service->isPreProposal($pdfText);

            if (! ($gate['is_pre_proposal'] ?? true)) {
                $reason = $gate['reason'] ?? 'it does not match the structure of a capstone pre-proposal';
                throw new \Exception("This doesn't look like a pre-proposal document ({$reason}). Please upload your capstone pre-proposal.");
            }

            $concepts = $this->service->extractKeyConcepts($pdfText);
            $matches = $this->service->findSimilarDocuments($concepts, 10);
            $instructors = $this->service->suggestInstructors($concepts);

            $topScore = $matches[0]['score'] ?? 0;
            $verdict = $topScore >= 65 ? 'High Similarity'
                    : ($topScore >= 55 ? 'Moderate Similarity' : 'Passed');

            $formattedMatches = array_map(fn ($m) => [
                'archived_id' => $m['document']->archived_id,
                'title' => $m['document']->title,
                'abstract' => $m['document']->abstract,
                'academic_year' => $m['document']->academic_year,
                'program' => $m['document']->program,
                'score' => $m['score'],
                'reason' => $m['reason'],
                'similarities' => $m['similarities'] ?? '',
                'differences' => $m['differences'] ?? '',
                'suggestion' => $m['suggestion'] ?? '',
                'is_relevant' => $m['is_relevant'] ?? true,
                'matched_on' => $m['matched_on'] ?? [],
            ], $matches);

            $studentSy = StudentSchoolYear::where('student_id', $request->user()->student_id)->first();
            $permanentPath = 'duplication-checks/'.basename($path);
            Storage::move($path, $permanentPath);

            $check = DuplicationCheck::create([
                'student_s_y_id' => $studentSy->s_y_id,
                'document_path' => $permanentPath,
                'concepts' => $concepts,
                'top_score' => $topScore,
                'verdict' => $verdict,
                'matches' => $formattedMatches,
                'instructors_suggested' => $instructors,
            ]);

            return response()->json([
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
                'highlight_words' => $this->service->extractHighlightWords($concepts),
            ]);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::delete($path);
            }

            Log::error('Pre-proposal duplication check failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        if (! $team) {
            return redirect()->route('student.team')
                ->with('error', 'Join or create a team before submitting a Pre-Proposal.');
        }

        $slot = $this->nextAvailableSlot($team);

        if (! $slot) {
            return redirect()->route('student.duplication')
                ->with('error', 'You already have 3 submitted proposals awaiting review.');
        }

        $validated = $request->validate([
            'title' => [
                'required', 'string', 'max:150',
                function ($attr, $value, $fail) {
                    $count = $this->countMeaningfulWords($value ?? '');

                    if ($count < 11 || $count > 12) {
                        $fail("Title must contain 11–12 meaningful words (filler words like 'a', 'of', 'in' don't count). Current count: {$count}.");
                    }
                },
            ],
            'sdg_goals' => ['required', 'string', 'max:150'],
            'research_agenda' => ['required', 'array', 'min:1'],
            'research_agenda.*' => ['string', 'max:150'],
            'problem_statement' => ['required', 'string'],
            'proposed_solution' => ['required', 'string'],
            'scope' => ['required', 'string'],
            'limitations' => ['required', 'string'],
            'sdm' => ['required', 'string', 'max:100'],
            'duplication_check_id' => ['required', 'integer'],
        ]);

        $check = DuplicationCheck::whereHas(
            'studentSchoolYear',
            fn ($q) => $q->where('team_id', $team->team_id)
        )->find($validated['duplication_check_id']);

        if (! $check) {
            return redirect()->route('student.duplication')
                ->with('error', 'Run the duplication check for this proposal first.');
        }

        $duplicationStatus = $check->verdict === 'Passed' ? 'free' : 'contains_duplication';

        CapstonePreProposal::updateOrCreate(
            ['team_id' => $team->team_id, 'slot' => $slot],
            [
                'duplication_check_id' => $check->id,
                'title' => $validated['title'],
                'sdg_goals' => $validated['sdg_goals'],
                'research_agenda' => $validated['research_agenda'],
                'problem_statement' => $validated['problem_statement'],
                'proposed_solution' => $validated['proposed_solution'],
                'scope' => $validated['scope'],
                'limitations' => $validated['limitations'],
                'sdm' => $validated['sdm'],
                'duplication_status' => $duplicationStatus,
                'approved_status' => false,
                'rejection_reason' => null,
                'submitted_at' => now(),
            ]
        );

        // Only once all 3 slots are in does this team become ready for CAPSA
        // review - matches "required to submit 3 proposals per team."
        if (! $team->is_confirmed && $team->preProposals()->count() >= 3) {
            $team->update(['team_status' => 'complete']);
        }

        return redirect()->route('student.duplication')
            ->with('status', 'Pre-proposal saved.');
    }

    public function choose(Request $request, CapstonePreProposal $preProposal): RedirectResponse
    {
        $team = $this->currentTeam($request);

        if (! $team || $preProposal->team_id !== $team->team_id) {
            abort(403);
        }

        if ((int) $team->leader_id !== (int) $request->user()->student_id) {
            return redirect()->route('student.duplication')
                ->with('error', 'Only the team leader can choose the final title.');
        }

        if ($team->selected_pre_proposal_id) {
            return redirect()->route('student.duplication')
                ->with('error', 'A final title has already been chosen.');
        }

        if (! $preProposal->approved_status) {
            return redirect()->route('student.duplication')
                ->with('error', 'You can only choose a title the CAPSA has approved.');
        }

        $team->update([
            'selected_pre_proposal_id' => $preProposal->id,
            'project_title' => $preProposal->title,
        ]);

        return redirect()->route('student.duplication')
            ->with('status', 'Final title chosen.');
    }

    // Lets a team drop a pending or rejected proposal, freeing its slot back
    // up for a fresh check + resubmission - an approved one is locked and
    // can't be removed.
    public function destroy(Request $request, CapstonePreProposal $preProposal): RedirectResponse
    {
        $team = $this->currentTeam($request);

        if (! $team || $preProposal->team_id !== $team->team_id) {
            abort(403);
        }

        if ($preProposal->approved_status) {
            return redirect()->route('student.duplication')
                ->with('error', 'An approved proposal cannot be removed.');
        }

        $preProposal->delete();

        if (! $team->is_confirmed && $team->team_status === 'complete' && $team->preProposals()->count() < 3) {
            $team->update(['team_status' => 'on-going']);
        }

        return redirect()->route('student.duplication')
            ->with('status', 'Proposal removed.');
    }

    // Slots are an internal bookkeeping detail, not something the student
    // picks - a new save fills the first empty slot, or replaces the first
    // rejected one if all 3 are already taken. Returns null once all 3 are
    // pending/approved (the team already has its 3 required proposals in).
    private function nextAvailableSlot(Team $team): ?int
    {
        return $this->findAvailableSlot($team->preProposals()->get()->keyBy('slot'));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CapstonePreProposal>  $existing
     */
    private function findAvailableSlot($existing): ?int
    {
        for ($s = 1; $s <= 3; $s++) {
            if (! isset($existing[$s])) {
                return $s;
            }
        }

        foreach ($existing as $s => $proposal) {
            if (! $proposal->approved_status && $proposal->rejection_reason) {
                return (int) $s;
            }
        }

        return null;
    }

    private function currentTeam(Request $request): ?Team
    {
        $studentSy = StudentSchoolYear::where('student_id', $request->user()->student_id)->first();

        return $studentSy?->team_id ? Team::find($studentSy->team_id) : null;
    }
}
