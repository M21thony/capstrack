<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Student\ResourceController;
use App\Models\Announcement;
use App\Models\ConsultationBooking;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use App\Support\AnnouncementAudience;
use App\Support\MilestoneProgress;
use App\Support\NextStep;
use App\Support\StudentResources;
use App\Support\StudentTeam;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, StudentResources $resources): View
    {
        /** @var Student $student */
        $student = auth()->user();

        $teamId = StudentTeam::idFor($student->student_id);

        [$myTeam, $teamMembers, $leaderStudentId] = $this->loadTeamData($student, $teamId);

        // Where the team is on its section's checklist - the same reading the
        // Progress Tracker shows - so the dashboard can say what is next.
        $team = $teamId ? Team::find($teamId) : null;
        $progress = $team?->checklistProgress();

        $announcements = $this->getAnnouncements($student);
        $nextDefense = $teamId
            ? DefenseSchedule::where('team_id', $teamId)->where('schedule_date', '>=', now()->startOfDay())->orderBy('schedule_date')->first()
            : null;

        $unreadNotifications = Notification::where('student_id', $student->student_id)
            ->where('is_read', false)
            ->latest('created_at')
            ->take(10)
            ->get();

        $unreadCount = Notification::where('student_id', $student->student_id)
            ->where('is_read', false)
            ->count();

        $files = $resources->files($student)->get();
        $newFiles = $files->filter(fn ($file) => $file->uploaded_at?->gt(now()->subWeek()));

        return view('student.dashboard', [
            'myTeam' => $myTeam,
            'nextStep' => NextStep::for($student),
            'leaderStudentId' => $leaderStudentId,
            'teamMembers' => $teamMembers,
            'overallProgress' => $progress->percent ?? 0,
            'progress' => $progress,
            'overdueItems' => $progress ? $progress->items->where('state', MilestoneProgress::OVERDUE)->sortBy('due_on')->values() : collect(),
            'nextItem' => $progress?->items
                ->reject(fn ($row) => in_array($row->state, MilestoneProgress::COMPLETE_STATES, true) || $row->state === MilestoneProgress::OVERDUE)
                ->filter(fn ($row) => $row->due_on)
                ->sortBy(fn ($row) => $row->due_on)
                ->first(),
            'nextDefense' => $nextDefense,
            // The team prints the panel's rating sheets, so the link shows only when there is a rubric to print.
            'sheetReady' => $nextDefense && GradingRubric::forStage($nextDefense->defense_type, $team?->program()),
            'announcements' => $announcements,
            'unreadNotifications' => $unreadNotifications,
            'unreadCount' => $unreadCount,
            'schoolYear' => Term::currentSchoolYear(),
            'section' => Student::computeSectionLabel($student->course, $student->year_section),
            'upcomingConsultations' => $this->upcomingConsultations($teamId, $myTeam),
            'recentActivity' => $this->recentActivity($student),
            'recentResources' => $files->take(2)->map(fn ($file) => (object) [
                'title' => $file->title ?: ($file->file_name ?: 'Untitled'),
                'category' => ResourceController::categoryLabel($file->category),
                // A file that cannot be downloaded opens on the Resources page instead.
                'url' => $file->is_downloadable !== false && ($file->drive_file_id || $file->external_url)
                    ? route('student.resources.download', $file)
                    : route('student.resources'),
            ]),
            'newResourceCount' => $newFiles->count(),
            'newResourceDate' => $newFiles->max('uploaded_at'),
        ]);
    }

    // The team's next three booked sessions, soonest first.
    private function upcomingConsultations(?int $teamId, ?Team $myTeam): Collection
    {
        if (! $teamId) {
            return collect();
        }

        $roles = $myTeam?->expertAssignments->pluck('engaged_role_name', 'expert_id') ?? collect();

        return ConsultationBooking::forTeam($teamId)
            ->with('schedule.expert')
            ->where('booking_status', 'scheduled')
            ->whereDate('session_date', '>=', today())
            ->get()
            ->sortBy(fn (ConsultationBooking $booking) => [$booking->session_date->toDateString(), $booking->schedule?->start_time])
            ->take(3)
            ->map(function (ConsultationBooking $booking) use ($roles) {
                $schedule = $booking->schedule;

                return (object) [
                    'date' => $booking->session_date,
                    'days' => (int) today()->diffInDays($booking->session_date),
                    'who' => collect([$schedule?->expert?->name, $roles[$schedule?->expert_id] ?? null])->filter()->implode(' · '),
                    'when' => collect([
                        $schedule ? Carbon::parse($schedule->start_time)->format('g:i').' – '.Carbon::parse($schedule->end_time)->format('g:i A') : null,
                        $booking->topicsLabel(),
                    ])->filter()->implode(' · '),
                ];
            })
            ->values();
    }

    // The student's own latest notifications, read ones included. The icon
    // goes by kind: dates and announcements orange, done green, documents
    // blue, everything about the team purple.
    private function recentActivity(Student $student): Collection
    {
        return Notification::where('student_id', $student->student_id)
            ->latest('created_at')
            ->take(3)
            ->get()
            ->map(function (Notification $notification) {
                [$tone, $icon] = match (true) {
                    $notification->type === 'announcement' => ['orange', 'megaphone'],
                    str_contains($notification->type, 'defense'), str_contains($notification->type, 'consultation') => ['orange', 'cal'],
                    in_array($notification->type, ['team_verified', 'title_confirmation'], true) => ['green', 'check'],
                    str_contains($notification->type, 'document'), str_contains($notification->type, 'paper') => ['blue', 'doc'],
                    default => ['purple', 'team'],
                };

                return (object) [
                    'text' => $notification->title ?: strip_tags((string) $notification->message),
                    'at' => $notification->created_at,
                    'tone' => $tone,
                    'icon' => $icon,
                ];
            });
    }

    private function loadTeamData(Student $student, ?int $teamId): array
    {
        if (! $teamId) {
            return [null, collect(), null];
        }

        $myTeam = Team::with('expertAssignments.expert', 'expertAssignments.role')->find($teamId);
        $leaderStudentId = $myTeam?->leader_id;

        if (! $myTeam) {
            return [null, collect(), null];
        }

        $teamMembers = Student::join('student_s_y', 'students.student_id', '=', 'student_s_y.student_id')
            ->where('student_s_y.team_id', $teamId)
            ->get()
            ->sortBy(function ($member) use ($leaderStudentId, $student) {
                if ($member->student_id === $leaderStudentId) {
                    return 0;
                }
                if ($member->student_id === $student->student_id) {
                    return 1;
                }

                return 2;
            })
            ->values();

        return [$myTeam, $teamMembers, $leaderStudentId];
    }

    // The enrolment row was passed in and never read - the section comes
    // from the student's own course and year.
    private function getAnnouncements(Student $student): Collection
    {
        if (! Student::computeSectionLabel($student->course, $student->year_section)) {
            return collect();
        }

        return Announcement::with('admin', 'team', 'milestone')
            ->where('is_active', true)
            ->get()
            ->filter(fn (Announcement $announcement) => AnnouncementAudience::reachesStudent($announcement, $student))
            ->sortByDesc('created_at')
            ->take(10)
            ->values();
    }
}
