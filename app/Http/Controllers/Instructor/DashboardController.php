<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\ConsultationSchedule;
use App\Models\DefensePanel;
use App\Models\PanelGrade;
use App\Models\TeamExpertAssignment;
use App\Services\RecipientResolver;
use App\Support\TitleRevision;
use Carbon\Carbon;

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

        // New titles waiting for this expert to confirm as a team's adviser:
        // the same list Team Assignments shows, counted.
        $resolver = app(RecipientResolver::class);
        $titlesToApprove = CapstonePreProposal::where('adviser_status', TitleRevision::PENDING)
            ->whereNull('retired_at')
            ->get(['id', 'team_id'])
            ->filter(fn ($p) => $resolver->resolveTeamRoleExpert((int) $p->team_id, 'adviser') === (int) $expertId)
            ->count();

        // Defenses this expert sat on (the date has come) with no grade of theirs submitted yet.
        $panels = DefensePanel::where('expert_id', $expertId)->with(['schedule.team'])->get()
            ->filter(fn ($panel) => $panel->schedule?->schedule_date !== null);
        $submitted = PanelGrade::where('expert_id', $expertId)->where('status', 'submitted')->pluck('defense_id')->all();
        $gradesToSubmit = $panels
            ->filter(fn ($panel) => $panel->schedule->schedule_date->lte(now()) && ! in_array($panel->defense_id, $submitted, true))
            ->unique('defense_id')->count();

        // What is coming up, soonest first: defenses they sit on and consultation slots.
        $upcoming = $panels
            ->filter(fn ($panel) => $panel->schedule->schedule_date->gte(now()->startOfDay()))
            ->unique('defense_id')
            ->map(fn ($panel) => (object) [
                'when' => $panel->schedule->schedule_date,
                'timed' => $panel->schedule->schedule_date->format('H:i') !== '00:00',
                'what' => $panel->schedule->defense_type,
                'who' => $panel->schedule->team?->team_name,
                'where' => $panel->schedule->venue,
                'tag' => $panel->panel_role ?: 'Panel',
            ])
            ->concat(ConsultationSchedule::where('expert_id', $expertId)
                ->where('specific_date', '>=', now()->toDateString())
                ->withCount('bookings')
                ->orderBy('specific_date')->orderBy('start_time')
                ->take(10)->get()
                ->map(fn ($slot) => (object) [
                    'when' => Carbon::parse($slot->specific_date.' '.($slot->start_time ?: '00:00')),
                    'timed' => (bool) $slot->start_time,
                    'what' => 'Consultation',
                    'who' => $slot->bookings_count ? $slot->bookings_count.' booked' : 'No bookings yet',
                    'where' => null,
                    'tag' => 'Consultation',
                ]))
            ->sortBy('when')->take(6)->values();

        return view('instructor.dashboard', compact(
            'pendingApplications',
            'pendingApplicationsNew',
            'titlesToApprove',
            'docsForReview',
            'oldestDocReview',
            'gradesToSubmit',
            'upcomingConsults',
            'teamsAdvised',
            'announcements',
            'upcoming',
            'expert',
        ));
    }
}
