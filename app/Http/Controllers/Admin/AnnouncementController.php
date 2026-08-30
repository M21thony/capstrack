<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Milestone;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentMilestone;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    public function index()
    {
        $showAll = request()->query('show_all') === '1';

        $announcements = Announcement::with('admin', 'team', 'milestone')
            ->when(! $showAll, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('created_at')
            ->get();

        $admin = auth('expert')->user();
        $adminSections = $admin->sections ?? [];
        $teams = collect();

        if (! empty($adminSections)) {
            $confirmedTeams = Team::where('is_confirmed', true)
                ->orderBy('team_name')
                ->get();

            $syRecords = StudentSchoolYear::with('student')
                ->whereIn('team_id', $confirmedTeams->pluck('team_id'))
                ->get()
                ->groupBy('team_id');

            foreach ($confirmedTeams as $team) {
                $records = $syRecords->get($team->team_id);
                if (! $records) {
                    continue;
                }

                foreach ($records as $sy) {
                    $student = $sy->student;
                    if (! $student?->course || ! $student->year_section) {
                        continue;
                    }

                    $sectionLabel = Student::computeSectionLabel($student->course, $student->year_section);
                    if (! $sectionLabel) {
                        continue;
                    }

                    if (in_array($sectionLabel, $adminSections, true)) {
                        $teams->push(['team_id' => $team->team_id, 'team_name' => $team->team_name]);
                        break;
                    }
                }
            }

            $teams = $teams->unique('team_id')->values();
        }

        $milestoneTitles = ['Capstone Team', 'Pre-Proposal', 'Title Defense', 'Pre-Oral Defense', 'Final Defense'];
        $allMs = Milestone::whereIn('title', $milestoneTitles)
            ->orderBy('due_date')
            ->get(['id', 'title', 'description', 'due_date']);

        $grouped = $allMs->groupBy('title');
        $milestones = collect();
        foreach ($milestoneTitles as $title) {
            $first = $grouped->get($title)?->first();
            $milestones->push([
                'id' => $first?->id,
                'title' => $title,
                'description' => $first?->description,
                'due_date' => $first?->due_date?->format('M j, Y'),
                'due_date_raw' => $first?->due_date?->format('Y-m-d'),
            ]);
        }

        $milestoneSubmissions = StudentMilestone::with('student', 'team')
            ->get()
            ->groupBy('milestone_id')
            ->map(function ($items) {
                return $items->map(fn ($sm) => [
                    'milestone_id' => $sm->milestone_id,
                    'file_url' => $sm->file_path ? Storage::url($sm->file_path) : null,
                    'completed_at' => $sm->completed_at?->toISOString(),
                    'submission_id' => $sm->id,
                    'is_verified' => $sm->is_verified,
                    'student' => $sm->student ? [
                        'f_name' => $sm->student->f_name,
                        'l_name' => $sm->student->l_name,
                        'avatar' => $sm->student->avatarUrl(),
                        'initials' => $sm->student->avatarInitials(),
                    ] : null,
                    'team_name' => $sm->team?->team_name,
                ]);
            });

        return view('capsa.announcements', [
            'announcements' => $announcements,
            'teams' => $teams,
            'adminSections' => $adminSections,
            'milestones' => $milestones,
            'milestoneSubmissions' => $milestoneSubmissions,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'priority' => 'required|in:normal,important,urgent',
            'target_audience' => 'required|array',
            'target_audience.*' => 'string',
            'team_id' => 'nullable|integer|exists:team,team_id',
            'milestone_title' => 'nullable|string|max:255',
            'milestone_due_date' => 'nullable|date',
        ]);

        // Create milestone record if posting from Milestone tab
        if ($data['milestone_title'] ?? null) {
            $milestone = Milestone::create([
                'title' => $data['milestone_title'],
                'description' => $data['body'],
                'due_date' => $data['milestone_due_date'] ?? null,
                'status' => 'todo',
                'team_id' => $data['team_id'] ?? null,
            ]);
            $data['milestone_id'] = $milestone->id;
        }

        $announcement = Announcement::create([
            ...$data,
            'admin_id' => auth()->guard('expert')->id(),
        ]);

        $now = now();
        $studentNotifs = collect();

        // Team mention notifications
        if ($data['team_id'] ?? null) {
            $team = Team::find($data['team_id']);
            $memberIds = StudentSchoolYear::where('team_id', $team->team_id)
                ->whereNotNull('student_id')
                ->pluck('student_id')
                ->unique();

            $memberIds->each(function ($sid) use (&$studentNotifs, $team, $data, $now) {
                $studentNotifs->push([
                    'student_id' => $sid,
                    'type' => 'team_mention',
                    'title' => 'Team mentioned in announcement',
                    'message' => 'Your team <strong>'.e($team->team_name).'</strong> was mentioned in: '.$data['title'],
                    'link' => '/student/dashboard#announcements-card',
                    'is_read' => false,
                    'created_at' => $now,
                ]);
            });
        }

        // General announcement notifications for students
        $audience = $data['target_audience'] ?? [];
        $targetsStudents = in_array('all', $audience) || in_array('student', $audience);

        if ($targetsStudents) {
            $targetSections = array_values(array_filter(
                $audience,
                fn ($a) => str_starts_with((string) $a, 'section:')
            ));
            $targetSections = array_map(fn ($s) => strtolower(substr((string) $s, 8)), $targetSections);

            $students = Student::whereNotNull('course')->whereNotNull('year_section')->get();

            foreach ($students as $student) {
                $label = Student::computeSectionLabel($student->course, $student->year_section);
                if (! $label) {
                    continue;
                }

                if (! empty($targetSections)) {
                    if (! in_array(strtolower($label), $targetSections, true)) {
                        continue;
                    }
                }

                // Avoid duplicate notifications (e.g. already added via team mention)
                $alreadyNotified = $studentNotifs->contains('student_id', $student->student_id);
                if ($alreadyNotified) {
                    continue;
                }

                $studentNotifs->push([
                    'student_id' => $student->student_id,
                    'type' => 'announcement',
                    'title' => $data['title'],
                    'message' => Str::limit(strip_tags($data['body']), 120),
                    'link' => '/student/dashboard#announcements-card',
                    'is_read' => false,
                    'created_at' => $now,
                ]);
            }
        }

        if ($studentNotifs->isNotEmpty()) {
            foreach ($studentNotifs->chunk(100) as $chunk) {
                Notification::insert($chunk->toArray());
            }
        }

        return response()->json($announcement);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'priority' => 'required|in:normal,important,urgent',
            'target_audience' => 'required|array',
            'target_audience.*' => 'string',
            'team_id' => 'nullable|integer|exists:team,team_id',
            'milestone_title' => 'nullable|string|max:255',
            'milestone_due_date' => 'nullable|date',
        ]);

        if ($data['milestone_title'] ?? null) {
            $data['title'] = 'Milestone Reminder: '.$data['milestone_title'];
        }

        $announcement->load('milestone');

        if ($announcement->milestone && ($data['milestone_title'] ?? null)) {
            $announcement->milestone->update([
                'title' => $data['milestone_title'],
                'description' => $data['body'],
                'due_date' => $data['milestone_due_date'] ?? null,
                'team_id' => $data['team_id'] ?? null,
            ]);
        } elseif ($data['milestone_title'] ?? null) {
            $milestone = Milestone::create([
                'title' => $data['milestone_title'],
                'description' => $data['body'],
                'due_date' => $data['milestone_due_date'] ?? null,
                'status' => 'todo',
                'team_id' => $data['team_id'] ?? null,
            ]);
            $data['milestone_id'] = $milestone->id;
        }

        $announcement->update($data);

        return response()->json($announcement);
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->load('milestone');

        if ($announcement->milestone) {
            $announcement->milestone->delete();
        }

        $announcement->delete();

        return response()->json(['success' => true]);
    }

    public function verifySubmission(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'submission_id' => 'required|integer|exists:student_milestones,id',
            'verified' => 'required|boolean',
        ]);

        $submission = StudentMilestone::findOrFail($data['submission_id']);

        $submission->update([
            'is_verified' => $data['verified'],
            'verified_at' => $data['verified'] ? now() : null,
        ]);

        $milestone = $announcement->milestone;
        if ($milestone && $data['verified']) {
            $milestone->update(['status' => 'completed']);
        } elseif ($milestone && ! $data['verified']) {
            $milestone->update(['status' => 'in_progress']);
        }

        return response()->json(['success' => true, 'is_verified' => $data['verified']]);
    }
}
