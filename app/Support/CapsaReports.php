<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\ExpensePost;
use App\Models\PanelGrade;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Models\Term;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The CaPSA's reports, built as tables.
 *
 * Every report is a list of columns and a list of rows, each row one team
 * (or one expert, for Expert Load). The page, the PDF and the Excel file are
 * all drawn from the same table, so what is on screen is what is downloaded.
 *
 * A cell is a plain string, or a small array that says how to draw it:
 *   ['b', text]               bold
 *   ['chip', text, tone]      a status pill (ok, warn, bad, none, info)
 *   ['bar', done, total]      "done of total" with a progress bar
 *   ['lines', [a, b]]         one entry per line
 *   ['slots', used, max]      "used of max" with a bar, max 0 = no limit
 *   ['num', n]                a number, sorted as one
 *
 * It only reads. Verifying a team or a receipt stays on Team Management and
 * Financial Records.
 */
class CapsaReports
{
    /** key => [name, what is in it] in the order the index lists them. */
    public const REPORTS = [
        'progress' => ['Team Progress', 'How many checklist requirements each team has done, and which teams have overdue items.'],
        'master' => ['Team Master List', "Every team with its project title, members, adviser, consultant and grammarian. The list the dean's office usually asks for."],
        'results' => ['Defense Results', "Every team's verdict and average panel score at one defense, by section."],
        'grades' => ['Student Grades', "Each student's grade at every defense held so far and their average, with their team's requirements done. A student's grade is their team's panel average."],
        'fees' => ['Fee Collection', 'Receipts handed in, approved and still to check for one defense fee, with the money collected.'],
        'experts' => ['Expert Load', 'How many teams each expert handles this school year, by role, next to another school year. Use it to spread teams fairly.'],
        'expert_list' => ['Expert List', 'The Expert Management table as a report: each expert with their roles, expertise and team slots. Pick only the columns you need, then download it as Excel or PDF.'],
        'schedule' => ['Defense Schedule', 'Date, time, room and panel of every defense in the period, ready to post or hand to the panel.'],
    ];

    /** One row per team: these can be shown as section totals and merged with each other. */
    public const TEAM_REPORTS = ['progress', 'master', 'results', 'fees'];

    public const NEW_REPORTS = ['master', 'grades', 'experts', 'schedule', 'expert_list'];

    /** The defenses Student Grades has a column for, key => stage. */
    private const GRADED_STAGES = ['title' => 'Title Defense', 'pod' => 'Pre Oral Defense', 'fod' => 'Final Oral Defense'];

    /** The Expert List's columns, key => heading. Expert Management offers these to pick from before a download. */
    public const EXPERT_LIST_COLUMNS = [
        'expert' => 'Name',
        'email' => 'Email',
        'roles' => 'Role(s)',
        'expertise' => 'Expertise',
        'accepts' => 'Slots',
        'sections' => 'Sections',
        'degree' => 'Highest degree',
        'status' => 'Status',
    ];

    /** The colour a merged report's column band is drawn in. */
    public const BANDS = ['progress' => 'grey', 'master' => 'gold', 'results' => 'blue', 'fees' => 'green'];

    public const DASH = '—';

    /**
     * The columns each one-row-per-team report has, key => heading. The section
     * is not a column: each row carries it as 'group' and the page puts a
     * section row above its teams. A column another report already shows
     * (title, members) is left to that report.
     */
    public const TEAM_COLUMNS = [
        'progress' => ['team' => 'Team', 'milestone' => 'Current milestone', 'done' => 'Requirements done', 'status' => 'Status'],
        'master' => ['team' => 'Team', 'title' => 'Project title', 'members' => 'Members', 'adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian'],
        'results' => ['team' => 'Team', 'verdict' => 'Verdict', 'average' => 'Average score'],
        'fees' => ['team' => 'Team', 'paid' => 'Paid', 'waiting' => 'Receipts to check', 'collected' => 'Collected', 'status' => 'Status'],
    ];

    private const ROLES = ['adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian'];

    private ?array $progressCache = null;

    /** @var array<string, Collection> approved assignments, by the team list they were asked for */
    private array $assignmentCache = [];

    public function __construct(
        private SectionScope $scope,
        private TeamChecklist $checklists,
        private FeeBoard $feeBoard,
    ) {}

    /**
     * The period and filters asked for, checked against what exists, and the
     * teams they leave.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function context(CapstoneExpert $capsa, array $query): array
    {
        $pick = fn (string $key, array $allowed, ?string $default) => is_string($query[$key] ?? null) && in_array($query[$key], $allowed, true) ? $query[$key] : $default;

        $thisYear = Term::currentSchoolYear();
        $years = StudentSchoolYear::acrossAllTerms()->whereNotNull('school_year')->where('school_year', '!=', '0000')
            ->distinct()->pluck('school_year')
            ->merge(Term::pluck('school_year'))->push($thisYear)
            ->filter()->unique()->sortDesc()->values()->all();
        $year = $pick('year', $years, $thisYear);

        $terms = Term::where('school_year', $year)->orderBy('semester')->orderBy('capstone_stage')->get();
        $termOptions = ['' => 'Whole school year']
            + $terms->mapWithKeys(fn (Term $t) => [(string) $t->term_id => $t->semesterLabel().' · Capstone '.$t->capstone_stage])->all();
        // The running term is only the first visit's default; "Whole school year" is a choice too.
        $defaultTerm = (string) $terms->firstWhere('is_active', true)?->term_id;
        $term = $pick('term', array_map('strval', array_keys($termOptions)), array_key_exists('term', $query) ? '' : $defaultTerm);

        $programs = $capsa->handledPrograms();
        $programOptions = ['' => count($programs) > 1 ? 'All programs' : ($programs[0] ?? 'All programs')]
            + (count($programs) > 1 ? array_combine($programs, $programs) : []);
        $program = $pick('program', array_keys($programOptions), '');

        $mine = $capsa->sections ?? [];
        sort($mine);
        $sectionList = array_values(array_filter($mine, fn ($s) => $program === '' || TeamChecklist::programOf($s) === $program));
        $sectionOptions = ['' => 'All sections'] + array_combine($sectionList, $sectionList) + [SectionScope::UNASSIGNED => 'Unassigned'];
        $section = $pick('section', array_map('strval', array_keys($sectionOptions)), '');

        $records = StudentSchoolYear::acrossAllTerms()
            ->where('school_year', $year)
            // Same rule as everywhere else: a row with no term belongs to the running one.
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q->where('term_id', $term)
                ->when((int) $term === Term::activeId(), fn ($q) => $q->orWhereNull('term_id'))))
            ->whereNotNull('team_id')
            ->with('student')
            ->get()
            ->groupBy('team_id');

        $found = $this->scope->filterTeams(
            Team::whereIn('team_id', $records->keys())->orderBy('team_name')->get(),
            $records,
            $mine,
        );

        $teams = $found['teams']
            ->filter(function (Team $team) use ($found, $section, $program) {
                $label = $found['teamSections'][$team->team_id];

                return ($section === '' || $label === $section)
                    && ($program === '' || $label === SectionScope::UNASSIGNED || TeamChecklist::programOf($label) === $program);
            })
            ->sortBy(fn (Team $team) => [$found['teamSections'][$team->team_id] === SectionScope::UNASSIGNED, $found['teamSections'][$team->team_id], mb_strtolower($team->team_name)])
            ->values();

        $earlier = array_values(array_filter($years, fn ($y) => $y < $year));
        $compareDefault = $earlier[0] ?? self::previousYear($year);
        $compareOptions = array_values(array_unique([self::previousYear($year), ...$earlier]));
        rsort($compareOptions);

        return [
            'capsa' => $capsa,
            'years' => $years,
            'year' => $year,
            'thisYear' => $thisYear,
            'termOptions' => $termOptions,
            'term' => $term,
            'programOptions' => $programOptions,
            'program' => $program,
            'programs' => $program !== '' ? [$program] : $programs,
            'sectionOptions' => $sectionOptions,
            'section' => $section,
            'stage' => $pick('stage', DefenseBoard::STAGES, DefenseBoard::STAGES[1] ?? DefenseBoard::STAGES[0]),
            'fee' => $pick('fee', DefenseBoard::PAID_STAGES, DefenseBoard::PAID_STAGES[0]),
            'compare' => $pick('compare', $compareOptions, $compareDefault),
            'compareOptions' => $compareOptions,
            'records' => $records,
            'teams' => $teams,
            'teamSections' => $found['teamSections'],
        ];
    }

    /** "2026-2027" gives "2025-2026"; anything else is left to the caller's list. */
    public static function previousYear(string $year): string
    {
        return preg_match('/^(\d{4})\D(\d{4})$/', $year, $m) ? ($m[1] - 1).'-'.($m[2] - 1) : $year;
    }

    /** Stage and fee filters each report takes, as field => [label, options]. */
    public function extraFilters(string $report, array $ctx): array
    {
        return match ($report) {
            'results', 'schedule' => ['stage' => ['Defense', array_combine(DefenseBoard::STAGES, DefenseBoard::STAGES)]],
            'fees' => ['fee' => ['Fee', array_combine(DefenseBoard::PAID_STAGES, array_map(fn ($s) => $s.' fee', DefenseBoard::PAID_STAGES))]],
            'experts' => ['compare' => ['Compare with', array_combine($ctx['compareOptions'], array_map(fn ($y) => 'S.Y. '.$y, $ctx['compareOptions']))]],
            default => [],
        };
    }

    /** The name shown over a report's columns when it is merged into another. */
    public function bandLabel(string $report, array $ctx): string
    {
        return self::REPORTS[$report][0].match ($report) {
            'results' => ' · '.$ctx['stage'],
            'fees' => ' · '.$ctx['fee'].' fee',
            default => '',
        };
    }

    /**
     * One report as a table.
     *
     * @return array{columns: array<string, array{label: string, align: string}>, rows: list<array{id: ?int, cells: array<string, mixed>}>, total: ?array, summary: string, empty: string}
     */
    public function build(string $report, array $ctx, bool $bySection = false): array
    {
        return match ($report) {
            'progress' => $bySection ? $this->progressBySection($ctx) : $this->progress($ctx),
            'master' => $bySection ? $this->masterBySection($ctx) : $this->master($ctx),
            'results' => $bySection ? $this->resultsBySection($ctx) : $this->results($ctx),
            'grades' => $this->grades($ctx),
            'fees' => $bySection ? $this->feesBySection($ctx) : $this->fees($ctx),
            'experts' => $this->experts($ctx),
            'expert_list' => $this->expertList(),
            'schedule' => $this->schedule($ctx),
        };
    }

    /**
     * What the side panel shows for each team: members, experts, overdue items.
     *
     * @param  int[]  $teamIds
     * @return array<int, array<string, mixed>>
     */
    public function teamDetails(array $ctx, array $teamIds): array
    {
        $progress = $this->progressFor($ctx);
        $experts = $this->approvedAssignments($teamIds)->groupBy('team_id');
        $teams = $ctx['teams']->keyBy('team_id');
        $out = [];

        foreach ($teamIds as $id) {
            if (! $team = $teams->get($id)) {
                continue;
            }

            $p = $progress[$id] ?? null;
            $roles = $this->rolesOf($experts->get($id, collect()));

            $out[$id] = [
                'name' => $team->team_name,
                'section' => $ctx['teamSections'][$id] ?? '',
                'title' => $team->project_title ?: null,
                'leader' => (int) $team->leader_id,
                'members' => $this->memberNames($ctx, $team, true),
                'experts' => array_map(fn ($label, $key) => ['role' => $label, 'name' => $roles[$key] ?? null], self::ROLES, array_keys(self::ROLES)),
                'milestone' => $p ? $this->currentMilestone($p) : null,
                'done' => $p->completed ?? 0,
                'total' => $p->total ?? 0,
                'overdue' => $p ? $p->items->where('state', MilestoneProgress::OVERDUE)->map(fn ($i) => $i->item->title)->values()->all() : [],
                'verified' => (bool) $team->is_confirmed,
                'url' => route('capsa.team_management.show', $team->team_id),
            ];
        }

        return $out;
    }

    // ---------------------------------------------------------------- progress

    private function progress(array $ctx): array
    {
        $progress = $this->progressFor($ctx);
        $rows = [];
        $behind = 0;

        foreach ($ctx['teams'] as $team) {
            $p = $progress[$team->team_id] ?? null;
            $overdue = $p->overdue ?? 0;
            $status = $this->progressStatus($team, $overdue);
            $behind += $overdue > 0 ? 1 : 0;

            $rows[] = ['id' => $team->team_id, 'group' => $this->sectionName($ctx, $team), 'cells' => [
                'team' => ['b', $team->team_name],
                'milestone' => $p && $p->total > 0 ? $this->currentMilestone($p) : self::DASH,
                'done' => ['bar', $p->completed ?? 0, $p->total ?? 0],
                // The overdue count is the status when there is one; sorts most overdue first.
                'status' => $status === 'Has overdue items'
                    ? ['tone', $overdue.' overdue', 'bad', -$overdue]
                    : ['tone', $status, $this->tone($status)],
            ]];
        }

        return [
            'columns' => $this->cols(self::TEAM_COLUMNS['progress'], ['done']),
            'rows' => $rows,
            'total' => null,
            'summary' => $this->count(count($rows), 'team').' · '.$behind.' with overdue items',
            'empty' => 'No teams match these filters.',
        ];
    }

    private function progressBySection(array $ctx): array
    {
        $progress = $this->progressFor($ctx);
        $statuses = ['On track', 'Has overdue items', 'Not verified'];
        $line = function (Collection $teams, $first) use ($progress, $statuses) {
            $done = $teams->sum(fn ($t) => $progress[$t->team_id]->completed ?? 0);
            $total = $teams->sum(fn ($t) => $progress[$t->team_id]->total ?? 0);
            $status = $teams->map(fn ($t) => $this->progressStatus($t, $progress[$t->team_id]->overdue ?? 0));

            return [
                'section' => $first,
                'teams' => ['num', $teams->count()],
                'done' => ['bar', $done, $total],
                'overdue' => ['num', $teams->sum(fn ($t) => $progress[$t->team_id]->overdue ?? 0)],
            ] + collect($statuses)->mapWithKeys(fn ($s) => [$s => ['num', $status->filter(fn ($x) => $x === $s)->count()]])->all();
        };

        return $this->bySection($ctx, $ctx['teams'], $line,
            ['section' => 'Section', 'teams' => 'Teams', 'done' => 'Requirements done', 'overdue' => 'Overdue items', 'On track' => 'On track', 'Has overdue items' => 'With overdue items', 'Not verified' => 'Not verified'],
            ['teams', 'done', 'overdue', ...$statuses]);
    }

    private function progressStatus(Team $team, int $overdue): string
    {
        return ! $team->is_confirmed ? 'Not verified' : ($overdue > 0 ? 'Has overdue items' : 'On track');
    }

    private function currentMilestone(object $p): string
    {
        return $p->milestones->first(fn ($m) => $m->completed < $m->total)?->milestone->name ?? 'All done';
    }

    /** @return array<int, object> checklist progress by team id, worked out once per request */
    private function progressFor(array $ctx): array
    {
        if ($this->progressCache !== null) {
            return $this->progressCache;
        }

        $out = [];
        foreach ($ctx['teams']->groupBy(fn ($t) => $ctx['teamSections'][$t->team_id]) as $label => $teams) {
            $checklist = $this->checklists->forSection($label === SectionScope::UNASSIGNED ? '' : $label);
            if ($checklist) {
                $out += MilestoneProgress::forTeams($checklist, $teams->pluck('team_id'));
            }
        }

        return $this->progressCache = $out;
    }

    // ------------------------------------------------------------------ master

    private function master(array $ctx): array
    {
        $assignments = $this->approvedAssignments($ctx['teams']->pluck('team_id')->all())->groupBy('team_id');
        $rows = [];

        foreach ($ctx['teams'] as $team) {
            $roles = $this->rolesOf($assignments->get($team->team_id, collect()));
            $members = $this->memberNames($ctx, $team);

            $rows[] = ['id' => $team->team_id, 'group' => $this->sectionName($ctx, $team), 'cells' => [
                'team' => ['b', $team->team_name],
                'title' => $team->project_title ?: self::DASH,
                'members' => $members ? ['lines', $members] : self::DASH,
                'adviser' => $roles['adviser'] ?? self::DASH,
                'consultant' => $roles['consultant'] ?? self::DASH,
                'grammarian' => $roles['grammarian'] ?? self::DASH,
            ]];
        }

        return [
            'columns' => $this->cols(self::TEAM_COLUMNS['master'], []),
            'rows' => $rows,
            'total' => null,
            'summary' => $this->count(count($rows), 'team').' · '.collect($rows)->filter(fn ($r) => $r['cells']['adviser'] !== self::DASH)->count().' with an adviser',
            'empty' => 'No teams match these filters.',
        ];
    }

    private function masterBySection(array $ctx): array
    {
        $assignments = $this->approvedAssignments($ctx['teams']->pluck('team_id')->all())->groupBy('team_id');
        $line = function (Collection $teams, $first) use ($ctx, $assignments) {
            $roles = $teams->map(fn ($t) => $this->rolesOf($assignments->get($t->team_id, collect())));

            return [
                'section' => $first,
                'teams' => ['num', $teams->count()],
                'members' => ['num', $teams->sum(fn ($t) => count($this->memberNames($ctx, $t)))],
                'titled' => ['num', $teams->filter(fn ($t) => (bool) $t->project_title)->count()],
            ] + collect(self::ROLES)->mapWithKeys(fn ($label, $key) => [$key => ['num', $roles->filter(fn ($r) => isset($r[$key]))->count()]])->all();
        };

        return $this->bySection($ctx, $ctx['teams'], $line,
            ['section' => 'Section', 'teams' => 'Teams', 'members' => 'Members', 'titled' => 'With a title', 'adviser' => 'With an adviser', 'consultant' => 'With a consultant', 'grammarian' => 'With a grammarian'],
            ['teams', 'members', 'titled', 'adviser', 'consultant', 'grammarian']);
    }

    // ----------------------------------------------------------------- results

    /** @return Collection<int, DefenseSchedule> the latest attempt at the stage, by team id */
    private function schedulesFor(Collection $teams, string $stage): Collection
    {
        // A redefense is a new attempt with its own panel; the latest is the one that stands.
        return DefenseSchedule::whereIn('team_id', $teams->pluck('team_id'))
            ->where('defense_type', $stage)
            ->with(['panels.expert', 'grades'])
            ->get()
            ->sortByDesc('attempt')
            ->unique('team_id')
            ->keyBy('team_id');
    }

    /** Results, fees and the schedule count the teams the CaPSA verified, as the defense and fee pages do. */
    private function verifiedTeams(array $ctx): Collection
    {
        return $ctx['teams']->filter(fn (Team $t) => (bool) $t->is_confirmed)->values();
    }

    private function results(array $ctx): array
    {
        $teams = $this->verifiedTeams($ctx);
        $schedules = $this->schedulesFor($teams, $ctx['stage']);
        $rows = [];
        $scores = collect();

        foreach ($teams as $team) {
            $s = $schedules->get($team->team_id);
            $verdict = $s?->outcome ? DefenseOutcome::label($s->outcome, $ctx['stage']) : 'No result yet';
            $average = $s?->panelAverage();
            if ($average !== null) {
                $scores->push($average);
            }

            $rows[] = ['id' => $team->team_id, 'group' => $this->sectionName($ctx, $team), 'cells' => [
                'team' => ['b', $team->team_name],
                'verdict' => ['chip', $verdict, $this->verdictTone($s?->outcome)],
                'average' => $average === null ? self::DASH : ['num', DefenseScores::format($average)],
            ]];
        }

        $withVerdict = $schedules->filter(fn ($s) => $s->outcome)->count();
        $avg = $scores->isEmpty() ? self::DASH : DefenseScores::format(round($scores->avg(), 2));

        return [
            'columns' => $this->cols(self::TEAM_COLUMNS['results'], ['average']),
            'rows' => $rows,
            'total' => $rows ? ['team' => ['b', 'All sections'], 'verdict' => $withVerdict.' of '.count($rows).' teams have a verdict', 'average' => ['b', $avg]] : null,
            'summary' => $this->count(count($rows), 'team').' · '.$ctx['stage'].' · average of scored teams: '.$avg,
            'empty' => 'No verified teams match these filters.',
        ];
    }

    private function resultsBySection(array $ctx): array
    {
        $teams = $this->verifiedTeams($ctx);
        $schedules = $this->schedulesFor($teams, $ctx['stage']);
        $label = fn (Team $t) => ($o = $schedules->get($t->team_id)?->outcome) ? DefenseOutcome::label($o, $ctx['stage']) : 'No result yet';
        $labels = collect(DefenseOutcome::forStage($ctx['stage']))->map(fn ($o) => DefenseOutcome::label($o, $ctx['stage']))
            ->merge($teams->map($label))->push('No result yet')
            ->unique()->reject(fn ($l) => $l === 'No result yet')->push('No result yet')->values()->all();

        $line = function (Collection $g, $first) use ($label, $labels, $schedules) {
            $scores = $g->map(fn ($t) => $schedules->get($t->team_id)?->panelAverage())->reject(fn ($a) => $a === null);

            return ['section' => $first, 'teams' => ['num', $g->count()]]
                + collect($labels)->mapWithKeys(fn ($l) => [$l => ['num', $g->filter(fn ($t) => $label($t) === $l)->count()]])->all()
                + ['average' => $scores->isEmpty() ? self::DASH : ['num', DefenseScores::format(round($scores->avg(), 2))]];
        };

        return $this->bySection($ctx, $teams, $line,
            ['section' => 'Section', 'teams' => 'Teams'] + array_combine($labels, $labels) + ['average' => 'Average score'],
            ['teams', ...$labels, 'average']);
    }

    private function verdictTone(?string $outcome): string
    {
        return match (true) {
            $outcome === null => 'none',
            DefenseOutcome::isPass($outcome) => 'ok',
            default => 'bad',
        };
    }

    // ------------------------------------------------------------------ grades

    /**
     * One row per student, by section, team, then name. A student's grade at a
     * defense is their team's panel average there. The average is over the
     * defenses held so far, each as a share of its rubric's points, so a
     * 30-point Title Defense and a 100-point Final weigh the same.
     */
    private function grades(array $ctx): array
    {
        $byStage = collect(self::GRADED_STAGES)->map(fn ($stage) => $this->schedulesFor($ctx['teams'], $stage)->load('grades.rubric'));
        $rows = [];
        $graded = 0;

        foreach ($ctx['teams'] as $team) {
            $held = $byStage->map(fn ($schedules) => $schedules->get($team->team_id));
            $shares = $held->map(fn ($s) => $this->scoreShare($s))->filter(fn ($p) => $p !== null);
            $students = $ctx['records']->get($team->team_id, collect())
                ->map(fn ($sy) => $sy->student)->filter()->unique('student_id')
                ->sortBy(fn ($s) => mb_strtolower($s->l_name.' '.$s->f_name));

            foreach ($students as $student) {
                $graded += $shares->isNotEmpty() ? 1 : 0;
                $rows[] = ['id' => $team->team_id, 'group' => $this->sectionName($ctx, $team), 'cells' => [
                    'number' => $student->student_number ?: self::DASH,
                    'name' => ['b', trim($student->l_name.', '.$student->f_name, ', ')],
                    'team' => $team->team_name,
                    ...$held->map(fn ($s) => $this->scoreCell($s))->all(),
                    'average' => $shares->isEmpty() ? self::DASH : ['num', DefenseScores::format(round($shares->avg(), 2)).'%', round($shares->avg(), 2)],
                ]];
            }
        }

        return [
            'columns' => $this->cols(
                ['number' => 'Student no.', 'name' => 'Name', 'team' => 'Team', 'title' => 'Title Defense', 'pod' => 'Pre-oral', 'fod' => 'Final', 'average' => 'Average'],
                ['title', 'pod', 'fod', 'average'],
            ),
            'rows' => $rows,
            'total' => null,
            'summary' => $this->count(count($rows), 'student').' · '.$graded.' graded at a defense so far. — means that defense has not been graded yet.',
            'empty' => 'No students match these filters.',
        ];
    }

    /** What the rubric was out of: the points of the rubric the panel graded on, 100 if none is known. */
    private function rubricTotal(DefenseSchedule $s): float
    {
        return (float) ($s->grades->firstWhere('status', PanelGrade::SUBMITTED)?->rubric?->total_points ?: 100);
    }

    /** The panel average as a percentage of the rubric, or null until the whole panel has graded. */
    private function scoreShare(?DefenseSchedule $s): ?float
    {
        $avg = $s?->panelAverage();

        return $avg === null ? null : $avg / $this->rubricTotal($s) * 100;
    }

    /** "85.17", or "26 / 30" when the rubric is not out of 100; sorts by the percentage. */
    private function scoreCell(?DefenseSchedule $s): mixed
    {
        $share = $this->scoreShare($s);
        if ($share === null) {
            return self::DASH;
        }
        $total = $this->rubricTotal($s);

        return ['num', DefenseScores::format($s->panelAverage()).($total === 100.0 ? '' : ' / '.DefenseScores::format($total)), round($share, 2)];
    }

    // -------------------------------------------------------------------- fees

    /** @return array{0: Collection, 1: bool} each verified team's members with their receipt status, and whether the fee is posted */
    private function feeMembers(array $ctx): array
    {
        // One fee post per program per stage (the active one, else the newest).
        $posts = ExpensePost::where('defense_type', $ctx['fee'])->whereIn('course', $ctx['programs'])
            ->orderByDesc('is_active')->orderByDesc('id')
            ->get()->unique('course')->keyBy('course');

        $members = collect();
        foreach ($this->verifiedTeams($ctx) as $team) {
            $label = $ctx['teamSections'][$team->team_id];
            $post = $posts->get(TeamChecklist::programOf($label));

            foreach ($this->feeBoard->members($ctx['records']->get($team->team_id, collect())) as $m) {
                $members->push($m + ['team' => $team, 'section' => $label, 'post' => $post]);
            }
        }

        $receipts = $this->feeBoard->receipts($posts->pluck('id'), $members->pluck('id'));

        return [$members->unique('id')->map(fn ($m) => $m + [
            'status' => ! $m['post'] ? 'No fee' : match ($receipts[$m['post']->id.':'.$m['id']]['status'] ?? null) {
                'verified' => 'Verified',
                'pending' => 'Waiting',
                'sent_back' => 'Sent back',
                default => 'Not uploaded',
            },
        ])->values(), $posts->isNotEmpty()];
    }

    private function fees(array $ctx): array
    {
        [$members, $posted] = $this->feeMembers($ctx);
        $byTeam = $members->groupBy(fn ($m) => $m['team']->team_id);
        $rows = [];

        foreach ($this->verifiedTeams($ctx) as $team) {
            $g = $byTeam->get($team->team_id, collect());
            $paid = $g->where('status', 'Verified')->count();
            $waiting = $g->where('status', 'Waiting')->count();
            $status = match (true) {
                ! $posted || $g->isNotEmpty() && $g->every(fn ($m) => $m['status'] === 'No fee') => 'Fee not posted',
                $g->isEmpty() => 'No members',
                $paid === $g->count() => 'Paid',
                $waiting > 0 => 'Receipt to check',
                $g->contains('status', 'Sent back') => 'Sent back',
                default => 'Not paid',
            };

            $rows[] = ['id' => $team->team_id, 'group' => $this->sectionName($ctx, $team), 'cells' => [
                'team' => ['b', $team->team_name],
                'paid' => $g->isEmpty() ? self::DASH : $paid.' of '.$g->count(),
                'waiting' => ['num', $waiting],
                'collected' => $this->money($this->collected($g)),
                'status' => ['chip', $status, $this->tone($status)],
            ]];
        }

        $collected = $this->money($this->collected($members));

        return [
            'columns' => $this->cols(self::TEAM_COLUMNS['fees'], ['paid', 'waiting', 'collected']),
            'rows' => $rows,
            'total' => $rows && $posted ? [
                'team' => ['b', 'All sections'],
                'paid' => ['b', $members->where('status', 'Verified')->count().' of '.$members->count()],
                'waiting' => ['b', (string) $members->where('status', 'Waiting')->count()],
                'collected' => ['b', $collected], 'status' => '',
            ] : null,
            'summary' => $posted
                ? $this->count(count($rows), 'team').' · '.$ctx['fee'].' fee · '.$collected.' collected, '.$members->where('status', 'Waiting')->count().' receipts to check'
                : 'The '.$ctx['fee'].' fee has not been posted yet',
            'empty' => $posted ? 'No verified teams match these filters.' : 'The '.$ctx['fee'].' fee has not been posted yet.',
        ];
    }

    private function feesBySection(array $ctx): array
    {
        [$members, $posted] = $this->feeMembers($ctx);
        $statuses = ['Verified', 'Waiting', 'Sent back', 'Not uploaded'];
        $bySection = $members->groupBy('section');

        $line = fn (Collection $g, $first) => ['section' => $first, 'members' => ['num', $g->count()]]
            + collect($statuses)->mapWithKeys(fn ($s) => [$s => ['num', $g->where('status', $s)->count()]])->all()
            + ['collected' => $this->money($this->collected($g)), 'expected' => $this->money($g->sum(fn ($m) => (float) ($m['post']->amount ?? 0)))];

        $rows = $bySection->map(fn ($g, $label) => ['id' => null, 'cells' => $line($g, ['b', $label === SectionScope::UNASSIGNED ? 'Unassigned' : $label])])->values()->all();

        return [
            'columns' => $this->cols(['section' => 'Section', 'members' => 'Members'] + array_combine($statuses, $statuses) + ['collected' => 'Collected', 'expected' => 'Expected'], ['members', ...$statuses, 'collected', 'expected']),
            'rows' => $posted ? $rows : [],
            'total' => $posted && $rows ? $this->bold($line($members, 'All sections')) : null,
            'summary' => $posted ? $this->count(count($rows), 'section').' · '.$ctx['fee'].' fee' : 'The '.$ctx['fee'].' fee has not been posted yet',
            'empty' => $posted ? 'No members match these filters.' : 'The '.$ctx['fee'].' fee has not been posted yet.',
        ];
    }

    private function collected(Collection $members): float
    {
        return $members->where('status', 'Verified')->sum(fn ($m) => (float) ($m['post']->amount ?? 0));
    }

    // ---------------------------------------------------------------- schedule

    private function schedule(array $ctx): array
    {
        $teams = $this->verifiedTeams($ctx);
        $schedules = $this->schedulesFor($teams, $ctx['stage']);
        $rows = [];

        foreach ($teams as $team) {
            $s = $schedules->get($team->team_id);
            $when = $s?->schedule_date;
            $chair = $s?->panels->firstWhere('panel_role', 'Panel Chair');
            $others = $s ? $s->panels->where('panel_role', '!=', 'Panel Chair')->map(fn ($p) => $p->expert?->name)->filter()->values()->all() : [];

            $rows[] = ['id' => $team->team_id, 'when' => $when?->timestamp, 'cells' => [
                'date' => $when ? ['num', $when->format('D, M j, Y'), $when->timestamp] : ['chip', 'Not scheduled', 'none'],
                'time' => $when ? $when->format('g:i A').($s->end_date ? '–'.$s->end_date->format('g:i A') : '') : self::DASH,
                'room' => $s?->venue ?: self::DASH,
                'team' => ['b', $team->team_name],
                'section' => $this->sectionName($ctx, $team),
                'chair' => $chair?->expert?->name ?? self::DASH,
                'panel' => $others ? ['lines', $others] : self::DASH,
            ]];
        }

        // Soonest first; teams with no date at the end.
        usort($rows, fn ($a, $b) => [$a['when'] === null, $a['when']] <=> [$b['when'] === null, $b['when']]);
        $scheduled = collect($rows)->whereNotNull('when')->count();

        return [
            'columns' => $this->cols(['date' => 'Date', 'time' => 'Time', 'room' => 'Room', 'team' => 'Team', 'section' => 'Section', 'chair' => 'Panel chair', 'panel' => 'Panel members'], []),
            'rows' => array_map(fn ($r) => ['id' => $r['id'], 'cells' => $r['cells']], $rows),
            'total' => null,
            'summary' => $this->count(count($rows), 'team').' · '.$scheduled.' scheduled, '.(count($rows) - $scheduled).' not scheduled · '.$ctx['stage'],
            'empty' => 'No verified teams match these filters.',
        ];
    }

    // ----------------------------------------------------------------- experts

    private function experts(array $ctx): array
    {
        // A team belongs to the program of its section; one with no section yet counts for every program, as on the other reports.
        $inScope = fn (string $section) => ! $ctx['programs'] || $section === '' || in_array(TeamChecklist::programOf($section), $ctx['programs'], true);

        // A school year's teams in the chosen program(s), and every expert role on any of that year's teams.
        $year = function (string $year) use ($inScope) {
            $records = StudentSchoolYear::acrossAllTerms()->where('school_year', $year)->whereNotNull('team_id')->with('student')->get()->groupBy('team_id');
            $scoped = Team::whereIn('team_id', $records->keys())->get()
                ->filter(fn (Team $t) => $inScope($this->scope->sectionFor($t, $records[$t->team_id])))
                ->pluck('team_id')->map(fn ($id) => (int) $id)->all();
            $roles = $this->approvedAssignments($records->keys()->all(), fresh: true)
                ->map(fn ($a) => ['expert' => $a->expert_id, 'role' => $this->roleKey($a), 'id' => (int) $a->team_id, 'team' => $a->team?->team_name, 'in' => in_array((int) $a->team_id, $scoped, true)])
                ->filter(fn ($a) => $a['role'] !== null);

            return [$scoped, $roles];
        };

        [$teamIds, $allNow] = $year($ctx['year']);
        $now = $allNow->where('in', true)->groupBy('expert');
        $then = $year($ctx['compare'])[1]->where('in', true)->groupBy('expert');
        $waiting = TeamExpertAssignment::whereIn('team_id', $teamIds)->where('status', 'pending')->get()->groupBy('expert_id')->map->count();

        $takesProgram = fn (CapstoneExpert $e) => ! $ctx['programs'] || collect($ctx['programs'])->contains(fn ($p) => $e->acceptsProgram($p));
        $rolesOf = fn (CapstoneExpert $e) => $e->roles->toBase()->map(fn ($r) => strtolower((string) $r->role_name))->intersect(array_keys(self::ROLES))->values();

        // Everyone who can take a team, so an expert with none shows up as free rather than missing.
        $experts = CapstoneExpert::where('approved_status', true)->with('roles')->get()
            ->filter(fn (CapstoneExpert $e) => $now->has($e->expert_id) || $then->has($e->expert_id) || ($rolesOf($e)->isNotEmpty() && $takesProgram($e)));

        // Slots count teams in every program; an expert's open slots are what is left of their limit for the program(s) shown and overall.
        $usedAll = fn (CapstoneExpert $e) => $allNow->where('expert', $e->expert_id)->pluck('id')->unique()->count();
        $open = function (CapstoneExpert $e) use ($ctx, $now, $usedAll) {
            if (! $e->hasSetPrograms() || $e->deactivated_at) {
                return 0;
            }
            $caps = collect($e->parsedCapacity());
            $max = (int) ($e->max_teams ?? $caps->sum(1));
            $cap = ($caps->count() === 1 && $caps[0][0] === 'ALL') || ! $ctx['programs'] ? $max : $caps->whereIn(0, $ctx['programs'])->sum(1);
            $usedHere = $now->get($e->expert_id, collect())->pluck('id')->unique()->count();

            return max(0, min($cap - $usedHere, $max - $usedAll($e)));
        };

        $rows = $experts->map(function (CapstoneExpert $e) use ($now, $then, $waiting, $usedAll) {
            $mine = $now->get($e->expert_id, collect());
            $last = $then->get($e->expert_id, collect())->count();
            $total = $mine->count();
            $max = (int) ($e->max_teams ?? 0);
            $asks = (int) $waiting->get($e->expert_id, 0);
            $full = $max > 0 && $usedAll($e) >= $max;
            $suggestion = $e->hasSetPrograms() ? $e->slotSuggestion($usedAll($e)) : null;

            return ['id' => null, 'now' => $total, 'name' => $e->name, 'asks' => $asks, 'full' => $full, 'cells' => [
                'expert' => ['b', $e->name],
            ] + collect(self::ROLES)->mapWithKeys(fn ($l, $key) => [$key => ['num', $mine->where('role', $key)->count()]])->all() + [
                'slots' => ['slots', $usedAll($e), $max],
                // Requests a full expert cannot take: those teams have to ask someone else unless the expert adds slots.
                'waiting' => $asks && $full ? ['tone', $asks.' · full', 'bad', $asks] : ['num', $asks],
                'suggestion' => $suggestion ? ['tone', $suggestion, ['Increase slots' => 'ok', 'Reduce slots' => 'bad'][$suggestion] ?? 'none'] : self::DASH,
                'last' => ['num', $last],
                'change' => $this->change($total, $last),
                'teams' => $mine->isEmpty() ? self::DASH : $mine->pluck('team')->filter()->unique()->sort()->implode(', '),
            ]];
        })->sort(fn ($a, $b) => [$b['now'], $a['name']] <=> [$a['now'], $b['name']])->values();

        $nowTotal = $rows->sum('now');
        $lastTotal = $rows->sum(fn ($r) => $r['cells']['last'][1]);
        $enough = $this->enoughExperts($ctx, $teamIds, $allNow->where('in', true), $experts, $rolesOf, $takesProgram, $open, $rows);

        return [
            'columns' => $this->cols(['expert' => 'Expert', 'adviser' => 'Adviser', 'consultant' => 'Consultant', 'grammarian' => 'Grammarian', 'slots' => 'Slots used', 'waiting' => 'Requests waiting', 'suggestion' => 'Suggestion', 'last' => 'S.Y. '.$ctx['compare'], 'change' => 'Change', 'teams' => 'Teams they handle'], ['adviser', 'consultant', 'grammarian', 'waiting', 'last']),
            'rows' => $rows->map(fn ($r) => ['id' => null, 'cells' => $r['cells']])->all(),
            'total' => $rows->isEmpty() ? null : ['expert' => ['b', 'All experts']]
                + collect(self::ROLES)->mapWithKeys(fn ($l, $key) => [$key => ['b', (string) $rows->sum(fn ($r) => $r['cells'][$key][1])]])->all()
                + ['slots' => '', 'waiting' => ['b', (string) $enough['waiting']], 'suggestion' => '', 'last' => ['b', (string) $lastTotal], 'change' => $this->change($nowTotal, $lastTotal), 'teams' => ''],
            'summary' => $this->count($rows->count(), 'expert').' · '.$nowTotal.' team roles in S.Y. '.$ctx['year'].', '.$lastTotal.' in S.Y. '.$ctx['compare'].'. A team counts once for each role the expert holds on it.',
            'empty' => 'No experts to show yet.',
            'enough' => $enough,
            'notes' => $this->enoughNotes($enough),
        ];
    }

    /**
     * The "Enough experts?" box: per role, the teams still needing one
     * against the open slots of the experts who take it, now and once the
     * students not yet in a team form theirs (five to a team); requests
     * waiting on full experts; and how the panel seats are spread.
     */
    private function enoughExperts(array $ctx, array $teamIds, Collection $roles, Collection $experts, \Closure $rolesOf, \Closure $takesProgram, \Closure $open, Collection $rows): array
    {
        $verdict = fn (int $need, int $slots) => $slots >= $need ? 'Enough' : 'Short by '.($need - $slots);

        // Students this year with no team in any of their rows for the year.
        $loose = StudentSchoolYear::acrossAllTerms()->where('school_year', $ctx['year'])->with('student')->get()
            ->groupBy('student_id')
            ->filter(function (Collection $records) use ($ctx) {
                $student = $records->first()->student;
                if (! $student || $records->contains(fn ($sy) => $sy->team_id)) {
                    return false;
                }
                $label = (string) Student::computeSectionLabel($student->course, $student->year_section);

                return ! $ctx['programs'] || $label === '' || in_array(TeamChecklist::programOf($label), $ctx['programs'], true);
            })
            ->count();
        $later = (int) ceil($loose / 5);

        $takers = $experts->filter(fn (CapstoneExpert $e) => ! $e->deactivated_at && $rolesOf($e)->isNotEmpty() && $takesProgram($e));
        $teams = count($teamIds);
        $table = [];
        foreach (self::ROLES as $key => $label) {
            $with = $roles->where('role', $key)->pluck('id')->unique()->count();
            $who = $takers->filter(fn ($e) => $rolesOf($e)->contains($key));
            $slots = $who->sum(fn ($e) => $open($e));
            $table[] = [
                'role' => $label,
                'when' => in_array($key, Team::PANEL_EXPERT_ROLES, true) ? 'needed before a panel' : 'may come later',
                'with' => $with, 'need' => $teams - $with, 'takers' => $who->count(), 'open' => $slots,
                'now' => $verdict($teams - $with, $slots), 'after' => $verdict($teams - $with + $later, $slots),
            ];
        }
        $need = array_sum(array_column($table, 'need'));
        // An expert who takes two roles still has one set of open slots, so the total counts each expert once.
        $slots = $takers->sum(fn ($e) => $open($e));

        $seats = DefensePanel::query()->join('defense_schedule', 'defense_schedule.defense_id', '=', 'defense_panels.defense_id')
            ->whereIn('defense_schedule.team_id', $teamIds)
            ->where(fn ($q) => $q->whereNull('defense_schedule.outcome')->orWhere('defense_schedule.outcome', '!=', DefenseOutcome::CANCELLED))
            ->pluck('defense_panels.expert_id')->countBy();
        $busiest = $seats->sortDesc()->keys()->first();

        return [
            'program' => $ctx['program'] !== '' ? $ctx['program'] : $ctx['programOptions'][''],
            'teams' => $teams,
            'loose' => $loose,
            'later' => $later,
            'roles' => $table,
            'all' => [
                'with' => array_sum(array_column($table, 'with')), 'need' => $need, 'takers' => $takers->count(), 'open' => $slots,
                'now' => $verdict($need, $slots), 'after' => $verdict($need + $later * count(self::ROLES), $slots),
            ],
            'waiting' => $rows->sum('asks'),
            // Waiting on an expert who is already full: the "· full" cells of the table.
            'stuck' => $rows->filter(fn ($r) => $r['full'] && $r['asks'])->map(fn ($r) => [$r['name'], $r['asks']])->values()->all(),
            'panel' => [
                'seats' => $seats->sum(),
                'panelists' => $seats->count(),
                'each' => $seats->count() ? (int) round($seats->sum() / $seats->count()) : 0,
                'busiest' => $busiest ? [(string) CapstoneExpert::withTrashed()->find($busiest)?->name, $seats[$busiest]] : null,
            ],
        ];
    }

    /** The box as lines of text, for the PDF and the Excel file. */
    private function enoughNotes(array $en): array
    {
        $after = $en['later'] ? 'after '.$this->count($en['later'], 'new team') : 'after new teams (none coming)';
        $stuck = array_sum(array_column($en['stuck'], 1));

        return [
            'Enough experts? '.$en['program'].' · '.$this->count($en['teams'], 'team').' · '.$en['loose'].' not in a team yet · '
                .$en['all']['need'].' roles still to fill · '.$en['all']['open'].' open slots · now '.$en['all']['now'].' · '.$after.' '.$en['all']['after'],
            ...array_map(fn ($r) => $r['role'].' ('.$r['when'].'): '.$r['with'].' teams with one, '.$r['need'].' still need one, '
                .$this->count($r['takers'], 'expert').' who take it with '.$r['open'].' open slots · now '.$r['now'].' · '.$after.' '.$r['after'], $en['roles']),
            'Requests waiting: '.$en['waiting'].($stuck ? ' · '.$stuck.' with experts who are already full ('.implode(', ', array_map(fn ($s) => $s[0].' '.$s[1], $en['stuck'])).')' : ''),
            'Panel: '.$en['panel']['seats'].' seats · '.$this->count($en['panel']['panelists'], 'expert').' on panels · about '.$en['panel']['each'].' each'
                .($en['panel']['busiest'] ? ' · busiest: '.$en['panel']['busiest'][0].', '.$en['panel']['busiest'][1] : '').' · no limit on panel seats',
        ];
    }

    /**
     * The people on the Expert Management page, one row each, with every
     * column that page shows or hides. Same people as that page: approved
     * experts who are not the system admin, deactivated ones included and
     * marked as such.
     */
    private function expertList(): array
    {
        $rows = CapstoneExpert::withTrashed()
            ->where('approved_status', true)
            ->where('is_admin', false)
            ->with('expertise', 'roles')
            ->withCount(['teamAssignments' => fn ($q) => $q->where('status', 'approved')])
            ->get()
            ->sortBy(fn (CapstoneExpert $e) => mb_strtolower((string) $e->name))
            ->map(function (CapstoneExpert $e) {
                $takesTeams = $e->roles->pluck('role_name')->intersect(CapstoneExpert::STUDENT_FACING_ROLES)->isNotEmpty();
                $slotsSet = $e->max_teams !== null || filled($e->program_slots);
                $teams = (int) ($e->team_assignments_count ?? 0);
                $full = $takesTeams && $slotsSet && $teams >= (int) $e->max_teams;
                [$status, $tone] = match (true) {
                    (bool) $e->deactivated_at => ['Deactivated', 'bad'],
                    $full => ['Full', 'warn'],
                    $e->is_online => ['Active', 'ok'],
                    default => ['Offline', 'none'],
                };
                $caps = collect($takesTeams && $slotsSet ? $e->parsedCapacity() : []);
                // Which programs the slots are for, beside the slots themselves.
                $programs = $caps->count() === 1 && $caps[0][0] === 'ALL' ? 'any program' : $caps->map(fn ($row) => $row[0].': max '.$row[1])->implode(', ');

                return ['id' => null, 'cells' => [
                    'expert' => ['b', (string) $e->name],
                    'email' => $e->email ?: self::DASH,
                    'roles' => $e->roles->isEmpty() ? self::DASH : $e->roles->pluck('role_name')->map(fn ($r) => Role::labelFor($r))->implode(', '),
                    'expertise' => $e->allExpertise()->isEmpty() ? self::DASH : $e->allExpertise()->implode(', '),
                    'accepts' => ! $takesTeams ? self::DASH : (! $slotsSet ? 'Not set' : ['slots', $teams, (int) $e->max_teams, $programs]),
                    'sections' => $e->sections ? implode(', ', $e->sections) : self::DASH,
                    'degree' => trim(($e->highest_degree ?? '').' '.($e->degree_title ? '('.$e->degree_title.')' : '')) ?: self::DASH,
                    'status' => ['chip', $status, $tone],
                ]];
            })
            ->values();

        return [
            'columns' => $this->cols(self::EXPERT_LIST_COLUMNS, []),
            'rows' => $rows->all(),
            'total' => null,
            'summary' => $this->count($rows->count(), 'expert').' on the Expert Management page.',
            'empty' => 'No experts to show yet.',
        ];
    }

    private function change(int $now, int $last): array|string
    {
        if ($last === 0) {
            return $now === 0 ? self::DASH : ['chip', 'New', 'info'];
        }

        $d = $now - $last;

        return $d === 0 ? 'Same' : ['num', ($d > 0 ? '↑ ' : '↓ ').abs($d), $d];
    }

    // ----------------------------------------------------------------- helpers

    /** @return Collection<int, TeamExpertAssignment> */
    private function approvedAssignments(array $teamIds, bool $fresh = false): Collection
    {
        $key = collect($teamIds)->map(fn ($id) => (int) $id)->unique()->sort()->implode(',');

        if (! $fresh && isset($this->assignmentCache[$key])) {
            return $this->assignmentCache[$key];
        }

        $found = TeamExpertAssignment::whereIn('team_id', $teamIds)->where('status', 'approved')
            ->with(['expert', 'expertRole.role', 'role', 'team'])->get();

        return $fresh ? $found : $this->assignmentCache[$key] = $found;
    }

    private function roleKey(TeamExpertAssignment $a): ?string
    {
        // Exact names: the CaPSA's own role is "capstone_adviser", which is not an adviser.
        $name = strtolower(trim((string) $a->engaged_role_name));

        return array_key_exists($name, self::ROLES) ? $name : null;
    }

    /** @return array<string, string> role key => expert name */
    private function rolesOf(Collection $assignments): array
    {
        $out = [];
        foreach ($assignments as $a) {
            if (($key = $this->roleKey($a)) && $a->expert) {
                $out[$key] = isset($out[$key]) ? $out[$key].', '.$a->expert->name : $a->expert->name;
            }
        }

        return $out;
    }

    /** @return string[] */
    private function memberNames(array $ctx, Team $team, bool $markLeader = false): array
    {
        return $ctx['records']->get($team->team_id, collect())
            ->map(fn ($sy) => $sy->student)->filter()->unique('student_id')
            ->sortBy(fn ($s) => [(int) $s->student_id !== (int) $team->leader_id, mb_strtolower($s->l_name.' '.$s->f_name)])
            ->map(fn ($s) => trim($s->f_name.' '.$s->l_name).($markLeader && (int) $s->student_id === (int) $team->leader_id ? ' (leader)' : ''))
            ->values()->all();
    }

    private function sectionName(array $ctx, Team $team): string
    {
        $label = $ctx['teamSections'][$team->team_id] ?? '';

        return $label === SectionScope::UNASSIGNED ? 'Unassigned' : $label;
    }

    /** One row per section, Unassigned last, then an "All sections" total. */
    private function bySection(array $ctx, Collection $teams, callable $line, array $labels, array $right): array
    {
        $groups = $teams->groupBy(fn ($t) => $ctx['teamSections'][$t->team_id]);
        $rows = $groups->map(fn ($g, $label) => ['id' => null, 'cells' => $line($g, ['b', $label === SectionScope::UNASSIGNED ? 'Unassigned' : $label])])->values()->all();

        return [
            'columns' => $this->cols($labels, $right),
            'rows' => $rows,
            'total' => $rows ? $this->bold($line($teams, 'All sections')) : null,
            'summary' => $this->count(count($rows), 'section').' plus the total',
            'empty' => 'No teams match these filters.',
        ];
    }

    private function bold(array $cells): array
    {
        return array_map(fn ($c) => match (true) {
            is_array($c) && in_array($c[0], ['num', 'b'], true) => ['b', (string) $c[1]],
            is_array($c) && $c[0] === 'bar' => ['b', $c[1].' of '.$c[2].' ('.($c[2] ? (int) round($c[1] / $c[2] * 100) : 0).'%)'],
            is_string($c) => ['b', $c],
            default => $c,
        }, $cells);
    }

    /** @return array<string, array{label: string, align: string}> */
    private function cols(array $labels, array $right): array
    {
        return collect($labels)->map(fn ($label, $key) => ['label' => $label, 'align' => in_array($key, $right, true) ? 'right' : 'left'])->all();
    }

    private function tone(string $status): string
    {
        return match ($status) {
            'On track', 'Paid', 'Verified' => 'ok',
            'Has overdue items', 'Not paid', 'Sent back' => 'bad',
            'Receipt to check', 'Waiting' => 'warn',
            default => 'none',
        };
    }

    private function count(int $n, string $what): string
    {
        return $n.' '.Str::plural($what, $n);
    }

    private function money(float $amount): string
    {
        return '₱'.str_replace('.00', '', number_format($amount, 2));
    }

    // ------------------------------------------------------- cells as text

    /** A cell as plain text, for the PDF, the spreadsheet and searching. */
    public static function text(mixed $cell, string $lineBreak = "\n"): string
    {
        if (! is_array($cell)) {
            return (string) $cell;
        }

        return match ($cell[0]) {
            'bar' => $cell[1].' of '.$cell[2],
            'lines' => implode($lineBreak, $cell[1]),
            'slots' => ($cell[2] > 0 ? $cell[1].' of '.$cell[2] : $cell[1].' (no limit)').(($cell[3] ?? '') !== '' ? ' · '.$cell[3] : ''),
            default => (string) $cell[1],
        };
    }

    /** What a column sorts by: numbers as numbers, everything else as lower-case text. */
    public static function sortValue(mixed $cell): string|int|float
    {
        if (! is_array($cell)) {
            $s = (string) $cell;

            return $s === self::DASH ? '' : mb_strtolower($s);
        }

        return match ($cell[0]) {
            'num' => $cell[2] ?? (is_numeric($cell[1]) ? $cell[1] + 0 : mb_strtolower((string) $cell[1])),
            'bar' => $cell[2] > 0 ? round($cell[1] / $cell[2], 4) : -1,
            'slots' => $cell[1],
            'tone' => $cell[3] ?? mb_strtolower((string) $cell[1]),
            'lines' => mb_strtolower(implode(' ', $cell[1])),
            default => mb_strtolower((string) $cell[1]),
        };
    }
}
