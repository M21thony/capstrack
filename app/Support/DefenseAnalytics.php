<?php

namespace App\Support;

use App\Models\ArchivedDocument;
use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\Term;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How many teams get through each defense, and what kind of project they are.
 *
 * The Analytics page used to be a mock-up: every number on it was typed into
 * the view. This is the real thing, worked out from defense_schedule (who
 * defended what, and how it ended), the pre-proposals (what kind of project a
 * team is building - the tags), and the team's section (whose it is).
 *
 * A few decisions worth knowing before reading the code:
 *
 * - One team is one row per stage, however many attempts it took. A team that
 *   was sent back for a redefense and then passed has passed; it is not a
 *   pass and a fail. This is the same rule DefenseProgress uses.
 *
 * - A team is counted in the school year its decisive attempt happened in -
 *   the one that passed it, or else the latest. Counted that way, last year's
 *   numbers stay put when a team from last year defends the next stage this
 *   year; counted by the team's latest activity they would drift.
 *
 * - The pass rate is passed over decided. A team still waiting for its first
 *   result, or not yet scheduled, has not had a chance to pass or fail, and
 *   counting it as a failure would make an early-semester page read as a
 *   disaster.
 *
 * - A team carries up to five tags, so it counts under each. The tag rows
 *   therefore add up to more than the number of teams, and the page says so.
 */
class DefenseAnalytics
{
    /** What a team with no usable project tag is filed under. */
    public const UNTAGGED = 'Untagged';

    /** The school-year filter value that means every year on record. */
    public const ALL_YEARS = 'all';

    /**
     * Where a team stands at one stage. The order is the order the stacked
     * bars are drawn in: good news first, then the ones still open, then the
     * ones that are not going to happen.
     */
    public const STATUSES = ['passed', 'revision', 'redefense', 'awaiting', 'missed', 'not_scheduled'];

    /** Stage => the short name a narrow column can hold. */
    public const SHORT = [
        'Pre-proposal Defense' => 'Pre-proposal',
        'Title Defense' => 'Title',
        'Pre Oral Defense' => 'Pre-oral',
        'Final Oral Defense' => 'Final oral',
    ];

    /**
     * The tags that get a colour of their own, in the order the palette is
     * validated for. Colour follows the tag, never its rank, so filtering the
     * page does not repaint the survivors. Every other tag folds into the
     * grey "Other" rather than being handed a ninth hue nobody can tell apart.
     */
    public const TAG_COLORS = [
        'Web-based' => '#2a78d6',
        'Mobile App' => '#eb6834',
        'IoT' => '#1baf7a',
        'AI / Machine Learning' => '#eda100',
        'Management System' => '#e87ba4',
        'Data Analytics' => '#008300',
        'Decision Support System' => '#4a3aa7',
    ];

    public const OTHER_COLOR = '#898781';

    /**
     * Ways the archive's free-text tags are written, mapped to the project
     * tag they mean. Keys are lower-cased with everything but letters and
     * digits removed.
     */
    private const ARCHIVE_ALIASES = [
        'webbased' => 'Web-based', 'web' => 'Web-based', 'webapp' => 'Web-based', 'webapplication' => 'Web-based',
        'mobileapp' => 'Mobile App', 'mobile' => 'Mobile App', 'mobileapplication' => 'Mobile App', 'android' => 'Mobile App',
        'iot' => 'IoT', 'internetofthings' => 'IoT',
        'aimachinelearning' => 'AI / Machine Learning', 'ai' => 'AI / Machine Learning', 'aiml' => 'AI / Machine Learning',
        'ml' => 'AI / Machine Learning', 'machinelearning' => 'AI / Machine Learning', 'artificialintelligence' => 'AI / Machine Learning',
        'managementsystem' => 'Management System', 'managementsystems' => 'Management System', 'mgmtsystems' => 'Management System',
        'dataanalytics' => 'Data Analytics',
        'decisionsupportsystem' => 'Decision Support System', 'dss' => 'Decision Support System',
    ];

    public function __construct(private SectionScope $scope, private DefenseProgress $progress) {}

    /**
     * Everything the Analytics page draws.
     *
     * @param  array{school_year?: ?string, program?: ?string, section?: ?string, tag?: ?string}  $filters
     * @return array<string, mixed>
     */
    public function build(CapstoneExpert $viewer, array $filters = []): array
    {
        $current = Term::currentSchoolYear();
        $rows = $this->teamRows($viewer, $current);

        $filters = $this->normalise($filters, $current);
        $year = $filters['school_year'];

        // Program and section narrow the teams; the year narrows which of a
        // team's stages count, so it is applied per stage further down.
        $scoped = $rows->filter(
            fn (array $t) => (! $filters['program'] || $t['program'] === $filters['program'])
                && (! $filters['section'] || $t['section'] === $filters['section'])
        );

        $inYear = $scoped->filter(fn (array $t) => $this->hasCountedStage($t, $year, $current));

        // The chips count teams before the tag filter is applied, or picking
        // a tag would make every other chip read zero.
        $tagChips = $this->tagChips($inYear);

        $teams = $inYear->filter(fn (array $t) => ! $filters['tag'] || in_array($filters['tag'], $t['tags'] ?: [self::UNTAGGED], true));

        $stages = $this->stageTotals($teams, $year, $current);
        $byTag = $this->byTag($teams, $year, $current);

        // The year-by-year view ignores the year filter - it is the year
        // filter's own context - but keeps every other one.
        $yearScope = $scoped->filter(fn (array $t) => ! $filters['tag'] || in_array($filters['tag'], $t['tags'] ?: [self::UNTAGGED], true));
        $byYear = $this->byYear($yearScope);

        return [
            'filters' => $filters,
            'currentYear' => $current,
            'options' => $this->options($rows, $current),
            'tagChips' => $tagChips,
            'teamsInScope' => $inYear->count(),
            'kpis' => $this->kpis($teams, $stages),
            'stages' => $stages,
            'byTag' => $byTag,
            'byYear' => $byYear,
            'teams' => $this->teamList($teams, $year, $current),
            'tagColors' => self::TAG_COLORS,
            'archive' => $this->archive(),
        ];
    }

    /** @return array{school_year: string, program: ?string, section: ?string, tag: ?string} */
    private function normalise(array $filters, string $current): array
    {
        $year = trim((string) ($filters['school_year'] ?? ''));

        return [
            'school_year' => $year === '' ? $current : $year,
            'program' => ($filters['program'] ?? null) ?: null,
            'section' => ($filters['section'] ?? null) ?: null,
            'tag' => ($filters['tag'] ?? null) ?: null,
        ];
    }

    /**
     * Every team the viewer may see, with where it stands at each stage.
     *
     * Admin sees everyone. A CaPSA/TSA sees their own sections, and - as on every
     * other CaPSA/TSA page - a team whose section cannot be worked out is shown
     * to all of them rather than to nobody, because nobody could then fix it.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function teamRows(CapstoneExpert $viewer, string $current): Collection
    {
        $teams = Team::query()
            ->where(fn ($q) => $q->where('is_confirmed', true)->orWhereHas('defenseSchedules'))
            ->with(['defenseSchedules', 'preProposals', 'selectedPreProposal'])
            ->orderBy('team_name')
            ->get();

        // Every term's enrolment, not just the running one: a team from last
        // year has no rows in this term's scope, and without its members it
        // would have no section - so it would look unassigned to everyone.
        $records = StudentSchoolYear::withoutGlobalScopes()
            ->whereIn('team_id', $teams->pluck('team_id'))
            ->with('student')
            ->get()
            ->groupBy('team_id');

        $capsaSections = $viewer->is_admin ? [] : ($viewer->sections ?? []);

        return $teams->map(function (Team $team) use ($records, $capsaSections, $current) {
            $label = $this->scope->sectionFor($team, $records->get($team->team_id, collect()));

            if ($capsaSections !== [] && ! $this->scope->covers($capsaSections, $label)) {
                return null;
            }

            $section = $label !== '' ? $label : SectionScope::UNASSIGNED;
            $program = preg_match('/^([A-Z]+)-/', $label, $m) && isset(Student::PROGRAM_COURSES[$m[1]]) ? $m[1] : 'Unassigned';

            return [
                'id' => (int) $team->team_id,
                'name' => (string) $team->team_name,
                'title' => $team->project_title,
                'section' => $section,
                'program' => $program,
                'tags' => $this->tagsFor($team),
                'stages' => $this->closeOut(collect(DefenseProgress::STAGES)
                    ->mapWithKeys(fn (string $stage) => [$stage => $this->stageRow($team, $stage, $current)])
                    ->all()),
            ];
        })->filter()->values();
    }

    /**
     * A team that did not pass a stage is not going to reach the ones after
     * it, so those are not "not scheduled yet" - they are not going to
     * happen. Left in, every team that dropped out last year would sit in this
     * year's numbers as work still to come, and the page would show a wall of
     * teams nobody is waiting on.
     *
     * This is derived like everything else here: if the CaPSA/TSA extends the
     * window and the team is back in play, the next time the page loads the
     * stage is no longer "missed" and its later stages count again.
     *
     * @param  array<string, array<string, mixed>>  $stages
     * @return array<string, array<string, mixed>>
     */
    private function closeOut(array $stages): array
    {
        if (! collect($stages)->contains(fn (array $row) => $row['status'] === 'missed')) {
            return $stages;
        }

        foreach ($stages as &$row) {
            if ($row['status'] === 'not_scheduled') {
                $row['dead'] = true;
            }
        }

        return $stages;
    }

    /**
     * What kind of project a team is building.
     *
     * The tags live on the pre-proposals: the team picks them when it writes
     * one, from the fixed list in ProjectTags. The pre-proposal the team is
     * going with speaks for it; before it has chosen, an approved one does,
     * then any it has. The team's own recruitment tags are a last resort -
     * they describe who it is looking for as much as what it is making - and
     * only count where they are on the list.
     *
     * @return array<int, string>
     */
    private function tagsFor(Team $team): array
    {
        $candidates = collect([$team->selectedPreProposal])
            ->merge($team->preProposals->where('approved_status', true))
            ->merge($team->preProposals)
            ->filter();

        foreach ($candidates as $proposal) {
            $tags = $this->onTheList((array) $proposal->tags);

            if ($tags !== []) {
                return $tags;
            }
        }

        return $this->onTheList((array) $team->tags);
    }

    /**
     * Keeps what is on the ProjectTags list, in the list's order, so the same
     * project reads the same wherever it shows up.
     *
     * @param  array<int, mixed>  $tags
     * @return array<int, string>
     */
    private function onTheList(array $tags): array
    {
        $wanted = array_map(fn ($t) => is_string($t) ? trim($t) : '', $tags);

        return array_slice(array_values(array_intersect(ProjectTags::OPTIONS, $wanted)), 0, ProjectTags::MAX);
    }

    /**
     * One team at one stage: how it stands, how many tries it took, and when.
     *
     * @return array{status: string, attempts: int, year: ?string, first: ?string}
     */
    private function stageRow(Team $team, string $stage, string $current): array
    {
        // A defense cancelled on the day was never heard, so it is not a try.
        $attempts = $team->defenseSchedules->where('defense_type', $stage)->where('outcome', '!==', DefenseOutcome::CANCELLED)->sortBy('attempt')->values();

        if ($attempts->isEmpty()) {
            return ['status' => 'not_scheduled', 'attempts' => 0, 'year' => null, 'first' => null];
        }

        // How the first try went, whatever came after: the honest measure of
        // whether a stage is hard, separate from whether teams get there in
        // the end.
        $firstOutcome = $attempts->first()->outcome;
        $first = $firstOutcome === null ? null : (DefenseOutcome::isPass($firstOutcome) ? 'passed' : 'failed');

        $passing = $attempts->first(fn (DefenseSchedule $s) => DefenseOutcome::isPass($s->outcome));
        $decisive = $passing ?? $attempts->last();
        $year = $this->schoolYearOf($decisive->schedule_date ?? $decisive->outcome_recorded_at) ?? $current;

        if ($passing) {
            $status = $passing->outcome === DefenseOutcome::PASSED_WITH_REVISION ? 'revision' : 'passed';
        } else {
            // A past year is closed: whatever was not passed by then was not
            // passed. Only the running year still has a clock, and that is the
            // stage's defense window (see DefenseProgress).
            $closed = $year !== $current || $this->progress->deadlineHasPassed($stage);

            $status = match (true) {
                $closed => 'missed',
                DefenseOutcome::requiresRedefense($decisive->outcome) => 'redefense',
                default => 'awaiting',
            };
        }

        return ['status' => $status, 'attempts' => $attempts->count(), 'year' => $year, 'first' => $first];
    }

    /**
     * The school year a date falls in. August starts the new one, as it does
     * everywhere else in the app (see Term::currentSchoolYear()).
     */
    private function schoolYearOf(?Carbon $date): ?string
    {
        if (! $date) {
            return null;
        }

        $start = $date->month >= 8 ? $date->year : $date->year - 1;

        return $start.'-'.($start + 1);
    }

    /**
     * Whether one team-at-a-stage belongs in the year being looked at.
     *
     * A stage nobody has scheduled has no year of its own, so it counts only
     * for the running one - it is work still to come, not work from last year
     * nobody did.
     *
     * @param  array{status: string, year: ?string}  $row
     */
    private function counts(array $row, string $year, string $current): bool
    {
        if (! empty($row['dead'])) {
            return false;
        }

        if ($year === self::ALL_YEARS) {
            return true;
        }

        return $row['status'] === 'not_scheduled' ? $year === $current : $row['year'] === $year;
    }

    private function hasCountedStage(array $team, string $year, string $current): bool
    {
        foreach ($team['stages'] as $row) {
            if ($this->counts($row, $year, $current)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function blank(): array
    {
        return [
            'teams' => 0, 'passed' => 0, 'revision' => 0, 'redefense' => 0, 'awaiting' => 0, 'missed' => 0, 'not_scheduled' => 0,
            'first_passed' => 0, 'first_failed' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $bucket
     * @param  array{status: string, first: ?string}  $row
     */
    private function add(array &$bucket, array $row): void
    {
        $bucket['teams']++;
        $bucket[$row['status']]++;

        if ($row['first'] === 'passed') {
            $bucket['first_passed']++;
        } elseif ($row['first'] === 'failed') {
            $bucket['first_failed']++;
        }
    }

    /**
     * Adds the derived figures. Kept out of add() because they are only
     * meaningful once every team has been counted.
     *
     * @param  array<string, mixed>  $bucket
     * @return array<string, mixed>
     */
    private function finish(array $bucket): array
    {
        $bucket['passed_total'] = $bucket['passed'] + $bucket['revision'];
        $bucket['decided'] = $bucket['passed_total'] + $bucket['redefense'] + $bucket['missed'];
        $bucket['rate'] = $bucket['decided'] > 0 ? round($bucket['passed_total'] / $bucket['decided'] * 100) : null;

        $firstTries = $bucket['first_passed'] + $bucket['first_failed'];
        $bucket['first_try_rate'] = $firstTries > 0 ? round($bucket['first_passed'] / $firstTries * 100) : null;

        return $bucket;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $teams
     * @return array<string, array<string, mixed>>
     */
    private function stageTotals(Collection $teams, string $year, string $current): array
    {
        $out = [];

        foreach (DefenseProgress::STAGES as $stage) {
            $bucket = $this->blank();

            foreach ($teams as $team) {
                if ($this->counts($team['stages'][$stage], $year, $current)) {
                    $this->add($bucket, $team['stages'][$stage]);
                }
            }

            $out[$stage] = $this->finish($bucket) + ['short' => self::SHORT[$stage]];
        }

        return $out;
    }

    /**
     * Each tag's results at each stage, biggest tag first.
     *
     * @param  Collection<int, array<string, mixed>>  $teams
     * @return array<int, array<string, mixed>>
     */
    private function byTag(Collection $teams, string $year, string $current): array
    {
        $tags = [];

        foreach ($teams as $team) {
            foreach ($team['tags'] ?: [self::UNTAGGED] as $tag) {
                $tags[$tag] ??= ['teams' => 0, 'stages' => array_fill_keys(DefenseProgress::STAGES, $this->blank())];
                $tags[$tag]['teams']++;

                foreach (DefenseProgress::STAGES as $stage) {
                    if ($this->counts($team['stages'][$stage], $year, $current)) {
                        $this->add($tags[$tag]['stages'][$stage], $team['stages'][$stage]);
                    }
                }
            }
        }

        $rows = [];
        foreach ($tags as $tag => $data) {
            $stages = array_map(fn (array $b) => $this->finish($b), $data['stages']);
            $passed = array_sum(array_column($stages, 'passed_total'));
            $decided = array_sum(array_column($stages, 'decided'));

            $rows[] = [
                'tag' => $tag,
                'color' => self::TAG_COLORS[$tag] ?? self::OTHER_COLOR,
                'teams' => $data['teams'],
                'stages' => $stages,
                'passed' => $passed,
                'decided' => $decided,
                'rate' => $decided > 0 ? round($passed / $decided * 100) : null,
            ];
        }

        // Untagged goes last whatever its size: it is a gap in the data, not
        // a kind of project.
        usort($rows, fn ($a, $b) => [$a['tag'] === self::UNTAGGED, $b['teams'], $a['tag']] <=> [$b['tag'] === self::UNTAGGED, $a['teams'], $b['tag']]);

        return $rows;
    }

    /**
     * Pass results for each school year on record, oldest first.
     *
     * @param  Collection<int, array<string, mixed>>  $teams
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function byYear(Collection $teams): array
    {
        $years = [];

        foreach ($teams as $team) {
            foreach ($team['stages'] as $stage => $row) {
                if ($row['status'] === 'not_scheduled' || ! $row['year']) {
                    continue;
                }

                $years[$row['year']][$stage] ??= $this->blank();
                $this->add($years[$row['year']][$stage], $row);
            }
        }

        ksort($years);

        return array_map(function (array $stages) {
            $out = [];
            foreach (DefenseProgress::STAGES as $stage) {
                $out[$stage] = $this->finish($stages[$stage] ?? $this->blank()) + ['short' => self::SHORT[$stage]];
            }

            return $out;
        }, $years);
    }

    /**
     * The tag chips above the page: how many teams carry each tag, before the
     * tag filter narrows them.
     *
     * @param  Collection<int, array<string, mixed>>  $teams
     * @return array<int, array{tag: string, teams: int, color: string}>
     */
    private function tagChips(Collection $teams): array
    {
        $counts = [];

        foreach ($teams as $team) {
            foreach ($team['tags'] ?: [self::UNTAGGED] as $tag) {
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        $chips = [];
        foreach ($counts as $tag => $n) {
            $chips[] = ['tag' => $tag, 'teams' => $n, 'color' => self::TAG_COLORS[$tag] ?? self::OTHER_COLOR];
        }

        usort($chips, fn ($a, $b) => [$a['tag'] === self::UNTAGGED, $b['teams'], $a['tag']] <=> [$b['tag'] === self::UNTAGGED, $a['teams'], $b['tag']]);

        return $chips;
    }

    /**
     * The four headline numbers.
     *
     * @param  Collection<int, array<string, mixed>>  $teams
     * @param  array<string, array<string, mixed>>  $stages
     * @return array<string, mixed>
     */
    private function kpis(Collection $teams, array $stages): array
    {
        $passed = array_sum(array_column($stages, 'passed_total'));
        $decided = array_sum(array_column($stages, 'decided'));
        $title = $stages['Title Defense'];

        return [
            'teams' => $teams->count(),
            'tagged' => $teams->filter(fn (array $t) => $t['tags'] !== [])->count(),
            'title_passed' => $title['passed_total'],
            'title_teams' => $title['teams'],
            'title_rate' => $title['rate'],
            'passed' => $passed,
            'decided' => $decided,
            'rate' => $decided > 0 ? round($passed / $decided * 100) : null,
            'awaiting' => array_sum(array_column($stages, 'awaiting')),
            'redefense' => array_sum(array_column($stages, 'redefense')),
            'missed' => array_sum(array_column($stages, 'missed')),
        ];
    }

    /**
     * The choices each filter offers - only ones that lead somewhere.
     *
     * @param  Collection<int, array<string, mixed>>  $all  Every team the viewer may see.
     * @return array<string, array<int, string>>
     */
    private function options(Collection $all, string $current): array
    {
        $years = $all
            ->flatMap(fn (array $t) => collect($t['stages'])->pluck('year'))
            ->filter()
            ->push($current)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $programs = $all->pluck('program')->unique()->sort()->values()->all();
        $sections = $all->pluck('section')->unique()->sort()->values()->all();

        return compact('years', 'programs', 'sections');
    }

    /**
     * Team-by-team results, for the table at the bottom and the CSV.
     *
     * A stage outside the year being looked at is left null: it is not this
     * year's result, and showing it would put last year's pass beside this
     * year's team without saying so.
     *
     * @param  Collection<int, array<string, mixed>>  $teams
     * @return array<int, array<string, mixed>>
     */
    private function teamList(Collection $teams, string $year, string $current): array
    {
        return $teams->map(function (array $t) use ($year, $current) {
            $stages = [];
            foreach (DefenseProgress::STAGES as $stage) {
                $row = $t['stages'][$stage];
                // A stage that is not going to happen is said so, and is not the
                // same as one that just falls outside the year.
                $stages[$stage] = ! empty($row['dead'])
                    ? 'closed'
                    : ($this->counts($row, $year, $current) ? $row['status'] : null);
            }

            return [
                'id' => $t['id'],
                'name' => $t['name'],
                'title' => $t['title'],
                'section' => $t['section'],
                'tags' => $t['tags'],
                'stages' => $stages,
                'attempts' => array_sum(array_column($t['stages'], 'attempts')),
            ];
        })->values()->all();
    }

    /**
     * Archived capstones, per school year, by kind of project.
     *
     * The archive is the department's history rather than this semester's
     * teams, so it is not narrowed by the filters above. Its tags are free
     * text an admin typed, so the ones that mean a ProjectTags entry are
     * folded into it and the rest are "Other".
     *
     * @return array{total: int, years: array<string, array<string, mixed>>, tags: array<string, int>}
     */
    private function archive(): array
    {
        $documents = ArchivedDocument::query()->with('tags')->whereNotNull('academic_year')->get();

        $years = [];
        $tagTotals = [];

        foreach ($documents as $document) {
            $year = (string) $document->academic_year;
            $years[$year] ??= ['total' => 0, 'tags' => []];
            $years[$year]['total']++;

            $kinds = $document->tags
                ->map(fn ($tag) => self::ARCHIVE_ALIASES[preg_replace('/[^a-z0-9]/', '', strtolower((string) $tag->tag_name))] ?? 'Other')
                ->unique();

            // A document with no tag at all is still a capstone in the year.
            foreach ($kinds->isEmpty() ? ['Other'] : $kinds as $kind) {
                $years[$year]['tags'][$kind] = ($years[$year]['tags'][$kind] ?? 0) + 1;
                $tagTotals[$kind] = ($tagTotals[$kind] ?? 0) + 1;
            }
        }

        ksort($years);
        arsort($tagTotals);

        return ['total' => $documents->count(), 'years' => $years, 'tags' => $tagTotals];
    }

    /** Label for a status, as it is said everywhere on the page. */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'passed' => 'Passed',
            'revision' => 'Passed with revision',
            'redefense' => 'Needs a redefense',
            'awaiting' => 'Waiting for the result',
            'missed' => 'Did not pass',
            'closed' => 'Not reached',
            default => 'Not scheduled',
        };
    }
}
