<?php

namespace App\Http\Controllers;

use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use Carbon\Carbon;
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

        // Determine the leader of the team (student with a 'build_a_team' recruitment goal)
        $leaderStudentId = null;
        if ($teamId) {
            $leaderStudentId = Recruitment::where('recruitment_goal', 'build_a_team')
                ->whereIn('student_id', function ($q) use ($teamId) {
                    $q->select('student_id')
                        ->from('student_s_y')
                        ->where('team_id', $teamId);
                })
                ->value('student_id');
        }

        $myTeam = null;
        $teamMembers = collect();

        if ($teamId) {
            $myTeam = Team::find($teamId);
            if ($myTeam) {
                $teamMembers = Student::join('student_s_y', 'students.student_id', '=', 'student_s_y.student_id')
                    ->where('student_s_y.team_id', $teamId)
                    ->get();
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

        // Build recent activities dynamically — only from real DB timestamps
        $activities = collect();

        // 1. Team creation — team.created_at is a real timestamp
        if ($myTeam && $myTeam->created_at) {
            $teamCreatedAt = Carbon::parse($myTeam->created_at);
            $activities->push((object) [
                'text' => 'Team <strong>'.e($myTeam->team_name).'</strong> was created',
                'time' => $teamCreatedAt->diffForHumans(),
                'icon_bg' => 'var(--purple-bg)',
                'icon_svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--purple)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>',
                'timestamp' => $teamCreatedAt->timestamp,
            ]);
        }

        // 2. Team member joins — use team_invitations.updated_at (when accepted) as real join time
        if ($myTeam && $teamMembers->isNotEmpty()) {
            foreach ($teamMembers as $member) {
                if ($member->student_id === $student->student_id) {
                    continue;
                }

                // Find the accepted invitation for this member to this team
                $acceptedInvite = TeamInvitation::where('team_id', $myTeam->team_id)
                    ->where('status', 'accepted')
                    ->where(function ($q) use ($member) {
                        $q->where('receiver_id', $member->student_id)
                            ->orWhere('sender_id', $member->student_id);
                    })
                    ->latest('updated_at')
                    ->first();

                if ($acceptedInvite && $acceptedInvite->updated_at) {
                    $joinedAt = $acceptedInvite->updated_at;
                    $activities->push((object) [
                        'text' => '<strong>'.e($member->f_name.' '.$member->l_name).'</strong> joined the team',
                        'time' => $joinedAt->diffForHumans(),
                        'icon_bg' => 'var(--purple-bg)',
                        'icon_svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--purple)"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>',
                        'timestamp' => $joinedAt->timestamp,
                    ]);
                }
            }
        }

        // 3. Team invitations & join requests — team_invitations.created_at is accurate
        $myInvitations = TeamInvitation::with(['sender', 'receiver', 'team'])
            ->where(function ($query) use ($student, $teamId) {
                $query->where('sender_id', $student->student_id)
                    ->orWhere('receiver_id', $student->student_id);
                if ($teamId) {
                    $query->orWhere('team_id', $teamId);
                }
            })
            ->latest()
            ->take(10)
            ->get();

        foreach ($myInvitations as $invite) {
            // For accepted/declined invites, updated_at reflects when the status changed
            $eventTime = match ($invite->status) {
                'accepted', 'declined' => $invite->updated_at ?? $invite->created_at,
                default => $invite->created_at,
            };

            if (! $eventTime) {
                continue;
            }

            if ($invite->type === 'invite') {
                if ($invite->sender_id === $student->student_id) {
                    $text = 'You invited <strong>'.e($invite->receiver->f_name.' '.$invite->receiver->l_name).'</strong> to join the team';
                } else {
                    $text = '<strong>'.e($invite->sender->f_name.' '.$invite->sender->l_name).'</strong> invited you to join <strong>'.e($invite->team->team_name ?? 'a team').'</strong>';
                }
            } else {
                if ($invite->sender_id === $student->student_id) {
                    $text = 'You requested to join team <strong>'.e($invite->team->team_name ?? 'a team').'</strong>';
                } else {
                    $text = '<strong>'.e($invite->sender->f_name.' '.$invite->sender->l_name).'</strong> requested to join your team';
                }
            }

            if ($invite->status === 'accepted') {
                $text .= ' · <span style="color:var(--green);font-size:10px;font-weight:700">Accepted</span>';
            } elseif ($invite->status === 'declined') {
                $text .= ' · <span style="color:var(--amber);font-size:10px;font-weight:700">Declined</span>';
            }

            $activities->push((object) [
                'text' => $text,
                'time' => $eventTime->diffForHumans(),
                'icon_bg' => 'var(--accent-glow)',
                'icon_svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent)"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
                'timestamp' => $eventTime->timestamp,
            ]);
        }

        // 4. Document review annotations — pdf_remarks.created_at is a real timestamp
        $remarks = collect();
        try {
            $remarks = DB::table('pdf_remarks')
                ->join('capstone_document_review', 'pdf_remarks.review_id', '=', 'capstone_document_review.review_id')
                ->join('capstone_documents', 'capstone_document_review.file_id', '=', 'capstone_documents.file_id')
                ->join('capstone_experts', 'pdf_remarks.expert_id', '=', 'capstone_experts.expert_id')
                ->where('capstone_documents.student_id', $student->student_id)
                ->select(
                    'pdf_remarks.created_at as remarked_at',
                    'capstone_experts.first_name',
                    'capstone_experts.last_name',
                    'capstone_documents.file_name',
                    DB::raw('COUNT(*) as annotation_count')
                )
                ->groupBy('pdf_remarks.review_id', 'pdf_remarks.created_at', 'capstone_experts.first_name', 'capstone_experts.last_name', 'capstone_documents.file_name')
                ->orderByDesc('pdf_remarks.created_at')
                ->take(3)
                ->get();
        } catch (\Exception $e) {
            // Table not available in this environment
        }

        foreach ($remarks as $remark) {
            $remarkedAt = Carbon::parse($remark->remarked_at);
            $activities->push((object) [
                'text' => '<strong>'.e($remark->first_name.' '.$remark->last_name).'</strong> left '.$remark->annotation_count.' annotation(s) on <strong>'.e($remark->file_name).'</strong>',
                'time' => $remarkedAt->diffForHumans(),
                'icon_bg' => 'var(--accent-glow)',
                'icon_svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--accent)"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
                'timestamp' => $remarkedAt->timestamp,
            ]);
        }

        // 5. Consultation bookings — use scheduled available_date+start_time as event time
        $bookings = collect();
        try {
            $bookings = DB::table('consultation_booking')
                ->join('consultation_schedule', 'consultation_booking.schedule_id', '=', 'consultation_schedule.schedule_id')
                ->join('capstone_experts', 'consultation_schedule.expert_id', '=', 'capstone_experts.expert_id')
                ->where('consultation_booking.student_id', $student->student_id)
                ->select(
                    'consultation_schedule.available_date',
                    'consultation_schedule.start_time',
                    'capstone_experts.first_name',
                    'capstone_experts.last_name',
                    'consultation_booking.booking_status'
                )
                ->orderByDesc('consultation_schedule.available_date')
                ->get();
        } catch (\Exception $e) {
            // Table not available in this environment
        }

        foreach ($bookings as $booking) {
            $scheduledAt = Carbon::parse($booking->available_date.' '.$booking->start_time);
            $isPast = $scheduledAt->isPast();
            $activities->push((object) [
                'text' => '<strong>Consultation</strong> '.($isPast ? 'held' : 'scheduled').' with '.e($booking->first_name.' '.$booking->last_name).' on '.$scheduledAt->format('M j, Y g:i A'),
                'time' => $scheduledAt->diffForHumans(),
                'icon_bg' => 'var(--amber-bg)',
                'icon_svg' => '<svg viewBox="0 0 24 24" style="stroke:var(--amber)"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
                'timestamp' => $scheduledAt->timestamp,
            ]);
        }

        // Sort activities and take 5
        $sortedActivities = $activities->sortByDesc('timestamp')->take(5);

        return view('student.dashboard', [
            'myTeam' => $myTeam,
            'leaderStudentId' => $leaderStudentId,
            'teamMembers' => $teamMembers,
            'teamGroupsCount' => $teamGroupsCount,
            'docsSubmitted' => $docsSubmittedCount,
            'pendingReviews' => $pendingReviewsCount,
            'overallProgress' => $overallProgress,
            'activities' => $sortedActivities,
        ]);
    }
}
