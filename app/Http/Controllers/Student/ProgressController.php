<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Milestone;
use App\Models\Student;
use App\Models\StudentMilestone;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function index()
    {
        /** @var Student $student */
        $student = auth()->user();

        $studentSy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $teamId = $studentSy?->team_id;

        $isLeader = false;
        if ($teamId) {
            $team = Team::find($teamId);
            $isLeader = $team && $team->leader_id === $student->student_id;
        }

        $milestoneAnnouncements = collect();
        $doneMilestoneIds = [];
        $fileMap = [];
        $doneByMap = [];
        $milestoneMap = [];
        $dueDateMap = [];

        if ($teamId) {
            $sectionLabel = Student::computeSectionLabel($student->course, $student->year_section);

            $milestoneAnnouncements = Announcement::with('admin', 'team')
                ->where('title', 'like', 'Milestone Reminder:%')
                ->where('is_active', true)
                ->get()
                ->filter(function ($announcement) use ($student, $sectionLabel) {
                    $audience = (array) ($announcement->target_audience ?? ['all']);

                    if (! in_array('all', $audience, true) && ! in_array('student', $audience, true)) {
                        return false;
                    }

                    if ($announcement->team) {
                        $memberIds = StudentSchoolYear::where('team_id', $announcement->team->team_id)
                            ->whereNotNull('student_id')
                            ->pluck('student_id')
                            ->toArray();

                        if (in_array($student->student_id, $memberIds, true)) {
                            return true;
                        }
                    }

                    if (! $sectionLabel) {
                        return false;
                    }

                    $studentSectionLower = strtolower($sectionLabel);

                    $targetSections = array_values(array_filter(
                        $audience,
                        fn ($a) => str_starts_with((string) $a, 'section:')
                    ));
                    $targetSections = array_map(fn ($s) => strtolower(substr((string) $s, 8)), $targetSections);

                    if (! empty($targetSections)) {
                        return in_array($studentSectionLower, $targetSections, true);
                    }

                    $admin = $announcement->admin;
                    if ($admin && ! empty($admin->sections)) {
                        $adminSectionsLower = array_map('strtolower', $admin->sections);

                        return in_array($studentSectionLower, $adminSectionsLower, true);
                    }

                    return true;
                })
                ->sortByDesc('created_at')
                ->values();

            // Build a map of milestone title -> milestone id from the DB
            $titles = $milestoneAnnouncements->map(fn ($ann) => str_replace('Milestone Reminder: ', '', $ann->title))->unique();
            $milestones = Milestone::whereIn('title', $titles)->get(['id', 'title', 'due_date']);
            foreach ($milestones as $m) {
                $milestoneMap[strtolower(trim($m->title))] = $m->id;
                $dueDateMap[strtolower(trim($m->title))] = $m->due_date?->format('M j, Y');
            }

            $doneMilestones = StudentMilestone::where('team_id', $teamId)->with('student')->get(['milestone_id', 'file_path', 'student_id', 'completed_at', 'is_verified']);
            $doneMilestoneIds = $doneMilestones->pluck('milestone_id')->toArray();
            $verifiedIds = $doneMilestones->where('is_verified', true)->pluck('milestone_id')->toArray();
            $fileMap = $doneMilestones->pluck('file_path', 'milestone_id')->map(fn ($p) => $p ? \Storage::url($p) : null)->toArray();
            $doneByMap = $doneMilestones->keyBy('milestone_id');
        }

        $verifiedCount = count(array_filter(
            $milestoneAnnouncements->toArray(),
            fn ($ann) => in_array(
                $milestoneMap[strtolower(trim(str_replace('Milestone Reminder: ', '', $ann['title'])))] ?? null,
                $verifiedIds,
                true
            )
        ));
        $submittedCount = count(array_filter(
            $milestoneAnnouncements->toArray(),
            fn ($ann) => in_array(
                $milestoneMap[strtolower(trim(str_replace('Milestone Reminder: ', '', $ann['title'])))] ?? null,
                $doneMilestoneIds,
                true
            )
        ));
        $pendingCount = $submittedCount - $verifiedCount;
        $todoCount = $milestoneAnnouncements->count() - $submittedCount;

        // Overdue: milestones with past due_date not yet completed
        $overdue = 0;
        if ($teamId) {
            $now = now()->startOfDay();
            $titles = $milestoneAnnouncements->map(fn ($ann) => str_replace('Milestone Reminder: ', '', $ann->title));
            $overdue = Milestone::whereIn('title', $titles)
                ->where('due_date', '<', $now)
                ->whereNotIn('id', $verifiedIds)
                ->count();
        }

        return view('student.progress', [
            'milestoneAnnouncements' => $milestoneAnnouncements,
            'milestoneMap' => $milestoneMap,
            'dueDateMap' => $dueDateMap,
            'fileMap' => $fileMap,
            'doneByMap' => $doneByMap,
            'doneMilestoneIds' => $doneMilestoneIds,
            'completed' => $verifiedCount,
            'pending' => $pendingCount,
            'todo' => $todoCount,
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
        $studentSy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $teamId = $studentSy?->team_id;

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
        $studentSy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $teamId = $studentSy?->team_id;

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
