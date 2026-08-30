<?php

namespace App\Http\Controllers;

use App\Models\CapstonePreProposal;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminTeamVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $admin = auth('expert')->user();
        $adminSections = $admin->sections ?? [];

        $tab = $request->query('tab', 'pending');
        $selectedSection = $request->query('section');

        $pendingCandidates = Team::with('leader', 'preProposals.duplicationCheck')
            ->where('team_status', 'complete')
            ->where('is_confirmed', false)
            ->orderByDesc('created_at')
            ->get();

        $verifiedCandidates = Team::with('leader', 'preProposals.duplicationCheck')
            ->where('is_confirmed', true)
            ->orderByDesc('created_at')
            ->get();

        $syRecords = StudentSchoolYear::whereIn('team_id', $pendingCandidates->pluck('team_id')->merge($verifiedCandidates->pluck('team_id'))->unique())
            ->with('student')
            ->get()
            ->groupBy('team_id');

        $pending = $this->buildTeams($pendingCandidates, $syRecords, $adminSections, $selectedSection);
        $verified = $this->buildTeams($verifiedCandidates, $syRecords, $adminSections, $selectedSection);

        $active = $tab === 'verified' ? $verified : $pending;

        return view('capsa.verify_teams', [
            'teams' => $active['teams'],
            'teamSections' => $active['teamSections'],
            'teamMembers' => $active['teamMembers'],
            'tab' => $tab,
            'selectedSection' => $selectedSection,
            'pendingCount' => $pending['count'],
            'verifiedCount' => $verified['count'],
            'allSections' => $adminSections,
        ]);
    }

    /**
     * @return array{teams: Collection<int, Team>, teamSections: array<int, string>, teamMembers: array<int, Collection<int, Student>>, count: int}
     */
    private function buildTeams($candidates, $syRecords, array $adminSections, ?string $selectedSection): array
    {
        $teams = collect();
        $teamSections = [];
        $teamMembers = [];

        foreach ($candidates as $team) {
            $records = $syRecords->get($team->team_id, collect());
            $members = $records->map(fn ($sy) => $sy->student)->filter()->values();

            $section = $this->sectionLabelFromRecords($records);

            // A team whose members haven't completed their profile (course /
            // year_section) has no section to match against any admin's
            // assignment - since the forced personal-info onboarding step
            // was removed for new student sign-ins, this is now a real,
            // reachable state, not just a data-migration edge case. Excluding
            // it here would make it permanently invisible to every admin,
            // with no one able to ever verify it - shown to every admin
            // instead, distinctly labeled, rather than silently lost.
            $hasSection = $section !== '';

            if ($hasSection) {
                if (! in_array($section, $adminSections, true)) {
                    continue;
                }

                if ($selectedSection && $section !== $selectedSection) {
                    continue;
                }
            }

            $teamSections[$team->team_id] = $hasSection ? $section : 'Unassigned (incomplete profile)';
            $teamMembers[$team->team_id] = $members;
            $teams->push($team);
        }

        return [
            'teams' => $teams,
            'teamSections' => $teamSections,
            'teamMembers' => $teamMembers,
            'count' => $teams->count(),
        ];
    }

    public function verify(Request $request, Team $team): RedirectResponse
    {
        $admin = auth('expert')->user();
        $team->refresh();

        if ($team->team_status !== 'complete') {
            return redirect()->route('admin.verify_teams')
                ->with('error', 'Team is not marked as complete, so it cannot be verified yet.');
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
        $section = $this->teamSection($team);

        // A team with no computed section (members haven't completed their
        // profile) is shown to every admin on the listing page rather than
        // being permanently invisible to all of them - the verify action
        // has to allow the same team through, or it just becomes visible
        // but permanently un-actionable instead.
        if ($section !== '' && ! in_array($section, $adminSections, true)) {
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

    // Used by verify() to re-check a single team's section server-side. Must
    // compute it identically to buildTeams() (which the admin's list view
    // renders from) - a previous version fell back to the raw student_s_y
    // "program" column when no full section label could be computed, which
    // silently disagreed with what buildTeams() showed as "Unassigned":
    // the team looked verifiable in the list but verify() rejected it with
    // "You can only verify teams assigned to your sections" anyway.
    private function teamSection(Team $team): string
    {
        $records = StudentSchoolYear::where('team_id', $team->team_id)
            ->with('student')
            ->get();

        return $this->sectionLabelFromRecords($records);
    }

    /**
     * @param  Collection<int, StudentSchoolYear>  $records
     */
    private function sectionLabelFromRecords(Collection $records): string
    {
        foreach ($records as $sy) {
            if ($sy->student) {
                $sectionLabel = Student::computeSectionLabel($sy->student->course, $sy->student->year_section);

                if ($sectionLabel) {
                    return $sectionLabel;
                }
            }
        }

        return '';
    }
}
