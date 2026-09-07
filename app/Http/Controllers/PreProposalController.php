<?php

namespace App\Http\Controllers;

use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\OllamaSSHClient;
use App\Services\SimilarityService;
use App\Support\StudentTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PreProposalController extends Controller
{
    /**
     * How many titles a team is advised to turn in, not how many they must.
     *
     * It used to be a floor: fewer than this and the submit button was dead.
     * A team who genuinely had two titles they believed in was stuck writing
     * two they did not, which is worse for the CaPSA reading them than two
     * good ones. It is a recommendation now, made once at the point of
     * submitting, and they can go ahead with one.
     */
    public const RECOMMENDED_PROPOSALS = 4;

    public function __construct(private SimilarityService $service) {}

    public function edit(Request $request): View
    {
        $team = $this->currentTeam($request);

        // A student with no team can read this page. It used to bounce them to
        // Team Management, which - since nothing on that page said why - was
        // indistinguishable from a broken link. Nothing here can be saved
        // without a team, and the page says so instead.
        $proposals = $team
            ? $team->preProposals()->with('duplicationCheck')->get()->keyBy('slot')
            : collect();

        return view('student.duplication', [
            'team' => $team?->load('members', 'leader', 'selectedPreProposal'),
            'proposals' => $proposals,
            'approvedProposals' => $proposals->filter(fn ($p) => $p->approved_status),
            // Saving a pre-proposal keeps it as a draft now; there is always
            // room for another until the set is turned in.
            'canSubmitMore' => (bool) $team && ! $this->isTurnedIn($team),
            'isLeader' => (bool) $team && (int) $team->leader_id === (int) $request->user()->student_id,
            'turnedIn' => (bool) $team && $this->isTurnedIn($team),
            'turnedInAt' => $proposals->whereNotNull('submitted_at')->min('submitted_at'),
            'draftCount' => $proposals->whereNull('submitted_at')->count(),
            'canTurnIn' => (bool) $team
                && $proposals->count() >= 1
                && ! $this->isTurnedIn($team),
            'recommendedProposals' => self::RECOMMENDED_PROPOSALS,
            // The most recent check this team ran that has not been turned
            // into a saved pre-proposal yet.
            //
            // runCheck() writes the result to the database before it answers,
            // so it has always survived - it simply was not drawn again, and
            // every reload therefore looked like the scan had been thrown
            // away. Unsubmitting reloads. So does a rejected form. So does
            // pressing back.
            'lastCheck' => $team
                ? DuplicationCheck::whereIn(
                    'student_s_y_id',
                    StudentSchoolYear::where('team_id', $team->team_id)->pluck('s_y_id')
                )
                    ->whereNotIn('id', $proposals->pluck('duplication_check_id')->filter())
                    ->latest('id')
                    ->first()
                : null,
            'reviewStarted' => (bool) $team && $this->reviewStarted($team),
        ]);
    }

    // AJAX: upload a PDF, run the same check DuplicationController::check()
    // runs, and return the extracted concepts + report so the page can fill
    // the pre-proposal fields in place - no navigation, no retyping. Which
    // slot this becomes is only decided later, at store() time.
    public function runCheck(Request $request): JsonResponse
    {
        // No team needed to run a check. The result is recorded against the
        // student's own school-year row, not the team, so the gate that used
        // to be here was asking for something the work never used - and a
        // student deciding on a title has every reason to check it before
        // they have a team. Saving and submitting still require one.
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

            if (! $studentSy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your enrolment record is missing, so the result cannot be saved. Tell your CaPSA.',
                ], 422);
            }

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

            // A student was being shown the raw failure - "Cannot connect to
            // 127.0.0.1:22. Error 10061..." - which tells them nothing and
            // reads like they broke something. The checker being unreachable
            // is not their problem and not their mistake, so say that, and
            // answer 503 rather than 500: nothing here is wrong, the machine
            // that does the checking is simply not answering.
            if ($this->checkerIsUnreachable($e)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The similarity checker is not responding right now. '
                        .'Nothing you uploaded was lost - try again in a few minutes, '
                        .'and tell your CaPSA if it keeps happening.',
                ], 503);
            }

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
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

    /**
     * DELETE /pre-proposal/duplication-checks/{check} — throw a scan away.
     *
     * The result is drawn again on every load now, which is what stops a
     * reload, a rejected form or the back button costing a team the check
     * they just waited on. The other half of that is a way to be rid of it:
     * a panel that cannot be dismissed is its own kind of stuck.
     *
     * Only a scan that has not become a pre-proposal. Once it has, the check
     * is the evidence behind a saved title and belongs to the CaPSA reviewing
     * it as much as to the team.
     */
    public function destroyCheck(Request $request, DuplicationCheck $check): JsonResponse
    {
        $team = $this->currentTeam($request);

        $mine = $team && StudentSchoolYear::where('team_id', $team->team_id)
            ->where('s_y_id', $check->student_s_y_id)
            ->exists();

        if (! $mine) {
            return response()->json([
                'success' => false,
                'message' => 'That check does not belong to your team.',
            ], 403);
        }

        if (CapstonePreProposal::where('duplication_check_id', $check->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This check belongs to a saved pre-proposal. Remove the pre-proposal instead.',
            ], 422);
        }

        if ($check->document_path) {
            Storage::delete($check->document_path);
        }

        $check->delete();

        return response()->json(['success' => true, 'message' => 'Result cleared.']);
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        if (! $team) {
            return redirect()->route('student.team')
                ->with('error', 'Join or create a team before submitting a Pre-Proposal.');
        }

        if ($this->isTurnedIn($team)) {
            return redirect()->route('student.duplication')
                ->with('error', 'Your pre-proposals are already turned in. Unsubmit first if you need to add another.');
        }

        $slot = $this->nextAvailableSlot($team);

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
                // Deliberately NOT stamped here. submitted_at is what "turned
                // in" means, and turning in is an explicit act now - saving is
                // just keeping a draft. Saving used to stamp it and silently
                // flip the team to complete on the third one, which meant a
                // team was in front of the CAPSA before they had said they
                // were ready.
                'submitted_at' => null,
            ]
        );

        $saved = $team->preProposals()->count();
        $message = $saved < self::RECOMMENDED_PROPOSALS
            ? 'Pre-proposal saved as a draft. '.$saved.' saved - four is the number we suggest, so one being turned down does not send you back to the start.'
            : 'Pre-proposal saved as a draft. Submit for checking when your team is ready.';

        return redirect()->route('student.duplication')->with('status', $message);
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
    /**
     * Turn the team's pre-proposals in for the CAPSA to check.
     *
     * This is the one act that puts a team in front of the CAPSA, which is
     * why it also marks them complete: saving a title used to do both
     * silently, so a team was under review before they had said they were
     * ready. One title is enough; RECOMMENDED_PROPOSALS is advice, not a gate.
     */
    /**
     * Handing the pre-proposal in and taking it back are the same field
     * moving, so one PATCH carries where it should end up.
     */
    public function updateStatus(Request $request): RedirectResponse
    {
        return $request->validate([
            'status' => ['required', 'in:submitted,draft'],
        ])['status'] === 'submitted'
            ? $this->submit($request)
            : $this->unsubmit($request);
    }

    public function submit(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        if (! $team) {
            return redirect()->route('student.team')->with('error', 'Join or create a team first.');
        }

        if ((int) $team->leader_id !== (int) $request->user()->student_id) {
            return redirect()->route('student.duplication')
                ->with('error', 'Only the team leader can submit your pre-proposals for checking.');
        }

        if ($this->isTurnedIn($team)) {
            return redirect()->route('student.duplication')
                ->with('error', 'Your pre-proposals are already turned in.');
        }

        $count = $team->preProposals()->count();

        // One is enough to be worth reading. The page says four is wiser and
        // asks before letting fewer through; it does not refuse them.
        if ($count < 1) {
            return redirect()->route('student.duplication')
                ->with('error', 'Save a pre-proposal title before submitting.');
        }

        // Only the pre-proposals are touched. Turning in used to also set
        // team_status to 'complete', which is what let one column mean both
        // "not recruiting" and "under review"; team_status is recruitment
        // only now, and Team::hasSubmittedPreProposals() answers the rest.
        $team->preProposals()->whereNull('submitted_at')->update(['submitted_at' => now()]);

        return redirect()->route('student.duplication')
            ->with('status', 'Submitted for checking. Your CaPSA can now review your '.$count.' titles.');
    }

    /**
     * Take the submission back, the way Google Classroom lets you unsubmit.
     *
     * Only until the CAPSA has acted on any of the titles - after that,
     * pulling the set back would change what she is part-way through
     * reviewing, so her decision has to stand.
     */
    public function unsubmit(Request $request): RedirectResponse
    {
        $team = $this->currentTeam($request);

        if (! $team) {
            return redirect()->route('student.team')->with('error', 'Join or create a team first.');
        }

        if ((int) $team->leader_id !== (int) $request->user()->student_id) {
            return redirect()->route('student.duplication')
                ->with('error', 'Only the team leader can unsubmit.');
        }

        if (! $this->isTurnedIn($team)) {
            return redirect()->route('student.duplication')
                ->with('error', 'Your pre-proposals have not been submitted yet.');
        }

        if ($this->reviewStarted($team)) {
            return redirect()->route('student.duplication')
                ->with('error', 'Your CaPSA has already started reviewing these titles, so they can no longer be unsubmitted.');
        }

        $team->preProposals()->update(['submitted_at' => null]);

        return redirect()->route('student.duplication')
            ->with('status', 'Unsubmitted. Your titles are drafts again - submit for checking when you are ready.');
    }

    /**
     * The PDF the team actually uploaded, served back to that same team.
     *
     * The CaPSA has had this since they started reviewing; the students who
     * wrote it had no way to open it again. It matters most on the expert
     * application form, which tells a team their approved pre-proposal goes
     * out with the application without ever showing them which file that is.
     *
     * The document is not on the proposal - it belongs to the duplication
     * check the proposal was created from.
     */
    public function document(Request $request, CapstonePreProposal $preProposal)
    {
        $team = $this->currentTeam($request);

        if (! $team || $preProposal->team_id !== $team->team_id) {
            abort(403);
        }

        $check = $preProposal->duplicationCheck;

        if (! $check || ! $check->document_path || ! Storage::exists($check->document_path)) {
            abort(404, 'The original document could not be found.');
        }

        // Inline, so it opens in the browser's reader rather than downloading.
        return Storage::response(
            $check->document_path,
            'Pre-Proposal-'.$preProposal->slot.'.pdf',
            [],
            'inline'
        );
    }

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

        if ($this->isTurnedIn($team)) {
            return redirect()->route('student.duplication')
                ->with('error', 'Your pre-proposals are turned in. Unsubmit first if you need to remove one.');
        }

        $preProposal->delete();

        return redirect()->route('student.duplication')
            ->with('status', 'Proposal removed.');
    }

    /**
     * Slots simply count up. There used to be exactly three, with a rejected
     * one's slot recycled; a team may now keep as many titles as they like,
     * and a rejected title stays put as a record rather than being written
     * over.
     */
    private function nextAvailableSlot(Team $team): int
    {
        return (int) $team->preProposals()->max('slot') + 1;
    }

    /** Whether this team has turned their pre-proposals in for checking. */
    private function isTurnedIn(Team $team): bool
    {
        return $team->preProposals()->whereNotNull('submitted_at')->exists();
    }

    /**
     * Whether the CAPSA has started acting on the submission.
     *
     * Once she has approved or rejected any of the titles, the set is locked:
     * pulling it back then would change what she is part-way through reading.
     */
    private function reviewStarted(Team $team): bool
    {
        return $team->preProposals()
            ->where(fn ($q) => $q->where('approved_status', true)->orWhereNotNull('rejection_reason'))
            ->exists();
    }

    private function currentTeam(Request $request): ?Team
    {
        return StudentTeam::for($request->user()->student_id);
    }
}
