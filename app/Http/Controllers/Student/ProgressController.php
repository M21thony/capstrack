<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Student;
use App\Models\StudentMilestone;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\MilestoneProgress;
use App\Support\SectionScope;
use App\Support\StudentTeam;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    /**
     * The team's own milestones, as its section's CaPSA keeps them.
     *
     * Read-only on purpose: every deliverable is closed by somebody else - the
     * CaPSA checks a signed paper, an upload closes a draft, the panel closes
     * a defense. What a team can do is upload, and that goes through Document
     * Submission with the deliverable already picked.
     */
    public function index(SectionScope $sections)
    {
        /** @var Student $student */
        $student = auth()->user();

        $teamId = StudentTeam::idFor($student->student_id);
        $team = $teamId ? Team::find($teamId) : null;
        $progress = $team?->checklistProgress();

        $groups = collect();

        if ($progress) {
            $groups = $progress->milestones
                ->map(fn ($summary) => (object) [
                    'name' => $summary->milestone->name,
                    'description' => $summary->milestone->description,
                    // The milestone's own date, when the CaPSA set one.
                    'due' => $summary->milestone->due_on,
                    'past_due' => $summary->past_due,
                    'rows' => $summary->items,
                    'closed' => $summary->completed,
                    'overdue' => $summary->overdue,
                    'dues' => $summary->items->pluck('due_on')->filter()->sort()->values(),
                    // Shut behind an unfinished phase - or a finished one whose
                    // experts the leader has not rated yet.
                    'locked' => $summary->locked,
                    'requires' => $summary->requires,
                    'evaluations_owed' => $summary->evaluations_owed,
                ])
                ->values();
        }

        $engaged = $team
            ? TeamExpertAssignment::where('team_id', $team->team_id)->where('status', 'approved')->with(['expert', 'expertRole.role', 'role'])->get()
            : collect();
        $expert = fn (string $role) => $engaged->first(fn ($a) => $a->engaged_role_name === $role)?->expert?->name;

        return view('student.progress', [
            'teamId' => $teamId,
            'team' => $team,
            'section' => $team ? $sections->sectionFor($team) : '',
            'adviser' => $expert('adviser'),
            'consultant' => $expert('consultant'),
            'progress' => $progress,
            'groups' => $groups,
            // The milestone the team is on: the first one not finished. It starts open.
            // A finished one still waiting on the expert evaluation counts.
            'current' => $groups->first(fn ($group) => $group->closed < $group->rows->count() || $group->evaluations_owed->isNotEmpty()),
            'isLeader' => $team && (int) $team->leader_id === (int) $student->student_id,
            'next' => $progress?->items
                ->reject(fn ($row) => in_array($row->state, MilestoneProgress::COMPLETE_STATES, true))
                ->filter(fn ($row) => $row->due_on)
                ->sortBy(fn ($row) => $row->due_on)
                ->first(),
        ]);
    }

    public function markDone(Request $request)
    {
        $data = $request->validate([
            'milestone_id' => 'required|integer|exists:milestones,id',
            'file' => 'required|image|max:5120',
        ]);

        /** @var Student $student */
        $student = auth()->user();
        $teamId = StudentTeam::idFor($student->student_id);

        if (! $teamId) {
            return back()->with('error', 'No team found.');
        }

        $exists = StudentMilestone::where('milestone_id', $data['milestone_id'])
            ->where('team_id', $teamId)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Already marked as done.');
        }

        $path = $request->file('file')->store('milestones', 'public');

        StudentMilestone::create([
            'milestone_id' => $data['milestone_id'],
            'team_id' => $teamId,
            'student_id' => $student->student_id,
            'file_path' => $path,
            'is_verified' => false,
        ]);

        $milestone = Milestone::find($data['milestone_id']);
        if ($milestone && $milestone->status === 'todo') {
            $milestone->update(['status' => 'in_progress']);
        }

        return back()->with('success', 'Milestone marked as done. Waiting for admin verification.');
    }

    public function updatePicture(Request $request)
    {
        $data = $request->validate([
            'milestone_id' => 'required|integer|exists:milestones,id',
            'file' => 'required|image|max:5120',
        ]);

        $student = auth()->user();
        $teamId = StudentTeam::idFor($student->student_id);

        if (! $teamId) {
            return back()->with('error', 'No team found.');
        }

        $submission = StudentMilestone::where('milestone_id', $data['milestone_id'])
            ->where('team_id', $teamId)
            ->first();

        if (! $submission) {
            return back()->with('error', 'No submission found.');
        }

        $path = $request->file('file')->store('milestones', 'public');

        $submission->update([
            'file_path' => $path,
            'is_verified' => false,
            'verified_at' => null,
        ]);

        $milestone = Milestone::find($data['milestone_id']);
        if ($milestone) {
            $milestone->update(['status' => 'in_progress']);
        }

        return back()->with('success', 'Submission picture updated.');
    }
}
