<?php

namespace App\Http\Controllers;

use App\Models\CapstonePreProposal;
use App\Models\Notification;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\SectionScope;
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
            return redirect()->route('admin.verify_teams')
                ->with('error', 'This team has not submitted their pre-proposals for checking yet, so it cannot be verified.');
        }

        if ($team->is_confirmed) {
            return redirect()->route('admin.verify_teams')
                ->with('error', 'Team <strong>'.e($team->team_name).'</strong> is already verified.');
        }

        if (! $team->selected_pre_proposal_id) {
            return redirect()->route('admin.verify_teams')
                ->with('error', 'This team\'s final title hasn\'t been resolved yet - approve one of their pre-proposals first.');
        }

        $adminSections = $admin->sections ?? [];
        $section = $this->sections->sectionFor($team);

        if (! $this->sections->covers($adminSections, $section)) {
            return redirect()->route('admin.verify_teams')
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

        return redirect()->route('admin.verify_teams')
            ->with('status', 'Team <strong>'.e($team->team_name).'</strong> has been verified.');
    }

    // Mirrors AdminApprovalController::approve() exactly - same shape
    // (approved_status + rejection_reason), same guards.
    public function approvePreProposal(CapstonePreProposal $preProposal): JsonResponse
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

        $preProposal->update(['approved_status' => true, 'rejection_reason' => null]);

        $this->resolveTitleIfSettled($preProposal->team);

        return response()->json(['success' => true, 'message' => 'Proposal approved.']);
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

        return response()->json(['success' => true, 'message' => 'Proposal rejected.']);
    }

    // After every approve/reject, check whether the title question is now
    // settled: exactly one approved auto-resolves it; two or more means the
    // student has to pick, so they're notified instead.
    private function resolveTitleIfSettled(Team $team): void
    {
        $team->refresh();

        // Already finalized (admin clicked "Verify Team") - a late approval
        // shouldn't retroactively change a decision that's already locked in.
        if ($team->is_confirmed) {
            return;
        }

        $approved = $team->preProposals()->where('approved_status', true)->get();

        if ($approved->count() === 1) {
            $winner = $approved->first();

            if ($team->selected_pre_proposal_id !== $winner->id) {
                $team->update([
                    'selected_pre_proposal_id' => $winner->id,
                    'project_title' => $winner->title,
                ]);

                $this->notifyTeam($team, 'title_approved', 'Title Approved', 'Your title <strong>'.e($winner->title).'</strong> has been approved by your adviser.');
            }
        } elseif ($approved->count() >= 2) {
            // A second approval arriving after the first had already
            // auto-selected a winner reopens the decision - the student
            // picks between the now-multiple approved options instead.
            if ($team->selected_pre_proposal_id) {
                $team->update(['selected_pre_proposal_id' => null]);
            }

            $this->notifyTeam($team, 'title_pick_required', 'Choose Your Final Title', 'More than one of your submitted titles was approved - please choose which one to proceed with.');
        }
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
