<?php

namespace App\Support;

use App\Models\ArchivedDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\CapstoneExpert;
use App\Models\ConsultationBooking;
use App\Models\DefensePanel;
use App\Models\PanelGrade;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;

/**
 * The numbers on the CaPSA/TSA, Admin and Expert dashboards.
 *
 * A dashboard answers "how are we doing" before it answers "what should I do
 * next": totals, how far along the teams are, how the defenses are going, what
 * the fees stand at. What is waiting on the person stays on the page as a
 * strip of its own; this class is the summary beside it.
 *
 * It reads what Reports and Analytics already work out - the same teams, the
 * same rules for on track, passed and paid - so a number on the dashboard
 * never disagrees with the page it links to.
 */
final class DashboardStats
{
    /** Palette for the parts of a bar, used by the views: good, open, warning, bad, neutral. */
    public const TONES = ['ok' => '#1baf7a', 'info' => '#2a78d6', 'warn' => '#eda100', 'bad' => '#dc2626', 'none' => '#a5a9b4'];

    public function __construct(private CapsaReports $reports, private DefenseAnalytics $defenses, private ExpertCapacity $capacity, private FeeBoard $feeBoard) {}

    /**
     * A CaPSA/TSA's sections, the running term.
     *
     * @param  array<string, mixed>  $ctx  CapsaReports::context() for this CaPSA/TSA.
     * @return array<string, mixed>
     */
    public function capsa(CapstoneExpert $capsa, array $ctx): array
    {
        $teams = $ctx['teams'];
        $teamIds = $teams->pluck('team_id');
        $students = $ctx['records']->toBase()->only($teamIds->all())->flatten(1)->pluck('student_id')->unique()->count();

        // Students carry no join date, so the new ones are the members of new teams.
        $since = now()->subDays(30)->toDateTimeString();
        $newTeams = $teams->filter(fn ($t) => $t->created_at >= $since);
        $newStudents = $ctx['records']->toBase()->only($newTeams->pluck('team_id')->all())->flatten(1)->pluck('student_id')->unique()->count();

        $progress = collect($this->reports->build('progress', $ctx)['rows']);
        $statuses = $progress->countBy(fn ($r) => ($r['cells']['status'][3] ?? 0) < 0 ? 'Has overdue items' : $r['cells']['status'][1]);
        $done = $progress->sum(fn ($r) => $r['cells']['done'][1]);
        $total = $progress->sum(fn ($r) => $r['cells']['done'][2]);

        $milestones = $progress
            ->map(fn ($r) => $r['cells']['milestone'])
            ->reject(fn ($m) => $m === CapsaReports::DASH)
            ->countBy()->sortDesc()->take(6);

        $master = collect($this->reports->build('master', $ctx)['rows']);
        $coverage = collect(['adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian'])
            ->map(fn ($label, $key) => [
                'label' => $label,
                'have' => $master->filter(fn ($r) => ($r['cells'][$key] ?? CapsaReports::DASH) !== CapsaReports::DASH)->count(),
            ])->values()->all();

        $bySection = $teams->countBy(fn ($t) => $ctx['teamSections'][$t->team_id] === SectionScope::UNASSIGNED ? 'Unassigned' : $ctx['teamSections'][$t->team_id])->sortKeys();

        return [
            'teams' => $teams->count(),
            'newTeams' => $newTeams->count(),
            'students' => $students,
            'newStudents' => $newStudents,
            'sections' => $bySection->count(),
            'verified' => $teams->where('is_confirmed', true)->count(),
            'progress' => [
                'statuses' => [
                    ['label' => 'On track', 'value' => (int) ($statuses['On track'] ?? 0), 'tone' => 'ok'],
                    ['label' => 'Has overdue items', 'value' => (int) ($statuses['Has overdue items'] ?? 0), 'tone' => 'bad'],
                    ['label' => 'Not verified yet', 'value' => (int) ($statuses['Not verified'] ?? 0), 'tone' => 'none'],
                ],
                'done' => (int) $done,
                'total' => (int) $total,
                'percent' => $total > 0 ? (int) round($done / $total * 100) : null,
                'milestones' => $milestones->map(fn ($n, $label) => ['label' => $label, 'value' => $n])->values()->all(),
            ],
            'defenses' => $this->defenseSummary($capsa),
            'fees' => $this->feeSummary($ctx),
            'coverage' => $coverage,
            'covered' => $master->filter(fn ($r) => collect(['adviser', 'consultant', 'grammarian'])
                ->every(fn ($key) => ($r['cells'][$key] ?? CapsaReports::DASH) !== CapsaReports::DASH))->count(),
            'capacity' => $this->capacity->forPrograms($capsa->handledPrograms()),
            'bySection' => $bySection->map(fn ($n, $label) => ['label' => $label, 'value' => $n])->values()->all(),
        ];
    }

    /**
     * Everything in the system, whichever section it belongs to.
     *
     * @return array<string, mixed>
     */
    public function admin(CapstoneExpert $admin): array
    {
        $experts = CapstoneExpert::where('approved_status', true)->with('roles')->get();
        $roles = $experts->flatMap(fn (CapstoneExpert $e) => $e->roles->pluck('role_name'))->countBy()
            ->mapWithKeys(fn ($n, $role) => [Role::labelFor($role) => $n])->sortDesc();

        // A student has one row per term; a year counts them once however many terms it had.
        $perYear = StudentSchoolYear::withoutGlobalScope(StudentSchoolYear::ACTIVE_TERM_SCOPE)
            ->whereNotNull('school_year')
            ->whereIn('student_id', Student::select('student_id')) // rows can outlive a deleted account
            ->selectRaw('school_year, count(distinct student_id) as n')
            ->groupBy('school_year')->orderBy('school_year')->pluck('n', 'school_year');

        // Active means enrolled this school year.
        $students = Student::count();
        $year = \App\Models\Term::currentSchoolYear() ?: $perYear->keys()->last();
        $active = (int) ($perYear[$year] ?? 0);

        $teams = Team::query();
        $sections = collect($this->feeBoard->forDean());

        return [
            'students' => $students,
            'active' => $active,
            'inactive' => $students - $active,
            'studentsByYear' => $perYear->map(fn ($n, $label) => ['label' => (string) $label, 'value' => (int) $n])->values()->all(),
            'teams' => (clone $teams)->count(),
            'verifiedTeams' => (clone $teams)->where('is_confirmed', true)->count(),
            'experts' => $experts->count(),
            'byRole' => $roles->map(fn ($n, $label) => ['label' => $label, 'value' => $n])->values()->all(),
            'defenses' => $this->defenseSummary($admin),
            'fees' => [
                'received' => (float) $sections->sum('amount'),
                'sent' => $sections->where('is_sent', true)->count(),
                'notSent' => $sections->where('is_sent', false)->count(),
            ],
            'archive' => $this->archiveSummary(),
        ];
    }

    /**
     * One expert's own work: the teams they advise, their reviews, consultations
     * and panels.
     *
     * @return array<string, mixed>
     */
    public function expert(CapstoneExpert $expert): array
    {
        $assignments = TeamExpertAssignment::where('expert_id', $expert->expert_id)->where('status', 'approved')->with('team')->get();
        $byRole = collect(['adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian'])
            ->map(fn ($label, $key) => [
                'label' => $label,
                'value' => $assignments->filter(fn ($a) => strtolower(trim((string) $a->engaged_role_name)) === $key)->pluck('team_id')->unique()->count(),
            ])->values()->all();
        $teamCount = $assignments->pluck('team_id')->unique()->count();
        $slots = (int) ($expert->max_teams ?? 0);

        $reviews = CapstoneDocumentReview::where('expert_id', $expert->expert_id)->get(['review_status']);
        $reviewed = $reviews->where('review_status', CapstoneDocumentReview::REVIEWED)->count();
        $waiting = $reviews->count() - $reviewed;

        $bookings = ConsultationBooking::whereHas('schedule', fn ($q) => $q->where('expert_id', $expert->expert_id))
            ->get(['booking_status', 'session_date']);
        $consults = $bookings->countBy('booking_status');

        $panels = DefensePanel::where('expert_id', $expert->expert_id)->with('schedule')->get()
            ->filter(fn ($p) => $p->schedule?->schedule_date !== null)->unique('defense_id');
        $graded = PanelGrade::where('expert_id', $expert->expert_id)->where('status', 'submitted')->pluck('defense_id')->unique();
        $held = $panels->filter(fn ($p) => $p->schedule->schedule_date->lte(now()));
        $gradedCount = $held->filter(fn ($p) => $graded->contains($p->defense_id))->count();

        $sections = $assignments->map(fn ($a) => $a->team)->filter()->unique('team_id')
            ->map(function (Team $team) {
                $records = StudentSchoolYear::where('team_id', $team->team_id)->with('student')->get();

                return app(SectionScope::class)->sectionFor($team, $records);
            })->map(fn ($label) => $label === SectionScope::UNASSIGNED || ! $label ? 'Unassigned' : $label)
            ->countBy()->sortKeys();

        return [
            'teams' => $teamCount,
            'slots' => ['used' => $teamCount, 'max' => $slots, 'left' => $slots > 0 ? max(0, $slots - $teamCount) : null],
            'byRole' => $byRole,
            'bySection' => $sections->map(fn ($n, $label) => ['label' => $label, 'value' => $n])->values()->all(),
            'reviews' => [
                'reviewed' => $reviewed,
                'waiting' => $waiting,
                'total' => $reviews->count(),
                'percent' => $reviews->count() > 0 ? (int) round($reviewed / $reviews->count() * 100) : null,
            ],
            'consultations' => [
                'done' => (int) ($consults['done'] ?? 0),
                'upcoming' => $bookings->where('booking_status', 'scheduled')->filter(fn ($booking) => $booking->session_date?->gte(today()))->count(),
                'cancelled' => (int) ($consults['cancelled'] ?? 0),
                'total' => $bookings->count(),
            ],
            'panels' => [
                'total' => $panels->count(),
                'held' => $held->count(),
                'graded' => $gradedCount,
                'toGrade' => $held->count() - $gradedCount,
                'upcoming' => $panels->count() - $held->count(),
            ],
        ];
    }

    /**
     * How the defenses are going, stage by stage: the pass rate over the ones
     * decided, and where every team stands.
     *
     * @return array<string, mixed>
     */
    private function defenseSummary(CapstoneExpert $viewer): array
    {
        $built = $this->defenses->build($viewer);
        $order = ['passed' => 'ok', 'revision' => 'info', 'redefense' => 'warn', 'awaiting' => 'none', 'missed' => 'bad', 'not_scheduled' => 'none'];

        return [
            'year' => $built['filters']['school_year'] ?? $built['currentYear'],
            'kpis' => $built['kpis'],
            // Only the stage a team is on counts: a new team is "not scheduled"
            // at every stage, and adding those up would count it four times.
            'unscheduled' => collect($built['teams'])
                ->filter(fn ($team) => collect($team['stages'])->first(fn ($status) => $status !== 'passed') === 'not_scheduled')
                ->count(),
            'stages' => collect($built['stages'])->map(fn (array $row, string $stage) => [
                'stage' => DefenseAnalytics::SHORT[$stage] ?? $stage,
                'teams' => $row['teams'],
                'rate' => $row['rate'],
                'parts' => collect($order)->map(fn ($tone, $status) => [
                    'label' => DefenseAnalytics::statusLabel($status),
                    'value' => (int) $row[$status],
                    'tone' => $tone,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Where each fee stands across the CaPSA/TSA's verified teams: members paid,
     * receipts waiting to be checked, and the money collected so far.
     *
     * @param  array<string, mixed>  $ctx
     * @return list<array<string, mixed>>
     */
    private function feeSummary(array $ctx): array
    {
        return collect(DefenseBoard::PAID_STAGES)->map(function (string $stage) use ($ctx) {
            $table = $this->reports->build('fees', ['fee' => $stage] + $ctx);
            $rows = collect($table['rows']);
            $status = $rows->countBy(fn ($r) => $r['cells']['status'][1] ?? '');
            $posted = ! str_contains($table['summary'], 'has not been posted');

            return [
                'stage' => $stage,
                'posted' => $posted,
                'teams' => $rows->count(),
                'collected' => $table['total']['collected'][1] ?? '₱0',
                'parts' => [
                    ['label' => 'Paid', 'value' => (int) ($status['Paid'] ?? 0), 'tone' => 'ok'],
                    ['label' => 'Receipt to check', 'value' => (int) ($status['Receipt to check'] ?? 0), 'tone' => 'warn'],
                    ['label' => 'Sent back', 'value' => (int) ($status['Sent back'] ?? 0), 'tone' => 'bad'],
                    ['label' => 'Not paid', 'value' => (int) ($status['Not paid'] ?? 0), 'tone' => 'none'],
                ],
            ];
        })->all();
    }

    /** @return array{total: int, years: list<array{label: string, value: int}>, missing: int} */
    private function archiveSummary(): array
    {
        $rows = ArchivedDocument::published()->whereNotNull('academic_year')->selectRaw('academic_year, count(*) as n')
            ->groupBy('academic_year')->orderBy('academic_year')->pluck('n', 'academic_year');

        // Years between the first and the last that have nothing published.
        $years = $rows->keys()->map(fn ($year) => (int) $year)->unique();

        return [
            'total' => (int) $rows->sum(),
            'years' => $rows->map(fn ($n, $year) => ['label' => (string) $year, 'value' => (int) $n])->values()->take(-4)->all(),
            'missing' => $years->isEmpty() ? 0 : $years->max() - $years->min() + 1 - $years->count(),
        ];
    }
}
