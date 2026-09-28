<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\CapstonePreProposal;
use App\Models\Notification;
use App\Models\StudentSchoolYear;
use App\Models\TitleChangeRequest;
use App\Services\RecipientResolver;
use App\Support\TitleRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The adviser confirming a team's new title after "Redefense with a new
 * title". The team already has this adviser - it applied before the
 * Pre-proposal Defense - so this stands in for the application the adviser
 * would otherwise have approved the title through.
 */
class TitleConfirmationController extends Controller
{
    public function __construct(private RecipientResolver $resolver) {}

    /** PATCH /instructor/title-confirmations/{preProposal} */
    public function update(Request $request, CapstonePreProposal $preProposal): RedirectResponse
    {
        $this->authorizeAdviser($preProposal);
        abort_unless($preProposal->adviser_status === TitleRevision::PENDING && ! $preProposal->retired_at, 404);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,decline'],
            'note' => ['required_if:decision,decline', 'nullable', 'string', 'min:10', 'max:1000'],
        ], [
            'note.required_if' => 'Tell the team what to change - they turn in another title from this.',
        ]);

        $team = $preProposal->team;

        if ($validated['decision'] === 'approve') {
            $preProposal->update(['adviser_status' => TitleRevision::APPROVED, 'adviser_decided_at' => now()]);
            $team->update(['selected_pre_proposal_id' => $preProposal->id, 'project_title' => $preProposal->title]);
            $this->notifyTeam($team->team_id, 'New title confirmed', 'Your adviser confirmed <strong>'.e($preProposal->title).'</strong> as your new title.');

            return redirect()->route('instructor.assignments')->with('status', 'Confirmed '.$team->team_name.'\'s new title.');
        }

        // Declined: this round's titles are set aside with the note, and the
        // team turns in another - through the duplication check again. The
        // adviser stays their adviser.
        $preProposal->update(['adviser_status' => TitleRevision::DECLINED, 'adviser_note' => $validated['note'], 'adviser_decided_at' => now()]);
        $team->preProposals()->update(['retired_at' => now()]);
        $this->notifyTeam($team->team_id, 'Adviser asked for another title', 'Your adviser asked for another title: “'.e($validated['note']).'”');

        return redirect()->route('instructor.assignments')->with('status', 'Sent '.$team->team_name.' back for another title.');
    }

    /**
     * PATCH /instructor/title-changes/{titleChangeRequest} - the adviser
     * deciding on a passed team's request to change part of its title. Only
     * an approval changes the team's title; the pre-proposal keeps the title
     * the panel passed, as a record.
     */
    public function decideChange(Request $request, TitleChangeRequest $titleChangeRequest): RedirectResponse
    {
        abort_unless(
            $titleChangeRequest->isPending()
                && $this->resolver->resolveTeamRoleExpert((int) $titleChangeRequest->team_id, 'adviser') === Auth::guard('expert')->id(),
            404
        );

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,decline'],
            'note' => ['required_if:decision,decline', 'nullable', 'string', 'min:10', 'max:1000'],
        ], [
            'note.required_if' => 'Tell the team why, so they know what to ask for instead.',
            'note.min' => 'Tell the team why, so they know what to ask for instead.',
        ]);

        $team = $titleChangeRequest->team;
        $approve = $validated['decision'] === 'approve';

        // The CaPSA verified the Signed Final Project Title Form while this
        // waited: the title is final now.
        if ($approve && TitleRevision::finalTitleVerified($team)) {
            return redirect()->route('instructor.assignments')
                ->withErrors(['note' => 'The CaPSA has already verified '.$team->team_name.'\'s Signed Final Project Title Form, so the title is final. Keep the title and tell the team why.']);
        }

        $titleChangeRequest->update([
            'status' => $approve ? TitleChangeRequest::APPROVED : TitleChangeRequest::DECLINED,
            'adviser_id' => Auth::guard('expert')->id(),
            'adviser_note' => $approve ? ($validated['note'] ?? null) : $validated['note'],
            'decided_at' => now(),
        ]);

        if ($approve) {
            $team->update(['project_title' => $titleChangeRequest->new_title]);
            $this->notifyTeam($team->team_id, 'Title change approved', 'Your adviser approved your new title: <strong>'.e($titleChangeRequest->new_title).'</strong>.');

            return redirect()->route('instructor.assignments')->with('status', 'Changed '.$team->team_name.'\'s title.');
        }

        $this->notifyTeam($team->team_id, 'Title change declined', 'Your adviser kept your title as it is: “'.e($validated['note']).'”');

        return redirect()->route('instructor.assignments')->with('status', 'Kept '.$team->team_name.'\'s title as it is.');
    }

    /** GET /instructor/title-confirmations/{preProposal}/document - the new pre-proposal's PDF. */
    public function document(CapstonePreProposal $preProposal)
    {
        $this->authorizeAdviser($preProposal);

        $check = $preProposal->duplicationCheck;
        abort_unless($check && $check->document_path && Storage::exists($check->document_path), 404);

        return Storage::response($check->document_path, 'new-title-pre-proposal.pdf', [], 'inline');
    }

    private function authorizeAdviser(CapstonePreProposal $preProposal): void
    {
        abort_unless(
            $this->resolver->resolveTeamRoleExpert((int) $preProposal->team_id, 'adviser') === Auth::guard('expert')->id(),
            404
        );
    }

    private function notifyTeam(int $teamId, string $title, string $message): void
    {
        foreach (StudentSchoolYear::where('team_id', $teamId)->pluck('student_id') as $studentId) {
            Notification::create([
                'student_id' => $studentId,
                'type' => 'title_confirmation',
                'title' => $title,
                'message' => $message,
                'link' => route('student.duplication'),
            ]);
        }
    }
}
