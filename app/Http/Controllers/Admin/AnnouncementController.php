<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentMilestone;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\NotificationMailer;
use App\Services\Notifier;
use App\Support\AnnouncementAudience;
use App\Support\SectionScope;
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

            // The team's own section - its leader's - the same answer the
            // rest of the portal gives, so a 4th-year team split across
            // sections is offered to the one CaPSA who handles it.
            $scope = app(SectionScope::class);

            foreach ($confirmedTeams as $team) {
                $records = $syRecords->get($team->team_id);
                if ($records && in_array($scope->sectionFor($team, $records), $adminSections, true)) {
                    $teams->push(['team_id' => $team->team_id, 'team_name' => $team->team_name]);
                }
            }

            $teams = $teams->unique('team_id')->values();
        }

        $milestoneSubmissions = StudentMilestone::with('student', 'team')
            ->get()
            ->groupBy('milestone_id')
            ->map(function ($items) {
                return $items->map(fn ($sm) => [
                    'milestone_id' => $sm->milestone_id,
                    'file_url' => $sm->file_path ? Storage::disk('public')->url($sm->file_path) : null,
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
        ]);

        $announcement = Announcement::create([
            ...$data,
            'admin_id' => auth()->guard('expert')->id(),
        ]);

        $now = now();
        $audience = $data['target_audience'];
        $poster = auth()->guard('expert')->user()?->name;

        // The students it reaches are the ones whose dashboard lists it - a
        // team it mentions, or the sections it is aimed at - so nobody gets
        // a bell for a post they can't open.
        $team = $announcement->team_id ? Team::find($announcement->team_id) : null;

        $studentNotifs = AnnouncementAudience::students($announcement)
            ->map(fn (Student $student) => $team ? [
                'student_id' => $student->student_id,
                'type' => 'team_mention',
                'title' => 'Team mentioned in announcement',
                'message' => 'Your team <strong>'.e($team->team_name).'</strong> was mentioned in: '.$data['title'],
                'link' => '/student/dashboard#announcements-card',
                'is_read' => false,
                'created_at' => $now,
            ] : [
                'student_id' => $student->student_id,
                'type' => 'announcement',
                'title' => $data['title'],
                'message' => Str::limit(strip_tags($data['body']), 120),
                'link' => '/student/dashboard#announcements-card',
                'is_read' => false,
                'created_at' => $now,
            ]);

        if ($studentNotifs->isNotEmpty()) {
            foreach ($studentNotifs->chunk(100) as $chunk) {
                Notification::insert($chunk->toArray());
            }

            // insert() writes no model events, so the bell's own email hook
            // never sees these rows. A team mention is always mailed; a plain
            // announcement only when it was posted as important or urgent.
            $mailable = $data['priority'] === 'normal'
                ? $studentNotifs->where('type', '!=', 'announcement')
                : $studentNotifs;

            $students = Student::whereIn('student_id', $mailable->pluck('student_id'))
                ->get()
                ->keyBy('student_id');

            foreach ($mailable as $row) {
                $student = $students->get($row['student_id']);

                if (! $student) {
                    continue;
                }

                if ($row['type'] === 'announcement') {
                    // The bell keeps a 120-letter preview; the email gets it all.
                    NotificationMailer::sendAnnouncement($student, $row['title'], $data['body'], $row['link'], $poster);
                } else {
                    NotificationMailer::send($student, $row['type'], $row['title'], $row['message'], $row['link']);
                }
            }
        }

        // Experts read these on their dashboard; the bell now tells them one was posted.
        if (array_intersect(['all', 'expert', 'panel'], $audience)) {
            CapstoneExpert::where('approved_status', true)
                ->where('expert_id', '!=', $announcement->admin_id)
                ->get()
                ->each(function (CapstoneExpert $expert) use ($data, $poster) {
                    $summary = Str::limit(strip_tags($data['body']), 120);

                    Notifier::toExpert($expert, 'announcement', $data['title'], $summary, route('instructor.dashboard'));

                    if ($data['priority'] !== 'normal') {
                        NotificationMailer::sendAnnouncement($expert, $data['title'], $data['body'], route('instructor.dashboard'), $poster);
                    }
                });
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
        $announcement->load('milestone');

        if ($announcement->milestone) {
            $announcement->milestone->delete();
        }

        $announcement->delete();

        return response()->json(['success' => true]);
    }

    public function verifySubmission(Request $request, Announcement $announcement, int $submission)
    {
        $data = $request->validate([
            'verified' => 'required|boolean',
        ]);

        $submission = StudentMilestone::findOrFail($submission);

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
