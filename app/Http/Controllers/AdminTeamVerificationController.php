<?php

namespace App\Http\Controllers;

use App\Models\CapstonePreProposal;
use App\Models\Notification;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\RecipientResolver;
use App\Support\PreProposalPayload;
use App\Support\SectionScope;
use App\Support\TitleRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The pre-proposal and verification ACTIONS.
 *
 * The listing page that used to live here is now a tab on Team Management
 * (Capsa\TeamManagementController) - these actions keep their original route
 * names so every link and redirect to them still resolves.
 */
class AdminTeamVerificationController extends Controller
{
    public function __construct(private SectionScope $sections) {}

    public function verify(Request $request, Team $team): RedirectResponse
    {
        $admin = auth('expert')->user();
        $team->refresh();

        if (! $team->hasSubmittedPreProposals()) {
            return redirect()->route('capsa.verify_teams')
                ->with('error', 'This team has not submitted their pre-proposals for checking yet, so it cannot be verified.');
        }

        if ($team->is_confirmed) {
            return redirect()->route('capsa.verify_teams')
                ->with('error', 'Team <strong>'.e($team->team_name).'</strong> is already verified.');
        }

        if (! $team->selected_pre_proposal_id) {
            return redirect()->route('capsa.verify_teams')
                ->with('error', 'This team\'s final title hasn\'t been resolved yet - recommend one of their pre-proposals first.');
        }

        $adminSections = $admin->sections ?? [];
        $section = $this->sections->sectionFor($team);

        if (! $this->sections->covers($adminSections, $section)) {
            return redirect()->route('capsa.verify_teams')
                ->with('error', 'You can only verify teams assigned to your sections.');
        }

        $team->update(['is_confirmed' => true]);

        $this->logActivity(
            $admin->expert_id,
            'team_verified',
            'Verified team <strong>'.e($team->team_name).'</strong>',
            ['team_id' => $team->team_id, 'verified_by' => $admin->expert_id]
        );

        $memberIds = StudentSchoolYear::where('team_id', $team->team_id)
            ->pluck('student_id');

        foreach ($memberIds as $memberId) {
            Notification::create([
                'student_id' => $memberId,
                'type' => 'team_verified',
                'title' => 'Team Verified',
                'message' => 'Your team <strong>'.e($team->team_name).'</strong> has been verified by the administrator!',
                'link' => route('student.team'),
            ]);
        }

        return redirect()->route('capsa.verify_teams')
            ->with('status', 'Team <strong>'.e($team->team_name).'</strong> has been verified.');
    }

    // Mirrors AdminApprovalController::approve() exactly - same shape
    // (approved_status + rejection_reason), same guards.
    /**
     * A CaPSA either picks this pre-proposal or sends it back, and both are
     * the same field changing. The status says which; the methods below
     * still do the work.
     */
    public function updatePreProposal(Request $request, CapstonePreProposal $preProposal): JsonResponse
    {
        return $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            // Recommending may come with a suggestion for the team. Rejecting
            // asks for its reason separately, and requires one.
            'note' => ['nullable', 'string', 'max:1000'],
        ])['status'] === 'approved'
            ? $this->approvePreProposal($preProposal, $request)
            : $this->rejectPreProposal($request, $preProposal);
    }

    public function approvePreProposal(CapstonePreProposal $preProposal, ?Request $request = null): JsonResponse
    {
        if ($preProposal->approved_status) {
            return response()->json(['success' => false, 'message' => 'Already processed.'], 422);
        }

        if (! $preProposal->submitted_at) {
            return response()->json([
                'success' => false,
                'message' => 'This team has not submitted their pre-proposals for checking yet.',
            ], 422);
        }

        // Optional, and only ever what she typed this time: recommending
        // again without a suggestion clears the one before it.
        $note = trim((string) $request?->input('note'));

        $preProposal->update([
            'approved_status' => true,
            'rejection_reason' => null,
            'capsa_note' => $note === '' ? null : $note,
        ]);

        $this->resolveTitleIfSettled($preProposal->team);

        return response()->json([
            'success' => true,
            'message' => 'Proposal recommended.',
        ] + $this->reviewState($preProposal->team));
    }

    // Lets the CAPSA open the exact PDF the team uploaded for this title,
    // not just the AI-extracted fields - part of "include the full content
    // of the Pre-proposal form" on the submission. Served inline (not as an
    // attachment) so it renders in the preview modal instead of forcing a
    // download.
    public function viewPreProposalDocument(CapstonePreProposal $preProposal)
    {
        $check = $preProposal->duplicationCheck;

        if (! $check || ! Storage::exists($check->document_path)) {
            abort(404, 'The original document could not be found.');
        }

        $filename = 'Proposal-'.$preProposal->slot.'-'.Str::slug($preProposal->title).'.pdf';

        return Storage::response($check->document_path, $filename, [], 'inline');
    }

    public function rejectPreProposal(Request $request, CapstonePreProposal $preProposal): JsonResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:1000']);

        if ($preProposal->approved_status) {
            return response()->json(['success' => false, 'message' => 'Already processed.'], 422);
        }

        if (! $preProposal->submitted_at) {
            return response()->json([
                'success' => false,
                'message' => 'This team has not submitted their pre-proposals for checking yet.',
            ], 422);
        }

        $preProposal->update(['rejection_reason' => $validated['reason']]);

        // A rejection is half of the decision. Rejecting the last undecided
        // proposal is what settles the title, so this has to run here too.
        $this->resolveTitleIfSettled($preProposal->team);

        return response()->json([
            'success' => true,
            'message' => 'Proposal rejected.',
        ] + $this->reviewState($preProposal->team));
    }

    // After every approve/reject, check whether the title question is now
    // settled: exactly one approved auto-resolves it; two or more means the
    // student has to pick, so they're notified instead.
    /**
     * What the review modal needs to redraw itself after a decision.
     *
     * It used to reload the page instead, which closed the modal - so a CaPSA
     * with three titles to get through had to reopen it after each one.
     */
    private function reviewState(Team $team): array
    {
        $team->refresh()->load('allPreProposals.duplicationCheck');

        return [
            'proposals' => PreProposalPayload::forTeam($team),
            'selected_pre_proposal_id' => $team->selected_pre_proposal_id,
            'project_title' => $team->project_title,
        ];
    }

    /**
     * Settle the team's title, but only once the review is actually over.
     *
     * Approving one proposal used to set the title there and then. A CaPSA
     * working down a team's three titles approves one and is still reading
     * the others, so the team was told its title was decided while two were
     * still being looked at - and if a second was then approved, the choice
     * reopened but the title already on the team stayed, which is how an
     * approved-but-not-chosen title ended up showing on student/team.
     *
     * Nothing is settled while a proposal is still undecided. A proposal is
     * undecided when it has been submitted and carries neither an approval
     * nor a rejection reason.
     */
    private function resolveTitleIfSettled(Team $team): void
    {
        $team->refresh();

        // Already finalized (admin clicked "Verify Team") - a late approval
        // shouldn't retroactively change a decision that's already locked in.
        // Unless the team has no title: a verified team the panel sent back for
        // a new one is choosing again, and its new title has to land.
        if ($team->is_confirmed && $team->selected_pre_proposal_id) {
            return;
        }

        $submitted = $team->preProposals()->whereNotNull('submitted_at')->get();

        if ($submitted->isEmpty()) {
            return;
        }

        $undecided = $submitted->filter(
            fn ($proposal) => ! $proposal->approved_status && $proposal->rejection_reason === null
        );

        // Still being reviewed. Say nothing and change nothing - the team
        // finds out when their CaPSA has finished, not partway through.
        if ($undecided->isNotEmpty()) {
            return;
        }

        $approved = $submitted->where('approved_status', true)->values();

        if ($approved->count() === 1) {
            $winner = $approved->first();

            // Sent back for a new title: the team already has its adviser, so
            // the adviser confirms the new title before it becomes the team's.
            if (TitleRevision::needsNewTitle($team)) {
                // The team's own CaPSA is also the team's own adviser, and it is
                // them recommending: one person, one click - recommended and
                // approved together. Any other CaPSA still leaves it to the adviser.
                $resolver = app(RecipientResolver::class);
                $adviserId = $resolver->resolveTeamRoleExpert((int) $team->team_id, 'adviser');
                if ($adviserId
                    && $adviserId === $resolver->resolveSubjectAdviserAdmin((int) $team->team_id)
                    && $adviserId === auth('expert')->id()) {
                    $winner->update(['adviser_status' => TitleRevision::APPROVED, 'adviser_note' => null, 'adviser_decided_at' => now()]);
                    $team->update(['selected_pre_proposal_id' => $winner->id, 'project_title' => $winner->title]);
                    $this->notifyTeam($team, 'title_approved', 'New Title Approved', 'Your CaPSA, who is also your adviser, recommended and approved <strong>'.e($winner->title).'</strong> as your new title.');

                    return;
                }

                if ($winner->adviser_status !== TitleRevision::PENDING) {
                    TitleRevision::askAdviser($winner);
                    $this->notifyTeam($team, 'title_awaiting_adviser', 'Title Recommended by CaPSA', 'Your CaPSA recommended <strong>'.e($winner->title).'</strong>. Your adviser approves it next.');
                }

                return;
            }

            if ($team->selected_pre_proposal_id !== $winner->id) {
                $team->update([
                    'selected_pre_proposal_id' => $winner->id,
                    'project_title' => $winner->title,
                ]);

                $this->notifyTeam($team, 'title_approved', 'Title Recommended', 'Your CaPSA recommended your title <strong>'.e($winner->title).'</strong>.');
            }

            return;
        }

        if ($approved->count() >= 2) {
            // Two or more got through, so the team picks. Until they do, the
            // team has no title - clearing it matters as much as clearing the
            // selection, because the title is what the rest of the app shows.
            if ($team->selected_pre_proposal_id || $team->project_title) {
                $team->update([
                    'selected_pre_proposal_id' => null,
                    'project_title' => null,
                ]);
            }

            $this->notifyTeam($team, 'title_pick_required', 'Choose Your Final Title', 'More than one of your submitted titles was recommended - please choose which one to proceed with.');

            return;
        }

        // Everything was rejected: the team has no title, and anything left
        // over from an earlier round would be a title nobody approved.
        if ($team->selected_pre_proposal_id || $team->project_title) {
            $team->update([
                'selected_pre_proposal_id' => null,
                'project_title' => null,
            ]);
        }

        $this->notifyTeam($team, 'titles_rejected', 'Titles Not Recommended', 'Your CaPSA did not recommend any of the titles you submitted. Save new titles and submit them for checking again.');
    }

    private function notifyTeam(Team $team, string $type, string $title, string $message): void
    {
        $memberIds = StudentSchoolYear::where('team_id', $team->team_id)->pluck('student_id');

        foreach ($memberIds as $memberId) {
            Notification::create([
                'student_id' => $memberId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => route('student.duplication'),
            ]);
        }
    }
}
