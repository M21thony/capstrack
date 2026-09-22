<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\DefenseSchedule;
use App\Models\ExpensePost;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use App\Support\DefenseBoard;
use App\Support\DefenseOutcome;
use App\Support\DefenseScores;
use App\Support\FeeBoard;
use App\Support\MilestoneProgress;
use App\Support\SectionScope;
use App\Support\TeamChecklist;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Reports: an overview of the CaPSA's sections, then the same sections counted
 * three ways - checklist progress, defense results and fee receipts. One row
 * per section, a page of its own per section, and a PDF or Excel copy of
 * whichever is on screen, with the columns picked on the page.
 *
 * It only counts. Verifying a team or a receipt stays on Team Management and
 * Financial Records, so nothing here writes.
 */
class ReportController extends Controller
{
    private const TABS = [
        'progress' => 'Team Progress',
        'results' => 'Defense Results',
        'fees' => 'Fee Collection',
    ];

    private const LEDES = [
        'progress' => "How far each section's teams are on the CaPSA checklist.",
        'results' => "How each section's teams did at one defense, with the average panel score.",
        'fees' => 'Receipts and money collected for one defense fee, by section.',
    ];

    /** One tone per status, so it reads the same as a dot on screen and a cell colour in Excel. */
    private const TONES = [
        'On track' => 'ok',
        'Has overdue items' => 'bad',
        'Not verified' => 'warn',
        'Passed' => 'ok',
        'Passed with revision' => 'ok',
        'Redefense' => 'bad',
        'Redefense with major revision' => 'bad',
        'Redefense with a new title' => 'bad',
        'No result yet' => 'warn',
        'Verified' => 'ok',
        'Waiting' => 'warn',
        'Sent back' => 'bad',
        'Not uploaded' => 'none',
    ];

    /** Excel cell fill and text colour per tone. */
    private const FILLS = [
        'ok' => ['DCFCE7', '166534'],
        'warn' => ['FEF3C7', '92400E'],
        'bad' => ['FEE2E2', '991B1B'],
        'none' => ['EEF0F3', '475569'],
    ];

    private const NO_RESULT = 'No result yet';

    public function __construct(
        private SectionScope $scope,
        private TeamChecklist $checklists,
        private DefenseBoard $board,
        private FeeBoard $feeBoard,
    ) {}

    public function index(Request $request)
    {
        return $request->has('tab') || $request->has('download')
            ? $this->respond($request, null)
            : $this->overview($request);
    }

    /** One section on a page of its own - a table row never opens in place. */
    public function section(Request $request, string $section)
    {
        abort_unless($section === SectionScope::UNASSIGNED || in_array($section, auth('expert')->user()->sections ?? [], true), 404);

        return $this->respond($request, $section);
    }

    /** The landing page: this term's headline numbers, where the teams are, and the three reports. */
    private function overview(Request $request)
    {
        $capsa = auth('expert')->user();
        ['year' => $year, 'term' => $term, 'termOptions' => $termOptions] = $this->period($request);
        [$records, $found] = $this->teams($capsa, $year, $term);

        $all = $this->bySection($found, true);
        $teams = $all->collapse();
        $progress = $this->progress($all, null)['table']['total'];
        $fee = DefenseBoard::PAID_STAGES[0];
        $fees = $this->fees($this->bySection($found, false), null, $fee, $records, $capsa->handledPrograms())['table']['total'];

        // Each team sits at the first defense it has not passed yet, at any attempt.
        $passed = DefenseSchedule::whereIn('team_id', $teams->pluck('team_id'))->get(['team_id', 'defense_type', 'outcome'])
            ->filter(fn ($s) => DefenseOutcome::isPass($s->outcome))
            ->groupBy('team_id')
            ->map(fn ($g) => $g->pluck('defense_type')->all());
        $at = $teams->map(fn (Team $team) => collect(DefenseBoard::STAGES)
            ->first(fn ($stage) => ! in_array($stage, $passed->get($team->team_id, []), true)));
        $sections = $all->count();
        $behind = $progress['Has overdue items'];

        return view('capsa.reports', [
            'view' => 'overview',
            'period' => 'S.Y. '.$year.', '.$termOptions[$term],
            'cards' => [
                ['progress', 'users', 'gold', 'Teams', $teams->count(), 'in '.$sections.' '.Str::plural('section', $sections)],
                ['progress', 'check', 'blue', 'Not verified', $progress['Not verified'], 'teams still to verify'],
                ['progress', 'alert', 'red', 'Overdue items', $progress['overdue'], $behind.' '.Str::plural('team', $behind).' behind'],
                ['fees', 'coin', 'green', 'Receipts to check', $fees['Waiting'] ?? '—', $fee.' fee'.($fees ? '' : ', not posted yet')],
            ],
            'teamCount' => $teams->count(),
            'pipeline' => collect(DefenseBoard::STAGES)->mapWithKeys(fn ($stage) => [$stage => $at->filter(fn ($s) => $s === $stage)->count()])->all(),
            'reports' => [
                'progress' => ['chart', 'gold', self::TABS['progress'], self::LEDES['progress']],
                'results' => ['grade', 'dark', self::TABS['results'], self::LEDES['results']],
                'fees' => ['coin', 'gold', self::TABS['fees'], self::LEDES['fees']],
            ],
        ]);
    }

    private function respond(Request $request, ?string $section)
    {
        $capsa = auth('expert')->user();
        $tab = $this->pick($request, 'tab', array_keys(self::TABS), 'progress');
        ['years' => $years, 'year' => $year, 'thisYear' => $thisYear, 'termOptions' => $termOptions, 'term' => $term, 'defaultTerm' => $defaultTerm]
            = $this->period($request);

        $programs = $capsa->handledPrograms();
        $programOptions = count($programs) > 1
            ? ['' => implode(' & ', $programs)] + array_combine($programs, $programs)
            : ['' => $programs[0] ?? 'All programs'];
        $program = $this->pick($request, 'program', array_keys($programOptions), '');

        $stage = $tab === 'results' ? $this->board->openingStage($capsa, $this->pick($request, 'stage', DefenseBoard::STAGES, null)) : null;
        $fee = $tab === 'fees' ? $this->pick($request, 'fee', DefenseBoard::PAID_STAGES, DefenseBoard::PAID_STAGES[0]) : null;

        [$records, $found] = $this->teams($capsa, $year, $term);
        $bySection = $this->bySection($found, $tab === 'progress', fn ($label) => $section !== null
            ? $label === $section
            : ($program === '' || TeamChecklist::programOf($label) === $program));

        $report = match ($tab) {
            'progress' => $this->progress($bySection, $section),
            'results' => $this->results($bySection, $section, $stage),
            'fees' => $this->fees($bySection, $section, $fee, $records,
                $section !== null ? [TeamChecklist::programOf($section)] : ($program !== '' ? [$program] : $programs)),
        };

        $fields = array_values(array_filter([
            ['year', 'Academic year', array_combine($years, array_map(fn ($y) => $y === $thisYear ? $y.' (current)' : $y, $years)), $year],
            ['term', 'Semester', $termOptions, $term],
            $stage ? ['stage', 'Defense', array_combine(DefenseBoard::STAGES, DefenseBoard::STAGES), $stage] : null,
            $fee ? ['fee', 'Fee', array_combine(DefenseBoard::PAID_STAGES, array_map(fn ($s) => $s.' fee', DefenseBoard::PAID_STAGES)), $fee] : null,
            // A section is in one program already, and one program is no choice at all.
            $section === null && count($programs) > 1 ? ['program', 'Program', $programOptions, $program] : null,
        ]));

        // The first column names the row and always shows. The rest show from
        // "from" to "to", less the ones hidden with a heading's cross.
        $keys = array_keys($report['table']['columns']);
        $pickable = array_slice($keys, 1);
        $from = $this->pick($request, 'from', $pickable, $pickable[0]);
        $to = $this->pick($request, 'to', $pickable, end($pickable));
        $a = array_search($from, $pickable, true);
        if (array_search($to, $pickable, true) < $a) {
            $to = end($pickable);
        }
        $range = array_slice($pickable, $a, array_search($to, $pickable, true) - $a + 1);
        $hidden = array_values(array_intersect($range, explode(',', (string) $request->query('hide', ''))));
        $visible = [$keys[0], ...array_values(array_diff($range, $hidden))];

        $name = self::TABS[$tab].($section !== null ? ' for '.$section : '');
        $scope = implode(' · ', array_filter([
            $section !== null ? 'By team' : 'By section',
            'S.Y. '.$year,
            $termOptions[$term],
            $stage,
            $fee ? $fee.' fee' : null,
        ]));
        $tiles = array_map(fn ($k) => [
            'value' => $k['of'] !== null ? $k['value'].' of '.$k['of'] : (string) $k['value'],
            'label' => $k['label'].' · '.$k['sub'],
        ], $report['kpis']);
        $fileName = Str::slug($name).'_'.$year.'_'.now()->toDateString();
        $by = $capsa->name ?? 'the CaPSA';
        $download = $request->query('download');

        if ($download === 'pdf' || $download === 'xlsx') {
            $table = $this->plain($report['table'], $visible);

            return $download === 'pdf'
                ? Pdf::loadView('capsa.pdf.report', compact('name', 'scope', 'tiles', 'table', 'by'))
                    ->setPaper('letter', 'landscape')->download($fileName.'.pdf')
                : $this->excel(self::TABS[$tab], $name, $scope.' · Generated '.now()->format('M j, Y').' by '.$by, $tiles, $table, $fileName.'.xlsx');
        }

        return view('capsa.reports', [
            'view' => 'report',
            'tab' => $tab,
            'name' => self::TABS[$tab],
            'section' => $section,
            'lede' => $section !== null ? $name.'.' : self::LEDES[$tab],
            'icon' => ['progress' => 'chart', 'results' => 'grade', 'fees' => 'coin'][$tab],
            'fields' => $fields,
            'report' => $report,
            'tiles' => $tiles,
            // Every column as plain text with its tone, for the download previews.
            'sheet' => $this->plain($report['table'], $keys),
            'tones' => self::TONES,
            'from' => $from,
            'to' => $to,
            'hidden' => $hidden,
            'visible' => $visible,
            'scope' => $scope,
            'by' => $by,
            'fileName' => $fileName,
            'filtered' => $year !== $thisYear || $term !== $defaultTerm,
            // Clearing keeps the report, its defense or fee, and the columns.
            'clear' => url()->current().'?'.http_build_query(array_filter(
                compact('tab', 'stage', 'fee', 'program', 'from', 'to') + ['hide' => implode(',', $hidden)],
                fn ($v) => $v !== null && $v !== '')),
            'notPosted' => $tab === 'fees' && str_contains($report['table']['empty'], 'not been posted'),
            // What a link to a section or back keeps of the filters.
            'keep' => array_filter(compact('tab', 'year', 'term', 'program', 'stage', 'fee'), fn ($v) => $v !== null),
        ]);
    }

    /** The school years on offer, the one picked, its terms and the term picked. */
    private function period(Request $request): array
    {
        // Every year anybody was enrolled in, and this one even before anybody is.
        $thisYear = Term::currentSchoolYear();
        $years = StudentSchoolYear::acrossAllTerms()->whereNotNull('school_year')->where('school_year', '!=', '0000')
            ->distinct()->pluck('school_year')
            ->merge(Term::pluck('school_year'))->push($thisYear)
            ->unique()->sortDesc()->values()->all();
        $year = $this->pick($request, 'year', $years, $thisYear);

        $terms = Term::where('school_year', $year)->orderBy('semester')->orderBy('capstone_stage')->get();
        $termOptions = ['' => 'Whole school year']
            + $terms->mapWithKeys(fn (Term $t) => [$t->term_id => $t->semesterLabel().' · Capstone '.$t->capstone_stage])->all();
        // The running term is only the first visit's default: "Whole school year" is a choice too.
        $defaultTerm = (string) $terms->firstWhere('is_active', true)?->term_id;
        $term = $this->pick($request, 'term', array_map('strval', array_keys($termOptions)),
            $request->has('term') ? '' : $defaultTerm);

        return compact('years', 'year', 'thisYear', 'termOptions', 'term', 'defaultTerm');
    }

    /** The year's (and term's) enrolment rows by team, and the teams in the CaPSA's sections. */
    private function teams($capsa, string $year, string $term): array
    {
        $records = StudentSchoolYear::acrossAllTerms()
            ->where('school_year', $year)
            // Same rule as everywhere else: a row with no term belongs to the running one.
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('term_id', $term)
                ->when((int) $term === Term::activeId(), fn ($q) => $q->orWhereNull('term_id'))))
            ->whereNotNull('team_id')
            ->with('student')
            ->get()
            ->groupBy('team_id');

        return [$records, $this->scope->filterTeams(
            Team::whereIn('team_id', $records->keys())->orderBy('team_name')->get(),
            $records,
            $capsa->sections ?? [],
        )];
    }

    /** Teams by section label, the sections in order and Unassigned last. */
    private function bySection(array $found, bool $unverifiedToo, ?callable $keep = null): Collection
    {
        return $found['teams']
            // Results and fees count verified teams only, as the defense and fee pages do.
            ->filter(fn (Team $team) => $unverifiedToo || $team->is_confirmed)
            ->groupBy(fn (Team $team) => $found['teamSections'][$team->team_id])
            ->filter(fn ($teams, $label) => $keep === null || $keep($label))
            ->sortKeysUsing(fn ($a, $b) => [$a === SectionScope::UNASSIGNED, $a] <=> [$b === SectionScope::UNASSIGNED, $b]);
    }

    private function progress(Collection $bySection, ?string $section): array
    {
        $rows = collect();

        foreach ($bySection as $label => $teams) {
            $checklist = $this->checklists->forSection($label === SectionScope::UNASSIGNED ? '' : $label);
            $progress = $checklist ? MilestoneProgress::forTeams($checklist, $teams->pluck('team_id')) : [];

            foreach ($teams as $team) {
                $p = $progress[$team->team_id] ?? null;
                $overdue = $p->overdue ?? 0;

                $rows->push([
                    'section' => $label,
                    'team' => $team->team_name,
                    'verified' => (bool) $team->is_confirmed,
                    'milestone' => ! $p || $p->total === 0 ? '—'
                        : ($p->milestones->first(fn ($m) => $m->completed < $m->total)?->milestone->name ?? 'All done'),
                    'done' => $p->completed ?? 0,
                    'total' => $p->total ?? 0,
                    'overdue' => $overdue,
                    'status' => ! $team->is_confirmed ? 'Not verified' : ($overdue > 0 ? 'Has overdue items' : 'On track'),
                ]);
            }
        }

        $statuses = ['On track', 'Has overdue items', 'Not verified'];
        $n = $rows->count();
        $done = $rows->sum('done');
        $total = $rows->sum('total');
        $behind = $rows->where('overdue', '>', 0)->count();

        $kpis = [
            $this->kpi('Teams', $n, null, $section ?? $bySection->count().' '.Str::plural('section', $bySection->count())),
            $this->kpi('Verified', $rows->where('verified', true)->count(), $n, 'checked by the CaPSA'),
            $this->kpi('Requirements done', $this->pct($done, $total).'%', null, $done.' of '.$total.' requirements'),
            $this->kpi('Overdue items', $rows->sum('overdue'), null, $behind.' '.Str::plural('team', $behind).' behind'),
        ];

        if ($section !== null) {
            return ['kpis' => $kpis, 'table' => [
                'columns' => ['team' => 'Team', 'verified' => 'Verified', 'milestone' => 'Current milestone', 'done' => 'Requirements done', 'overdue' => 'Overdue', 'status' => 'Status'],
                'numeric' => ['done', 'overdue'],
                'rows' => $rows->map(fn ($r) => [
                    'team' => ['b', $r['team']],
                    'verified' => $r['verified'] ? 'Yes' : ['b', 'No'],
                    'milestone' => $r['milestone'],
                    'done' => $r['done'].' of '.$r['total'],
                    'overdue' => $r['overdue'] ? ['b', $r['overdue']] : 0,
                    'status' => ['res', $r['status']],
                ])->all(),
                'total' => null,
                'empty' => 'No teams match these filters',
            ]];
        }

        $summary = fn (Collection $g, $first) => [
            'section' => $first,
            'teams' => $g->count(),
            'verified' => $g->where('verified', true)->count().' of '.$g->count(),
            'done' => $g->sum('done').' of '.$g->sum('total').' ('.$this->pct($g->sum('done'), $g->sum('total')).'%)',
            'overdue' => $g->sum('overdue'),
        ] + $this->tally($g, 'status', $statuses);

        return [
            'kpis' => $kpis,
            'table' => [
                'columns' => ['section' => 'Section', 'teams' => 'Teams', 'verified' => 'Verified', 'done' => 'Requirements done', 'overdue' => 'Overdue items',
                    'On track' => 'On track', 'Has overdue items' => 'With overdue items', 'Not verified' => 'Not verified'],
                'numeric' => ['teams', 'verified', 'done', 'overdue', ...$statuses],
                'rows' => $rows->groupBy('section')->map(fn ($g, $label) => $summary($g, ['sec', $label]))->values()->all(),
                'total' => $summary($rows, 'All sections'),
                'empty' => 'No teams match these filters',
            ],
        ];
    }

    private function results(Collection $bySection, ?string $section, string $stage): array
    {
        // A redefense is a new attempt with its own panel; the latest is the one that stands.
        $schedules = DefenseSchedule::whereIn('team_id', $bySection->collapse()->pluck('team_id'))
            ->where('defense_type', $stage)
            ->with(['panels.expert', 'grades'])
            ->get()
            ->sortByDesc('attempt')
            ->unique('team_id')
            ->keyBy('team_id');

        $rows = $bySection->flatMap(fn ($teams, $label) => $teams->map(function (Team $team) use ($label, $schedules, $stage) {
            $schedule = $schedules->get($team->team_id);

            return [
                'section' => $label,
                'team' => $team,
                'schedule' => $schedule,
                'raw' => $schedule?->outcome,
                'outcome' => $schedule?->outcome ? DefenseOutcome::label($schedule->outcome, $stage) : self::NO_RESULT,
                'average' => $schedule?->panelAverage(),
            ];
        }));

        $labels = collect(DefenseOutcome::forStage($stage))->map(fn ($o) => DefenseOutcome::label($o, $stage))
            ->merge($rows->pluck('outcome')->reject(fn ($l) => $l === self::NO_RESULT))
            ->push(self::NO_RESULT)->unique()->values()->all();
        $average = function (Collection $rows) {
            $scores = $rows->pluck('average')->reject(fn ($a) => $a === null);

            return $scores->isEmpty() ? '—' : DefenseScores::format(round($scores->avg(), 2));
        };

        $n = $rows->count();
        $defended = $rows->whereNotNull('raw')->count();
        $passed = $rows->filter(fn ($r) => DefenseOutcome::isPass($r['raw']))->count();
        $redo = $rows->filter(fn ($r) => DefenseOutcome::requiresRedefense($r['raw']))->count();
        $pre = $stage === DefenseOutcome::PRE_PROPOSAL;

        $kpis = [
            $this->kpi('Defended', $defended, $n, $defended === $n ? 'every team' : ($n - $defended).' with no result yet'),
            $this->kpi('Passed', $passed, $n, $pre
                ? $rows->where('raw', DefenseOutcome::PASSED_WITH_REVISION)->count().' of them with revision'
                : $this->pct($passed, $n).'% of the teams'),
            $this->kpi('Redefense', $redo, $n, $pre
                ? $rows->where('raw', DefenseOutcome::REDEFENSE_NEW_TITLE)->count().' need a new title'
                : 'defending this stage again'),
            $this->kpi('Average score', $average($rows), null, 'across fully graded teams'),
        ];

        if ($section !== null) {
            // A chair and two panelists normally; a bigger panel gets more columns.
            $seats = max(2, $schedules->max(fn ($s) => $s->panels->where('panel_role', '!=', 'Panel Chair')->count()) ?? 0);
            $seat = function (?DefenseSchedule $schedule, $panel) {
                if (! $panel) {
                    return '—';
                }

                $grade = $schedule->submittedGrades()->firstWhere('expert_id', $panel->expert_id);

                return ['seat', $panel->expert?->name ?? 'Panelist', $grade ? DefenseScores::format((float) $grade->total_score) : '—'];
            };

            return ['kpis' => $kpis, 'table' => [
                'columns' => ['team' => 'Team', 'title' => 'Title', 'chair' => 'Chair']
                    + collect(range(1, $seats))->mapWithKeys(fn ($i) => ['p'.$i => 'Panelist '.$i])->all()
                    + ['average' => 'Average', 'outcome' => 'Outcome'],
                'numeric' => ['average'],
                'rows' => $rows->map(function ($r) use ($seats, $seat) {
                    $panels = $r['schedule']?->panels ?? collect();
                    $others = $panels->where('panel_role', '!=', 'Panel Chair')->values();
                    $row = [
                        'team' => ['b', $r['team']->team_name],
                        'title' => $r['team']->project_title ?: '—',
                        'chair' => $seat($r['schedule'], $panels->firstWhere('panel_role', 'Panel Chair')),
                    ];

                    foreach (range(1, $seats) as $i) {
                        $row['p'.$i] = $seat($r['schedule'], $others->get($i - 1));
                    }

                    return $row + [
                        'average' => $r['average'] === null ? '—' : ['b', DefenseScores::format($r['average'])],
                        'outcome' => ['res', $r['outcome']],
                    ];
                })->all(),
                'total' => null,
                'empty' => 'No teams match these filters',
            ]];
        }

        $summary = fn (Collection $g, $first) => ['section' => $first, 'teams' => $g->count()]
            + $this->tally($g, 'outcome', $labels)
            + ['average' => ['b', $average($g)]];

        return [
            'kpis' => $kpis,
            'table' => [
                'columns' => ['section' => 'Section', 'teams' => 'Teams'] + array_combine($labels, $labels) + ['average' => 'Average score'],
                'numeric' => ['teams', ...$labels, 'average'],
                'rows' => $rows->groupBy('section')->map(fn ($g, $label) => $summary($g, ['sec', $label]))->values()->all(),
                'total' => $summary($rows, 'All sections'),
                'empty' => 'No teams match these filters',
            ],
        ];
    }

    /** @param  array<int, string>  $programs  whose fee to count */
    private function fees(Collection $bySection, ?string $section, string $stage, Collection $records, array $programs): array
    {
        // ponytail: one fee post per program per stage (active, else newest); per-year-level fees would need year_level matched
        $posts = ExpensePost::where('defense_type', $stage)->whereIn('course', $programs)
            ->orderByDesc('is_active')->orderByDesc('id')
            ->get()->unique('course')->keyBy('course');

        $members = collect();

        foreach ($bySection as $label => $teams) {
            if (! $post = $posts->get(TeamChecklist::programOf($label))) {
                continue;
            }

            foreach ($teams as $team) {
                foreach ($this->feeBoard->members($records->get($team->team_id, collect())) as $member) {
                    $members->push($member + ['section' => $label, 'team' => $team->team_name, 'post' => $post]);
                }
            }
        }

        $receipts = $this->feeBoard->receipts($posts->pluck('id'), $members->pluck('id'));
        $members = $members->unique('id')->map(function ($m) use ($receipts) {
            $receipt = $receipts[$m['post']->id.':'.$m['id']] ?? null;

            return $m + [
                'status' => match ($receipt['status'] ?? null) {
                    'verified' => 'Verified',
                    'pending' => 'Waiting',
                    'sent_back' => 'Sent back',
                    default => 'Not uploaded',
                },
                'at' => $receipt['at'] ?? null,
                'reason' => $receipt['reason'] ?? null,
            ];
        });

        $posted = $posts->isNotEmpty();
        $statuses = ['Verified', 'Waiting', 'Sent back', 'Not uploaded'];
        $counts = $this->tally($members, 'status', $statuses);
        $collected = fn (Collection $g) => $g->where('status', 'Verified')->sum(fn ($m) => (float) $m['post']->amount);
        $expected = fn (Collection $g) => $g->sum(fn ($m) => (float) $m['post']->amount);
        $subs = [
            'Verified' => $posted ? $this->money($collected($members)).' collected' : 'not posted yet',
            'Waiting' => 'receipts to check',
            'Sent back' => 'asked to upload again',
            'Not uploaded' => 'no receipt yet',
        ];
        $kpis = array_map(fn ($s) => $this->kpi($s, $posted ? $counts[$s] : '—', $posted ? $members->count() : null, $subs[$s]), $statuses);
        $empty = $posted ? 'No members match these filters' : 'The '.$stage.' fee has not been posted yet';

        if ($section !== null) {
            return ['kpis' => $kpis, 'table' => [
                'columns' => ['team' => 'Team', 'member' => 'Member', 'amount' => 'Amount', 'receipt' => 'Receipt', 'uploaded' => 'Uploaded', 'reason' => 'Reason sent back'],
                'numeric' => ['amount'],
                'rows' => $members->map(fn ($m) => [
                    'team' => ['b', $m['team']],
                    'member' => $m['name'],
                    'amount' => $this->money((float) $m['post']->amount),
                    'receipt' => ['res', $m['status']],
                    'uploaded' => $m['at'] ?? '—',
                    'reason' => $m['status'] === 'Sent back' ? ($m['reason'] ?: '—') : '',
                ])->values()->all(),
                'total' => null,
                'empty' => $empty,
            ]];
        }

        $summary = fn (Collection $g, $first) => ['section' => $first, 'members' => $g->count()]
            + $this->tally($g, 'status', $statuses)
            + ['collected' => ['b', $this->money($collected($g))], 'expected' => $this->money($expected($g))];

        return [
            'kpis' => $kpis,
            'table' => [
                'columns' => ['section' => 'Section', 'members' => 'Members'] + array_combine($statuses, $statuses) + ['collected' => 'Collected', 'expected' => 'Expected'],
                'numeric' => ['members', ...$statuses, 'collected', 'expected'],
                'rows' => $members->groupBy('section')->map(fn ($g, $label) => $summary($g, ['sec', $label]))->values()->all(),
                'total' => $posted ? $summary($members, 'All sections') : null,
                'empty' => $empty,
            ],
        ];
    }

    /** @return array<string, int> how many rows carry each label, in the labels' order */
    private function tally(Collection $rows, string $field, array $labels): array
    {
        return collect($labels)->mapWithKeys(fn ($label) => [$label => $rows->where($field, $label)->count()])->all();
    }

    private function kpi(string $label, int|string $value, ?int $of, string $sub): array
    {
        return compact('label', 'value', 'of', 'sub');
    }

    private function pct(int|float $part, int|float $whole): int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : 0;
    }

    private function money(float $amount): string
    {
        return '₱'.str_replace('.00', '', number_format($amount, 2));
    }

    /** A query value when it is one of $allowed, else $default. */
    private function pick(Request $request, string $key, array $allowed, ?string $default): ?string
    {
        $value = $request->query($key);

        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    /**
     * The picked columns as plain text, each cell with its tone: a status
     * column is coloured whole, a status cell by its own value. The legend
     * says what each colour means, with the column it came from.
     */
    private function plain(array $table, array $keys): array
    {
        $text = fn ($cell) => ! is_array($cell) ? $cell : ($cell[0] === 'seat' ? $cell[1].': '.$cell[2] : $cell[1]);
        $tone = fn ($key, $cell) => self::TONES[$key] ?? (is_array($cell) && $cell[0] === 'res' ? self::TONES[$cell[1]] ?? 'none' : null);
        $legend = [];

        foreach ($keys as $key) {
            if (isset(self::TONES[$key])) {
                $legend[$table['columns'][$key]] = [self::TONES[$key], $key];

                continue;
            }
            foreach ($table['rows'] as $row) {
                if (is_array($row[$key] ?? null) && $row[$key][0] === 'res') {
                    $legend[$row[$key][1]] = [$tone($key, $row[$key]), $key];
                }
            }
        }

        $line = fn (array $row) => array_map(fn ($key) => ['text' => $text($row[$key] ?? ''), 'tone' => $tone($key, $row[$key] ?? null)], $keys);

        return [
            'keys' => $keys,
            'headings' => array_map(fn ($key) => $table['columns'][$key], $keys),
            'numeric' => array_map(fn ($key) => in_array($key, $table['numeric'], true), $keys),
            'tones' => array_map(fn ($key) => self::TONES[$key] ?? null, $keys),
            'rows' => array_map($line, $table['rows']),
            'total' => $table['total'] && $table['rows'] ? $line($table['total']) : null,
            'legend' => $legend,
            'empty' => $table['empty'],
        ];
    }

    /**
     * Title, scope, the summary figures and the legend on top, then a gold
     * heading row kept in view while scrolling, the rows, and the total.
     */
    private function excel(string $sheetTitle, string $title, string $scope, array $tiles, array $table, string $name)
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle($sheetTitle);
        $width = count($table['headings']);
        $paint = fn ($c, $r, $tone) => $sheet->getStyle([$c, $r])->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::FILLS[$tone][0]]],
            'font' => ['color' => ['rgb' => self::FILLS[$tone][1]]],
        ]);
        // Typed explicitly, so a team named "=SUM(...)" stays text instead of becoming a formula.
        $put = fn ($c, $r, $value) => $sheet->setCellValueExplicit([$c, $r], $value, is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);

        $put(1, 1, $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $put(1, 2, $scope);
        $sheet->getStyle('A2')->getFont()->getColor()->setRGB('6B7280');
        foreach (array_values($tiles) as $i => $tile) {
            $put($i + 1, 3, $tile['label'].': '.$tile['value']);
        }

        $head = 5;
        if ($table['legend']) {
            $put(1, 4, 'Legend');
            $sheet->getStyle('A4')->getFont()->setBold(true);
            foreach (array_keys($table['legend']) as $i => $text) {
                $put($i + 2, 4, $text);
                $paint($i + 2, 4, $table['legend'][$text][0]);
            }
            $head = 6;
        }

        foreach ($table['headings'] as $c => $heading) {
            $put($c + 1, $head, $heading);
        }
        $sheet->getStyle([1, $head, $width, $head])->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '18191F']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C9A82C']],
        ]);

        $lines = [...$table['rows'], ...($table['total'] ? [$table['total']] : [])];
        foreach ($lines as $i => $line) {
            foreach ($line as $c => $cell) {
                $put($c + 1, $head + 1 + $i, $cell['text']);
                if ($cell['tone']) {
                    $paint($c + 1, $head + 1 + $i, $cell['tone']);
                }
            }
        }
        $last = $head + count($lines);

        $sheet->getStyle([1, $head, $width, $last])->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '9AA0AD']]],
        ]);
        if ($table['total']) {
            $sheet->getStyle([1, $last, $width, $last])->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F2F3F5']],
            ]);
        }

        // Widths from the table alone, so the long title and scope lines above don't stretch the columns.
        foreach (range(1, $width) as $c) {
            $longest = max(array_map(fn ($s) => mb_strlen((string) $s), [$table['headings'][$c - 1], ...array_map(fn ($l) => $l[$c - 1]['text'], $lines)]));
            $sheet->getColumnDimensionByColumn($c)->setWidth(min(60, max(10, $longest + 3)));

            if ($table['numeric'][$c - 1]) {
                $sheet->getStyle([$c, $head, $c, $last])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        $sheet->freezePane('A'.($head + 1));

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
