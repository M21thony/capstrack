<?php

namespace App\Http\Controllers;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DefenseSchedule;
use App\Models\FinancialRecord;
use App\Models\MemberLeaveRequest;
use App\Models\Setting;
use App\Models\Student;
use App\Support\CapsaReports;
use App\Support\DashboardStats;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The first page an Admin or a CaPSA sees: how things stand, then what is
 * waiting on them.
 *
 * The summary comes first - totals, how far along the teams are, how the
 * defenses are going, what the fees stand at (DashboardStats) - because that is
 * what a dashboard is for. What needs doing keeps its own strip beneath it: a
 * CaPSA's work waiting in their sections and the defenses coming up; an
 * Admin's setup that is still missing, like a section nobody covers.
 */
class AdminDashboardController extends Controller
{
    public function __construct(private CapsaReports $reports, private DashboardStats $stats) {}

    public function index(): View
    {
        /** @var CapstoneExpert $viewer */
        $viewer = Auth::guard('expert')->user();

        // The running term, the CaPSA's sections - the same teams Reports counts.
        $ctx = $viewer->is_capsa ? $this->reports->context($viewer, []) : null;

        return view('capsa.dashboard', [
            'viewer' => $viewer,
            'capsa' => $ctx ? $this->forCapsa($viewer, $ctx) : null,
            'admin' => $viewer->is_admin ? $this->forAdmin() : null,
            'stats' => [
                'capsa' => $ctx ? $this->stats->capsa($viewer, $ctx) : null,
                'admin' => $viewer->is_admin ? $this->stats->admin($viewer) : null,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function forCapsa(CapstoneExpert $capsa, array $ctx): array
    {
        $teams = $ctx['teams'];
        $teamIds = $teams->pluck('team_id');
        $studentIds = $ctx['records']->toBase()->only($teamIds->all())->flatten(1)->pluck('student_id')->unique();

        $progress = collect($this->reports->build('progress', $ctx)['rows']);
        $behind = $progress->filter(fn ($r) => ($r['cells']['status'][3] ?? 0) < 0)->count();

        return [
            'toVerify' => $teams->filter(fn ($t) => $t->team_status === 'complete' && ! $t->is_confirmed)->count(),
            'titles' => CapstonePreProposal::whereIn('team_id', $teamIds)
                ->whereNotNull('submitted_at')->whereNull('retired_at')
                ->where('approved_status', false)->whereNull('rejection_reason')
                ->distinct('team_id')->count('team_id'),
            'receipts' => FinancialRecord::whereIn('student_id', $studentIds)
                ->whereNull('verified_at')->whereNotNull('receipt_image')
                ->where(fn ($q) => $q->whereNull('f_record_status')->orWhere('f_record_status', 'pending'))
                ->count(),
            'leaveRequests' => MemberLeaveRequest::pendingCountFor($capsa),
            'teamCount' => $teams->count(),
            'sectionCount' => $teams->map(fn ($t) => $ctx['teamSections'][$t->team_id])->unique()->count(),
            'behind' => $behind,
            'unverified' => $teams->where('is_confirmed', false)->count(),
            'period' => 'S.Y. '.$ctx['year'].($ctx['term'] !== '' ? ' · '.$ctx['termOptions'][$ctx['term']] : ''),
            'defenses' => DefenseSchedule::whereIn('team_id', $teamIds)
                ->whereBetween('schedule_date', [now()->startOfDay(), now()->addDays(14)->endOfDay()])
                ->with('team')->orderBy('schedule_date')->take(6)->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function forAdmin(): array
    {
        $capsas = CapstoneExpert::whereHas('roles', fn ($q) => $q->where('role_name', CapstoneExpert::CAPSA_ROLE))->get();
        $covered = $capsas->flatMap(fn ($c) => $c->sections ?? [])->unique()->all();
        $uncovered = array_values(array_diff(AdminProfileController::getAllAvailableSections(), $covered));

        return [
            'students' => Student::count(),
            'experts' => CapstoneExpert::where('approved_status', true)->count(),
            'capsas' => $capsas->count(),
            'uncovered' => $uncovered,
            'schoolYear' => Setting::get('current_school_year'),
        ];
    }
}
