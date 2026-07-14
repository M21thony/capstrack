<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\FinancialRecord;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Student $student */
        $student = auth()->user();

        $studentSy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $teamId = $studentSy?->team_id;

        $myTeam = null;
        $teamMembers = collect();
        $leaderStudentId = null;

        if ($teamId) {
            $myTeam = Team::with('expertAssignments.expert', 'expertAssignments.role')->find($teamId);
            $leaderStudentId = $myTeam?->leader_id;
            if ($myTeam) {
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
                    })->values();
            }
        }

        // Count of team groups: teams in the same course, year, and section as the current student
        $teamGroupsCount = StudentSchoolYear::join('students', 'student_s_y.student_id', '=', 'students.student_id')
            ->where('students.course', $student->course)
            ->where('students.year_section', $student->year_section)
            ->whereNotNull('student_s_y.team_id')
            ->distinct('student_s_y.team_id')
            ->count('student_s_y.team_id');

        // Dynamic count of documents submitted by the student's team or student themselves
        $docsSubmittedCount = 0;
        try {
            $docsSubmittedCount = DB::table('capstone_documents')
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
            $docsSubmittedCount = 0;
        }

        // Dynamic count of pending reviews
        $pendingReviewsCount = 0;
        if ($docsSubmittedCount > 0) {
            try {
                $pendingReviewsCount = DB::table('capstone_document_review')
                    ->whereIn('file_id', function ($query) use ($student, $teamId) {
                        $query->select('file_id')
                            ->from('capstone_documents')
                            ->where('student_id', $student->student_id);
                        if ($teamId) {
                            $query->orWhereIn('student_id', function ($subQuery) use ($teamId) {
                                $subQuery->select('student_id')
                                    ->from('student_s_y')
                                    ->where('team_id', $teamId);
                            });
                        }
                    })
                    ->where('review_status', 'pending')
                    ->count();
            } catch (\Exception $e) {
                $pendingReviewsCount = 0;
            }
        }

        // Fetch overall progress (defaulting to 0% if no progress logged yet)
        $overallProgress = 0;
        if ($teamId) {
            try {
                $progressRecord = DB::table('capstone_progress')
                    ->where('team_id', $teamId)
                    ->first();
                if ($progressRecord && isset($progressRecord->progress_percentage)) {
                    $overallProgress = (int) $progressRecord->progress_percentage;
                }
            } catch (\Exception $e) {
                $overallProgress = 0;
            }
        }

        // Recent expense receipts for dashboard widget
        $recentExpenses = FinancialRecord::with('expensePost')
            ->where('student_id', $student->student_id)
            ->orderByDesc('uploaded_at')
            ->take(5)
            ->get();

        // Build recent activities from activity_log
        $activities = ActivityLog::where('student_id', $student->student_id)
            ->latest('created_at')
            ->take(50)
            ->get()
            ->map(function ($log) {
                $icon = match ($log->type) {
                    'team_created' => ['bg' => 'var(--purple-bg)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--purple)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>'],
                    'team_joined' => ['bg' => 'var(--purple-bg)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--purple)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>'],
                    'team_left' => ['bg' => 'rgba(239,68,68,0.1)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:#ef4444"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>'],
                    'team_confirmed' => ['bg' => 'rgba(22,163,74,0.1)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:#16a34a"><polyline points="20 6 9 17 4 12"/></svg>'],
                    'member_removed' => ['bg' => 'rgba(239,68,68,0.1)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:#ef4444"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'],
                    'leadership_transferred' => ['bg' => 'rgba(251,191,36,0.15)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:#fbbf24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
                    'request_accepted' => ['bg' => 'rgba(22,163,74,0.1)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:#16a34a"><polyline points="20 6 9 17 4 12"/></svg>'],
                    'invitation_sent' => ['bg' => 'var(--accent-glow)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent)"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'],
                    'post_created' => ['bg' => 'var(--accent-glow)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent)"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="m18.5 2.5 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'],
                    'post_updated' => ['bg' => 'var(--amber-bg)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--amber)"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>'],
                    'request_sent' => ['bg' => 'var(--accent-glow)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent)"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'],
                    'invitation_declined' => ['bg' => 'rgba(239,68,68,0.1)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:#ef4444"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'],
                    'request_declined' => ['bg' => 'rgba(239,68,68,0.1)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:#ef4444"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'],
                    'post_deleted' => ['bg' => 'rgba(239,68,68,0.1)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:#ef4444"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>'],
                    'team_status_changed' => ['bg' => 'var(--amber-bg)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--amber)"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>'],
                    'member_count_changed' => ['bg' => 'var(--purple-bg)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--purple)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
                    'team_auto_closed' => ['bg' => 'rgba(239,68,68,0.1)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:#ef4444"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'],
                    default => ['bg' => 'var(--accent-glow)', 'svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent)"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
                };

                return (object) [
                    'text' => $log->description,
                    'time' => $log->created_at->diffForHumans(),
                    'icon_bg' => $icon['bg'],
                    'icon_svg' => $icon['svg'],
                    'timestamp' => $log->created_at->timestamp,
                ];
            });

        // Unread notifications for the notification bell
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
            'unreadNotifications' => $unreadNotifications,
            'unreadCount' => $unreadCount,
        ]);
    }
}
