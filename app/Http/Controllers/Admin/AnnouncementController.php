<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with('admin', 'team')
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

        return view('admin.announcements', [
            'announcements' => $announcements,
            'teams' => $teams,
            'adminSections' => $adminSections,
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
        ]);

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
        ]);

        $announcement->update($data);

        return response()->json($announcement);
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return response()->json(['success' => true]);
    }
}
