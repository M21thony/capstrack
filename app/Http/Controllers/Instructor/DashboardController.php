<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\ConsultationSchedule;
use App\Models\TeamExpertAssignment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        /** @var CapstoneExpert $expert */
        $expert = auth()->guard('expert')->user();
        $expertId = $expert->expert_id;

        $pendingApplications = TeamExpertAssignment::where('expert_id', $expertId)
            ->where('status', 'pending')
            ->count();

        $pendingApplicationsNew = TeamExpertAssignment::where('expert_id', $expertId)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        $docsForReview = CapstoneDocumentReview::whereHas('document', function ($q) use ($expertId) {
            $q->whereIn('file_id', function ($q) use ($expertId) {
                $q->select('file_id')
                    ->from('capstone_documents')
                    ->whereIn('student_id', function ($q) use ($expertId) {
                        $q->select('student_id')
                            ->from('student_s_y')
                            ->whereIn('team_id', function ($q) use ($expertId) {
                                $q->select('team_id')
                                    ->from('capstone_expert_application')
                                    ->where('expert_id', $expertId)
                                    ->where('status', 'approved');
                            });
                    });
            });
        })
            // 'pending' is not a value this column ever holds, so this counted
            // nothing. What the chip means is "still on my plate".
            ->awaitingReview()
            ->where('expert_id', $expertId)
            ->count();

        $oldestDocReview = CapstoneDocumentReview::whereHas('document', function ($q) use ($expertId) {
            $q->whereIn('file_id', function ($q) use ($expertId) {
                $q->select('file_id')->from('capstone_documents')
                    ->whereIn('student_id', function ($q) use ($expertId) {
                        $q->select('student_id')->from('student_s_y')
                            ->whereIn('team_id', function ($q) use ($expertId) {
                                $q->select('team_id')->from('capstone_expert_application')
                                    ->where('expert_id', $expertId)->where('status', 'approved');
                            });
                    });
            });
        })
            ->awaitingReview()
            ->where('expert_id', $expertId)
            // Oldest by when it arrived, not by reviewed_at: a document still
            // waiting has not been reviewed, so that column is null for every
            // row this can return and sorted on nothing.
            ->orderBy('created_at')
            ->first();

        $upcomingConsults = ConsultationSchedule::where('expert_id', $expertId)
            ->where('is_available', true)
            ->where('specific_date', '>=', now()->toDateString())
            ->count();

        $teamsAdvised = TeamExpertAssignment::where('expert_id', $expertId)
            ->where('status', 'approved')
            ->distinct('team_id')
            ->count('team_id');

        $announcements = Announcement::with('admin', 'team', 'milestone')
            ->where('is_active', true)
            ->get()
            ->filter(function ($announcement) {
                $audience = $announcement->target_audience;
                if (empty($audience)) {
                    return true;
                }

                $audience = (array) $audience;

                return in_array('all', $audience)
                    || in_array('expert', $audience)
                    || in_array('panel', $audience);
            })
            ->sortByDesc('created_at')
            ->take(5)
            ->values();

        $consultations = ConsultationSchedule::where('expert_id', $expertId)
            ->where('specific_date', '>=', now()->toDateString())
            ->orderBy('specific_date')
            ->orderBy('start_time')
            ->take(5)
            ->get();

        $recentActivity = $this->buildRecentActivity($expertId);

        return view('instructor.dashboard', compact(
            'pendingApplications',
            'pendingApplicationsNew',
            'docsForReview',
            'oldestDocReview',
            'upcomingConsults',
            'teamsAdvised',
            'announcements',
            'consultations',
            'recentActivity',
            'expert',
        ));
    }

    private function buildRecentActivity(int $expertId): array
    {
        $activities = [];

        $teamIds = TeamExpertAssignment::where('expert_id', $expertId)
            ->where('status', 'approved')
            ->pluck('team_id');

        if ($teamIds->isNotEmpty()) {
            $submissions = DB::table('capstone_documents')
                ->join('student_s_y', 'capstone_documents.student_id', '=', 'student_s_y.student_id')
                ->join('team', 'student_s_y.team_id', '=', 'team.team_id')
                ->whereIn('student_s_y.team_id', $teamIds)
                ->select('team.team_name', 'capstone_documents.file_name', 'capstone_documents.created_at')
                ->latest('capstone_documents.created_at')
                ->take(10)
                ->get();

            foreach ($submissions as $s) {
                $activities[] = [
                    'type' => 'document_submitted',
                    'text' => '<strong>'.e($s->team_name).'</strong> sent <strong>'.e($s->file_name).'</strong> for review',
                    'at' => $s->created_at ? Carbon::parse($s->created_at) : null,
                ];
            }
        }

        $reviewedDocs = DB::table('capstone_document_review')
            ->join('capstone_documents', 'capstone_document_review.file_id', '=', 'capstone_documents.file_id')
            ->join('student_s_y', 'capstone_documents.student_id', '=', 'student_s_y.student_id')
            ->join('team', 'student_s_y.team_id', '=', 'team.team_id')
            ->where('capstone_document_review.expert_id', $expertId)
            // Was 'approved', which nothing writes - the activity feed had a
            // permanently empty section. 'Reviewed' is what finishing sets.
            ->where('capstone_document_review.review_status', CapstoneDocumentReview::REVIEWED)
            ->select('team.team_name', 'capstone_document_review.reviewed_at')
            ->latest('capstone_document_review.reviewed_at')
            ->take(5)
            ->get();

        foreach ($reviewedDocs as $r) {
            $activities[] = [
                'type' => 'document_reviewed',
                'text' => 'You finished reviewing <strong>'.e($r->team_name).'</strong>\'s document',
                'at' => $r->reviewed_at ? Carbon::parse($r->reviewed_at) : null,
            ];
        }

        // Newest first by the time itself. It used to sort on "3 days ago"
        // as text, which is alphabetical order, not time order.
        return collect($activities)
            ->filter(fn ($a) => $a['at'] !== null)
            ->sortByDesc(fn ($a) => $a['at']->timestamp)
            ->values()
            ->take(10)
            ->all();
    }
}
