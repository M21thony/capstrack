<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Student;
use App\Models\StudentMilestone;
use App\Models\Team;
use App\Support\MilestoneProgress;
use App\Support\StudentTeam;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    /**
     * The team's checklist, as the CaPSA actually keeps it.
     *
     * This used to read announcements: it found every announcement whose
     * title began "Milestone Reminder:", stripped that prefix, and looked the
     * milestone up by matching the title string. So what a team saw was
     * whichever announcements happened to have been posted, not the
     * deliverables anybody was tracking - and a renamed milestone dropped off
     * the page entirely.
     *
     * It is read-only on purpose. Every line here is closed by somebody else:
     * the CaPSA sights a signed form, an upload closes a draft, the adviser's
     * verdict closes a CLF, the panel closes a defense. A team marking its own
     * deliverable done would be asserting something it is not the one to say.
     */
    public function index()
    {
        /** @var Student $student */
        $student = auth()->user();

        $teamId = StudentTeam::idFor($student->student_id);

        $isLeader = false;
        if ($teamId) {
            $team = Team::find($teamId);
            $isLeader = $team && $team->leader_id === $student->student_id;
        }

        $boards = collect();
        $completed = 0;
        $pending = 0;
        $todo = 0;
        $overdue = 0;

        if ($teamId) {
            $boards = Milestone::where('team_id', $teamId)
                ->with('items')
                ->orderBy('due_date')
                ->orderBy('id')
                ->get()
                // A checklist with no lines on it yet says nothing a team can
                // act on - it reads "0 of 0 complete", which looks like a
                // fault rather than a checklist still being written. A CaPSA
                // building one by hand should not be pushing an empty card at
                // their students in the meantime; it appears once it has
                // something on it.
                ->filter(fn (Milestone $milestone) => $milestone->items->isNotEmpty())
                ->values()
                ->map(fn (Milestone $milestone) => (object) [
                    'model' => $milestone,
                    'progress' => $milestone->progress(),
                ]);

            foreach ($boards as $board) {
                $completed += $board->progress->completed;
                $pending += $board->progress->handed_in;
                $overdue += $board->progress->overdue;
                $todo += $board->progress->items
                    ->where('state', MilestoneProgress::NOT_STARTED)
                    ->count();
            }
        }

        return view('student.progress', [
            'boards' => $boards,
            'completed' => $completed,
            'pending' => $pending,
            'todo' => $todo,
            'overdue' => $overdue,
            'teamId' => $teamId,
            'isLeader' => $isLeader,
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
