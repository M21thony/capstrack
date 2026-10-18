<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\MilestoneProgress;
use App\Support\NextStep;
use App\Support\StudentTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
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
        $defenseResults = $this->getDefenseResults($teamId);
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

        return view('student.dashboard', [
            'myTeam' => $myTeam,
            'nextStep' => NextStep::for($student),
            'leaderStudentId' => $leaderStudentId,
            'teamMembers' => $teamMembers,
            'overallProgress' => $progress->percent ?? 0,
            'progress' => $progress,
            'currentMilestone' => $progress ? $progress->milestones->first(fn ($m) => $m->completed < $m->total)?->milestone->name : null,
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
            'defenseResults' => $defenseResults,
        ]);
    }

    // Only counts grades a panelist has actually submitted (not drafts) -
    // a student never sees a panelist's in-progress working copy.
    private function getDefenseResults(?int $teamId): Collection
    {
        if (! $teamId) {
            return collect();
        }

        $schedules = DefenseSchedule::where('team_id', $teamId)->with('panels')->get();

        if ($schedules->isEmpty()) {
            return collect();
        }

        $schedules->load('grades');

        return $schedules->map(function (DefenseSchedule $schedule) {
            return (object) [
                'defense_type' => $schedule->defense_type,
                // A stage can be defended more than once. Both attempts are
                // listed - a team should see their redefense - so the number
                // is carried through to tell them apart.
                'attempt' => (int) $schedule->attempt,
                'schedule_date' => $schedule->schedule_date,
                'end_date' => $schedule->end_date,
                'assigned_count' => $schedule->panelSize(),
                // Scored or abstained: either way that panelist is done.
                'submitted_count' => $schedule->gradesInCount(),
                // Null until every panelist has graded - see panelAverage().
                'average_score' => $schedule->panelAverage(),
            ];
        })->sortBy(fn ($s) => [$s->schedule_date ?? now()->addYears(10), $s->attempt])->values();
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
        if (! $student->course || ! $student->year_section) {
            return collect();
        }

        $studentSectionLabel = Student::computeSectionLabel($student->course, $student->year_section);
        if (! $studentSectionLabel) {
            return collect();
        }

        return Announcement::with('admin', 'team', 'milestone')
            ->where('is_active', true)
            ->get()
            ->filter(function ($announcement) use ($studentSectionLabel, $student) {
                $admin = $announcement->admin;

                $audience = $announcement->target_audience;
                if (empty($audience)) {
                    $audience = ['all'];
                }
                $audience = (array) $audience;

                if (! in_array('all', $audience, true) && ! in_array('student', $audience, true)) {
                    return false;
                }

                // A team-targeted announcement is that team's business - it
                // names their defense or their milestone. Members see it
                // whatever their section; nobody else does. This used to fall
                // through to the section checks for non-members, which handed
                // one team's defense schedule to every student in the same
                // section.
                if ($announcement->team_id) {
                    $teamMemberIds = StudentSchoolYear::where('team_id', $announcement->team_id)
                        ->whereNotNull('student_id')
                        ->pluck('student_id')
                        ->toArray();

                    return in_array($student->student_id, $teamMemberIds, true);
                }

                // Case-insensitive section matching
                $studentSectionLower = strtolower($studentSectionLabel);

                // Extract explicit section targets (e.g., 'section:BSIT-3A')
                $targetSections = array_values(array_filter(
                    $audience,
                    fn ($a) => str_starts_with((string) $a, 'section:')
                ));
                $targetSections = array_map(fn ($s) => strtolower(substr((string) $s, 8)), $targetSections);

                if (! empty($targetSections)) {
                    return in_array($studentSectionLower, $targetSections, true);
                }

                if ($admin && ! empty($admin->sections)) {
                    $adminSectionsLower = array_map('strtolower', $admin->sections);

                    return in_array($studentSectionLower, $adminSectionsLower, true);
                }

                return true;
            })
            ->sortByDesc('created_at')
            ->take(10)
            ->values();
    }
}
