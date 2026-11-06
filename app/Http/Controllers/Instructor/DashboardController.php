<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\ConsultationBooking;
use App\Models\DefensePanel;
use App\Models\Notification;
use App\Models\PanelGrade;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Models\Term;
use App\Services\RecipientResolver;
use App\Support\DashboardStats;
use App\Support\StudentTeam;
use App\Support\TitleRevision;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        // the same list Team Applications shows, counted.
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
        $toGrade = $panels
            ->filter(fn ($panel) => $panel->schedule->schedule_date->lte(now()) && ! in_array($panel->defense_id, $submitted, true))
            ->unique('defense_id');

        // What is coming up, soonest first: defenses they sit on and booked consultations.
        $upcoming = $panels
            ->filter(fn ($panel) => $panel->schedule->schedule_date->gte(now()->startOfDay()))
            ->unique('defense_id')
            ->map(fn ($panel) => (object) [
                'when' => $panel->schedule->schedule_date,
                'timed' => $panel->schedule->schedule_date->format('H:i') !== '00:00',
                'what' => $panel->schedule->defense_type,
                'who' => $panel->schedule->team?->team_name,
                'where' => $panel->schedule->venue,
            ])
            ->concat(ConsultationBooking::where('booking_status', 'scheduled')
                ->whereHas('schedule', fn ($q) => $q->where('expert_id', $expertId))
                ->whereDate('session_date', '>=', today())
                ->with('schedule', 'team', 'student')
                ->get()
                ->map(fn ($booking) => (object) [
                    'when' => Carbon::parse($booking->session_date->toDateString().' '.($booking->schedule->start_time ?: '00:00')),
                    'timed' => (bool) $booking->schedule->start_time,
                    'what' => $booking->topicsLabel(),
                    'who' => $booking->team?->team_name ?? $booking->student?->name,
                    'where' => null,
                ]))
            ->sortBy('when')->take(3)->values();

        return view('instructor.dashboard', [
            'stats' => app(DashboardStats::class)->expert($expert),
            'schoolYear' => Term::currentSchoolYear(),
            'docsForReview' => $docsForReview,
            'gradesToSubmit' => $toGrade->count(),
            'announcements' => $announcements,
            'upcoming' => $upcoming,
            'tasks' => $this->tasks($expertId, $oldestDocReview, $toGrade, $pendingApplications, $titlesToApprove),
            'recentActivity' => $this->recentActivity($expertId),
            'expert' => $expert,
        ]);
    }

    /**
     * What to do next, the oldest of each kind. The tiles and My Workload carry
     * the totals.
     *
     * @return list<array{icon: string, tint: string, title: string, meta: string, link: string}>
     */
    private function tasks(int $expertId, ?CapstoneDocumentReview $review, Collection $toGrade, int $applications, int $titles): array
    {
        $tasks = [];

        if ($document = $review?->document) {
            $team = Team::where('team_id', $document->team_id ?? StudentTeam::idFor($document->student_id))->value('team_name');
            $tasks[] = [
                'icon' => 'doc',
                'tint' => 'blue',
                'title' => 'Review '.$team,
                'meta' => ($document->deliverable?->title ?? $document->chapter ?: $document->file_name).' · Waiting '.$review->created_at?->diffForHumans(null, true),
                'link' => route('instructor.docreview'),
            ];
        }

        if ($panel = $toGrade->sortBy(fn ($panel) => $panel->schedule->schedule_date)->first()) {
            $tasks[] = [
                'icon' => 'check',
                'tint' => 'green',
                'title' => 'Grade '.$panel->schedule->team?->team_name,
                'meta' => $panel->schedule->defense_type.' · Held '.$panel->schedule->schedule_date->format('M j'),
                'link' => route('instructor.grading'),
            ];
        }

        if ($applications) {
            $request = TeamExpertAssignment::where('expert_id', $expertId)->where('status', 'pending')->with('team')->latest()->first();
            $tasks[] = [
                'icon' => 'userplus',
                'tint' => 'purple',
                'title' => 'Team request: '.$request->team?->team_name,
                'meta' => collect([
                    'Asked you'.($request->engaged_role_name ? ' as '.$request->engaged_role_name : ''),
                    $request->created_at?->format('M j'),
                    $applications > 1 ? $applications.' waiting' : null,
                ])->filter()->implode(' · '),
                'link' => route('instructor.teams', ['tab' => 'applications']),
            ];
        }

        if ($titles) {
            $tasks[] = [
                'icon' => 'checkc',
                'tint' => 'purple',
                'title' => Str::plural('Title', $titles).' to approve',
                'meta' => $titles.' waiting for you as adviser',
                'link' => route('instructor.teams', ['tab' => 'applications']),
            ];
        }

        return array_slice($tasks, 0, 3);
    }

    /**
     * The latest things that happened to this expert, from their notifications.
     *
     * @return Collection<int, array{icon: string, tint: string, text: string, at: string}>
     */
    private function recentActivity(int $expertId): Collection
    {
        $kinds = [
            'expert_application' => ['userplus', 'purple'],
            'consultation_booked' => ['chat', 'teal'],
            'consultation_cancelled' => ['chat', 'teal'],
            'consultation_reminder' => ['chat', 'teal'],
            'document_submitted' => ['doc', 'blue'],
            'paper_to_check' => ['doc', 'blue'],
            'review_reminder' => ['doc', 'blue'],
            'leave_request_received' => ['team', 'purple'],
            'announcement' => ['megaphone', 'orange'],
            'expert_slots_short' => ['warn', 'red'],
        ];

        return Notification::where('expert_id', $expertId)->latest()->take(4)->get()
            ->map(fn ($notification) => [
                'icon' => $kinds[$notification->type][0] ?? 'pulse',
                'tint' => $kinds[$notification->type][1] ?? 'teal',
                'text' => html_entity_decode(strip_tags((string) $notification->message)) ?: $notification->title,
                'at' => $notification->created_at->format('M j · g:i A'),
            ]);
    }
}
