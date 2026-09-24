<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\CapstoneDocumentReview;
use App\Models\DefenseSchedule;
use App\Models\FinancialRecord;
use App\Models\Milestone;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentMilestone;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\StudentTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Student $student */
        $student = auth()->user();

        $teamId = StudentTeam::idFor($student->student_id);

        [$myTeam, $teamMembers, $leaderStudentId] = $this->loadTeamData($student, $teamId);

        $teamGroupsCount = $this->getTeamGroupsCount($student);

        $docsSubmittedCount = $this->getDocsSubmittedCount($student, $teamId);
        $pendingReviewsCount = $this->getPendingReviewsCount($student, $teamId);
        $overallProgress = $this->getOverallProgress($teamId);

        $activities = $this->buildActivities($student, $teamId);
        $milestones = $this->getMilestones($teamId);
        $announcements = $this->getAnnouncements($student);
        $defenseResults = $this->getDefenseResults($teamId);

        // Recent expense receipts for dashboard widget (from
        // complete-grading-rubrics-crud's financial dashboard sync work).
        $recentExpenses = FinancialRecord::with('expensePost')
            ->where('student_id', $student->student_id)
            ->orderByDesc('uploaded_at')
            ->take(5)
            ->get();

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
            'leaderStudentId' => $leaderStudentId,
            'teamMembers' => $teamMembers,
            'teamGroupsCount' => $teamGroupsCount,
            'docsSubmitted' => $docsSubmittedCount,
            'pendingReviews' => $pendingReviewsCount,
            'overallProgress' => $overallProgress,
            'recentExpenses' => $recentExpenses,
            'activities' => $activities,
            'announcements' => $announcements,
            'milestones' => $milestones,
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

    private function getTeamGroupsCount(Student $student): int
    {
        return StudentSchoolYear::join('students', 'student_s_y.student_id', '=', 'students.student_id')
            ->where('students.course', $student->course)
            ->where('students.year_section', $student->year_section)
            ->whereNotNull('student_s_y.team_id')
            ->distinct('student_s_y.team_id')
            ->count('student_s_y.team_id');
    }

    private function getDocsSubmittedCount(Student $student, ?int $teamId): int
    {
        try {
            return DB::table('capstone_documents')
                ->where(function ($query) use ($student, $teamId) {
                    $query->where('student_id', $student->student_id);
                    if ($teamId) {
                        $query->orWhereExists(function ($subQuery) use ($teamId) {
                            $subQuery->select(DB::raw(1))
                                ->from('student_s_y')
                                ->whereColumn('student_s_y.student_id', 'capstone_documents.student_id')
                                ->where('student_s_y.team_id', $teamId);
                        });
                    }
                })
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getPendingReviewsCount(Student $student, ?int $teamId): int
    {
        try {
            return DB::table('capstone_document_review')
                ->whereIn('file_id', function ($query) use ($student, $teamId) {
                    $query->select('file_id')->from('capstone_documents')
                        ->where('student_id', $student->student_id);
                    if ($teamId) {
                        $query->orWhereIn('student_id', function ($subQuery) use ($teamId) {
                            $subQuery->select('student_id')->from('student_s_y')
                                ->where('team_id', $teamId);
                        });
                    }
                })
                // 'pending' is never written, so this was always zero. This is a
                // DB::table() builder, not Eloquent, so it cannot use the model's
                // scope - and the catch below would have swallowed the resulting
                // error and gone on returning zero.
                ->whereIn('review_status', [
                    CapstoneDocumentReview::AWAITING,
                    CapstoneDocumentReview::IN_PROGRESS,
                ])
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getOverallProgress(?int $teamId): int
    {
        if (! $teamId) {
            return 0;
        }

        $titles = ['Capstone Team', 'Pre-Proposal', 'Title Defense', 'Pre-Oral Defense', 'Final Defense'];

        // Get unique milestone IDs by title (take the first record per title)
        $milestoneIds = Milestone::whereIn('title', $titles)
            ->orderBy('id')
            ->get()
            ->groupBy('title')
            ->map(fn ($group) => $group->first()->id)
            ->values();

        if ($milestoneIds->isEmpty()) {
            return 0;
        }

        $doneCount = StudentMilestone::where('team_id', $teamId)
            ->whereIn('milestone_id', $milestoneIds)
            ->where('is_verified', true)
            ->distinct('milestone_id')
            ->count('milestone_id');

        return (int) round(($doneCount / count($titles)) * 100);
    }

    private function buildActivities(Student $student, ?int $teamId): Collection
    {
        $activities = ActivityLog::where('student_id', $student->student_id)
            ->latest('created_at')
            ->take(50)
            ->get()
            // The icon and colour come from App\Support\ActivityIcons, by type,
            // the same as on the CaPSA and Expert dashboards.
            ->map(fn ($log) => [
                'type' => $log->type,
                'text' => $log->description,
                'at' => $log->created_at,
            ]);

        if ($teamId) {
            $announcementActivities = Announcement::with('admin')
                ->where('team_id', $teamId)
                ->where('is_active', true)
                ->latest('created_at')
                ->take(10)
                ->get()
                ->map(fn ($ann) => [
                    'type' => 'announcement',
                    'text' => 'Your team was mentioned in <strong>'.e($ann->title).'</strong> <span style="color:var(--text3)">by '.e($ann->admin?->name ?? 'your CaPSA').'</span>',
                    'at' => $ann->created_at,
                ]);

            $activities = $activities->concat($announcementActivities)
                ->sortByDesc(fn ($a) => $a['at']?->timestamp ?? 0)
                ->take(50)
                ->values();
        }

        return $activities;
    }

    private function getMilestones(?int $teamId): Collection
    {
        if (! $teamId) {
            return collect();
        }

        $titles = ['Capstone Team', 'Pre-Proposal', 'Title Defense', 'Pre-Oral Defense', 'Final Defense'];

        // Get one milestone per title (take the first record per title)
        $milestones = Milestone::whereIn('title', $titles)
            ->orderBy('id')
            ->get()
            ->groupBy('title')
            ->map(fn ($group) => $group->first())
            ->values();

        // Get completed (verified) milestone IDs for this team
        $verifiedIds = StudentMilestone::where('team_id', $teamId)
            ->whereIn('milestone_id', $milestones->pluck('id'))
            ->where('is_verified', true)
            ->pluck('milestone_id')
            ->unique()
            ->toArray();

        // Get unverified (pending) milestone IDs for this team
        $pendingIds = StudentMilestone::where('team_id', $teamId)
            ->whereIn('milestone_id', $milestones->pluck('id'))
            ->where('is_verified', false)
            ->pluck('milestone_id')
            ->unique()
            ->toArray();

        // Annotate each milestone with the actual status based on team submission
        $milestones->each(function ($ms) use ($verifiedIds, $pendingIds) {
            if (in_array($ms->id, $verifiedIds, true)) {
                $ms->status = 'completed';
            } elseif (in_array($ms->id, $pendingIds, true)) {
                $ms->status = 'in_progress';
            } elseif (! $ms->status || $ms->status === 'todo') {
                $ms->status = 'todo';
            }
        });

        return $milestones->sortBy(function ($ms) use ($titles) {
            return array_search($ms->title, $titles, true);
        })->values();
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
