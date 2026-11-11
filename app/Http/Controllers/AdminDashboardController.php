<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\ActivityLogController;
use App\Models\ActivityLog;
use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefenseSchedule;
use App\Models\FinancialRecord;
use App\Models\MemberLeaveRequest;
use App\Models\Setting;
use App\Models\Student;
use App\Support\ActivityScope;
use App\Support\CapsaReports;
use App\Support\DashboardStats;
use App\Support\ExpertCapacity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The first page an Admin or a CaPSA/TSA sees: how things stand, then what is
 * waiting on them.
 *
 * A CaPSA/TSA sees their teams, how well experts cover them, what is waiting on
 * them and what is due next. An Admin sees the summary from DashboardStats and
 * the setup that is still missing, like a section nobody covers.
 */
class AdminDashboardController extends Controller
{
    public function __construct(private CapsaReports $reports, private DashboardStats $stats) {}

    public function index(): View
    {
        /** @var CapstoneExpert $viewer */
        $viewer = Auth::guard('expert')->user();

        // The running term, the CaPSA/TSA's sections - the same teams Reports counts.
        $ctx = $viewer->is_capsa ? $this->reports->context($viewer, []) : null;
        $capsaStats = $ctx ? $this->stats->capsa($viewer, $ctx) : null;

        return view('capsa.dashboard', [
            'viewer' => $viewer,
            'capsa' => $ctx ? $this->forCapsa($viewer, $ctx, $capsaStats) : null,
            'admin' => $viewer->is_admin ? $this->forAdmin() : null,
            'stats' => [
                'capsa' => $capsaStats,
                'admin' => $viewer->is_admin ? $this->stats->admin($viewer) : null,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function forCapsa(CapstoneExpert $capsa, array $ctx, array $stats): array
    {
        $teams = $ctx['teams'];
        $teamIds = $teams->pluck('team_id');
        $studentIds = $ctx['records']->toBase()->only($teamIds->all())->flatten(1)->pluck('student_id')->unique();
        $visibleIds = ActivityScope::studentIdsFor($capsa);

        $titles = CapstonePreProposal::whereIn('team_id', $teamIds)
            ->whereNotNull('submitted_at')->whereNull('retired_at')
            ->where('approved_status', false)->whereNull('rejection_reason')
            ->distinct('team_id')->count('team_id');
        $toVerify = $teams->filter(fn ($t) => $t->team_status === 'complete' && ! $t->is_confirmed)->count();
        $unscheduled = $stats['defenses']['unscheduled'];
        $receipts = FinancialRecord::whereIn('student_id', $studentIds)
            ->whereNull('verified_at')->whereNotNull('receipt_image')
            ->where(fn ($q) => $q->whereNull('f_record_status')->orWhere('f_record_status', 'pending'))
            ->count();
        $leaveRequests = MemberLeaveRequest::pendingCountFor($capsa);

        $shortages = $this->shortages($stats['capacity']);
        $count = fn (int $n, string $word) => $n.' '.Str::plural($word, $n);

        return [
            'year' => $ctx['year'],
            'status' => $this->expertStatus($stats['capacity'], $shortages),
            'shortage' => $shortages->first(),
            // Worst first; the dashboard shows the first four with work in them.
            'issues' => [
                ['label' => 'Insufficient Experts', 'count' => $shortages->sum('short'), 'critical' => true, 'icon' => 'team', 'tone' => 't-red',
                    'detail' => $shortages->map(fn ($s) => "{$s['program']} · Short by {$s['who']}")->implode(', '),
                    'href' => route('capsa.reports.show', 'expert_list')],
                ['label' => 'Titles Awaiting Review', 'count' => $titles, 'icon' => 'doc', 'tone' => 't-blue',
                    'detail' => $titles === 1 ? '1 proposal needs a decision' : "{$titles} proposals need decisions",
                    'href' => route('capsa.team_management', ['tab' => 'pre_proposals'])],
                ['label' => 'Unverified Teams', 'count' => $toVerify, 'icon' => 'team', 'tone' => 't-purple',
                    'detail' => $count($toVerify, 'team').' awaiting verification',
                    'href' => route('capsa.team_management', ['tab' => 'verification'])],
                ['label' => 'Unscheduled Defenses', 'count' => $unscheduled, 'icon' => 'cal', 'tone' => 't-orange',
                    'detail' => $count($unscheduled, 'team').' without a date',
                    'href' => route('capsa.defenses')],
                ['label' => 'Receipts to Check', 'count' => $receipts, 'icon' => 'receipt', 'tone' => 't-green',
                    'detail' => $count($receipts, 'receipt').' to verify',
                    'href' => route('capsa.financial')],
                ['label' => 'Member Requests', 'count' => $leaveRequests, 'icon' => 'user', 'tone' => 't-teal',
                    'detail' => $count($leaveRequests, 'request').' to leave a team',
                    'href' => route('capsa.team_management', ['tab' => 'leave_requests'])],
            ],
            'defenses' => DefenseSchedule::whereIn('team_id', $teamIds)
                ->where('schedule_date', '>=', now()->startOfDay())
                ->with('team')->orderBy('schedule_date')->take(3)->get(),
            'requirements' => $this->reports->upcomingRequirements($ctx),
            // The same rows as the Activity Log, so View all carries on from here.
            'activity' => ActivityLog::when($visibleIds !== null, fn ($q) => $q->whereIn('student_id', $visibleIds))
                ->whereIn('type', ActivityLogController::TYPES)
                ->latest('created_at')->take(4)->get(),
        ];
    }

    /**
     * What each program is short of: its short roles, or the "All roles" row
     * when every role looks fine but the experts' shared slots run out.
     *
     * @param  array<string, list<array<string, mixed>>>  $capacity
     * @return Collection<int, array{program: string, short: int, who: string}>
     */
    private function shortages(array $capacity): Collection
    {
        return collect($capacity)->flatMap(function (array $rows, string $program) {
            $short = collect($rows)->where('short', '>', 0);
            $roles = $short->where('role', '!=', 'all');

            return ($roles->isNotEmpty() ? $roles : $short)->map(fn ($row) => [
                'program' => $program,
                'short' => $row['short'],
                'who' => $row['short'].' '.Str::plural($row['role'] === 'all' ? 'expert' : strtolower($row['label']), $row['short']),
            ]);
        })->values();
    }

    /** @param  array<string, list<array<string, mixed>>>  $capacity */
    private function expertStatus(array $capacity, Collection $shortages): ?string
    {
        if (! $capacity) {
            return null;
        }

        if ($shortages->isNotEmpty()) {
            return ExpertCapacity::SHORT;
        }

        return collect($capacity)->flatten(1)->contains('status', ExpertCapacity::JUST_ENOUGH) ? ExpertCapacity::JUST_ENOUGH : ExpertCapacity::OK;
    }

    /** @return array<string, mixed> */
    private function forAdmin(): array
    {
        $capsas = CapstoneExpert::whereHas('roles', fn ($q) => $q->where('role_name', CapstoneExpert::CAPSA_ROLE))->get();
        $covered = $capsas->flatMap(fn ($c) => $c->sections ?? [])->unique()->all();
        $available = AdminProfileController::getAllAvailableSections();
        $uncovered = array_values(array_diff($available, $covered));
        $noSections = array_filter(CapstoneExpert::PROGRAMS, fn ($program) => ! collect($available)->contains(fn ($s) => str_starts_with($s, "{$program}-")));

        return [
            'students' => Student::count(),
            'experts' => CapstoneExpert::where('approved_status', true)->count(),
            'capsas' => $capsas->count(),
            'uncovered' => $uncovered,
            'noSections' => count($noSections),
            'schoolYear' => Setting::get('current_school_year'),
        ];
    }
}
