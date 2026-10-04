<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\DefenseCancellation;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\ExpensePost;
use App\Models\ExpertAwayDate;
use App\Models\FinancialRecord;
use App\Models\GradingRubric;
use App\Models\Role;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Where every team in a CaPSA's sections stands for one defense stage.
 *
 * Panel Assignment, Defense Scheduling and Generate schedule all ask the same
 * question - can this team be scheduled, and if not, why not - so the answer
 * is worked out here once. Asked in three places, it once drifted: saving a
 * window scheduled teams that the review screen would have refused.
 *
 * The order of the reasons is the order a team clears them in: its three
 * experts, the stage before this one, the checklist requirements, a panel,
 * and - for every stage but the pre-proposal - each member's fee.
 */
class DefenseBoard
{
    public const STAGES = DefenseProgress::STAGES;

    /** Pre-proposal Defense has no fee; the other three do. */
    public const PAID_STAGES = ['Title Defense', 'Pre Oral Defense', 'Final Oral Defense'];

    /**
     * Shown, but not in the way of a date: a team still owing requirements
     * can be scheduled, and the panel follows them up.
     */
    public const WARNING_KEYS = ['req'];

    public const VENUES = ['NAS Lab', 'Mac Lab', 'ERP Lab', 'Open Lab', 'CS Lab', 'IT Lab 1', 'IT Lab 2', 'Conference Room'];

    /** A stage with no window yet is timed as two-hour defenses. */
    public const DEFAULT_MINUTES = 120;

    /** A custom length: at least half an hour, at most a whole morning (8-12). */
    public const MIN_MINUTES = 30;

    public const MAX_MINUTES = 240;

    /**
     * Start times for defenses of this length: back to back from 8:00 until
     * lunch at 12:00, then again from 1:00 until 5:00. Nothing runs into
     * lunch or past five.
     *
     * @return array<int, string>
     */
    public static function slots(int $minutes): array
    {
        $out = [];

        foreach ([[8 * 60, 12 * 60], [13 * 60, 17 * 60]] as [$from, $until]) {
            for ($t = $from; $t + $minutes <= $until; $t += $minutes) {
                $out[] = sprintf('%02d:%02d', intdiv($t, 60), $t % 60);
            }
        }

        return $out;
    }

    /**
     * The blockers that keep a team from a date - every one but the warnings.
     *
     * @param  array<int, array{key: string, text: string}>  $blockers
     * @return array<int, array{key: string, text: string}>
     */
    public static function hardBlockers(array $blockers): array
    {
        return array_values(array_filter($blockers, fn (array $b) => ! in_array($b['key'], self::WARNING_KEYS, true)));
    }

    /** Whether this stage needs the CaPA's go before it is held: all but the pre-proposal. */
    public static function needsCapaGo(string $stage): bool
    {
        return in_array($stage, self::PAID_STAGES, true);
    }

    /** How long this stage's defenses run, from its window. */
    public static function minutesFor(string $stage): int
    {
        return DefenseWindow::where('defense_type', $stage)->value('duration_minutes') ?? self::DEFAULT_MINUTES;
    }

    // Team tags are picked from a fixed list, but an expert's expertise is
    // free text, so each tag also matches the other ways people write it.
    private const TAG_SYNONYMS = [
        'artificial intelligence' => ['ai'],
        'ai' => ['artificial intelligence'],
        'machine learning' => ['ml', 'ai'],
        'web development' => ['web dev', 'web'],
        'mobile app' => ['mobile development', 'mobile', 'android', 'ios'],
        'data analytics' => ['data analysis', 'data science'],
        'cybersecurity' => ['security', 'infosec'],
        'database systems' => ['databases', 'database'],
        'ui/ux design' => ['ui/ux', 'ui', 'ux', 'user experience', 'user interface'],
        'network systems' => ['networking', 'networks'],
    ];

    public function __construct(private SectionScope $scope, private DefenseProgress $progress) {}

    /**
     * The stage a page shows: the one asked for, else the earliest stage any
     * of this CaPSA's teams is still working through - where the work is.
     */
    public function openingStage(CapstoneExpert $capsa, ?string $asked): string
    {
        if (in_array($asked, self::STAGES, true)) {
            return $asked;
        }

        $earliest = $this->teamsFor($capsa)['teams']
            ->map(fn (Team $team) => array_search($this->progress->currentStage($team, $team->defenseSchedules), self::STAGES, true))
            ->reject(fn ($i) => $i === false)
            ->min();

        return self::STAGES[$earliest ?? 0];
    }

    /**
     * The confirmed teams this CaPSA is responsible for, each with its section.
     * A CaPSA with no sections on record (the admin account) sees every team.
     *
     * @return array{teams: Collection<int, Team>, sections: array<int, string>}
     */
    public function teamsFor(CapstoneExpert $capsa): array
    {
        $teams = Team::where('is_confirmed', true)
            ->with(['defenseSchedules.panels', 'defenseSchedules.grades'])
            ->orderBy('team_name')
            ->get();

        $records = StudentSchoolYear::whereIn('team_id', $teams->pluck('team_id'))->with('student')->get()->groupBy('team_id');
        $capsaSections = $capsa->sections ?? [];
        $kept = collect();
        $sections = [];

        foreach ($teams as $team) {
            $section = $this->scope->sectionFor($team, $records->get($team->team_id, collect()));

            if ($capsaSections !== [] && ! $this->scope->covers($capsaSections, $section)) {
                continue;
            }

            $team->setRelation('schoolYears', $records->get($team->team_id, collect()));
            $sections[$team->team_id] = $section !== '' ? $section : SectionScope::UNASSIGNED;
            $kept->push($team);
        }

        return ['teams' => $kept, 'sections' => $sections];
    }

    /** Whether this CaPSA may act on a team. */
    public function covers(CapstoneExpert $capsa, Team $team): bool
    {
        $capsaSections = $capsa->sections ?? [];

        return $capsaSections === [] || $this->scope->covers($capsaSections, $this->scope->sectionFor($team));
    }

    /**
     * Every team's row for this stage, as the pages draw it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(CapstoneExpert $capsa, string $stage): array
    {
        ['teams' => $teams, 'sections' => $sections] = $this->teamsFor($capsa);

        $experts = $this->teamExperts($teams->pluck('team_id'));
        $paid = in_array($stage, self::PAID_STAGES, true) ? self::paidTeamIds($teams, $stage) : $teams->pluck('team_id');
        $panelists = $this->panelists();
        $cancellations = DefenseCancellation::whereIn('team_id', $teams->pluck('team_id'))
            ->where('defense_type', $stage)
            ->orderBy('was_scheduled_at')
            ->get()
            ->groupBy('team_id');

        return $teams->map(function (Team $team) use ($stage, $sections, $experts, $paid, $panelists, $cancellations) {
            $schedule = $team->defenseSchedules->where('defense_type', $stage)->sortByDesc('attempt')->first();
            $tries = $this->triesOf($team, $stage, $schedule, $cancellations->get($team->team_id, collect()));
            $roles = $experts->get($team->team_id, collect());
            $panel = $schedule ? $this->panelOf($schedule) : null;
            $tags = array_values(array_filter(array_map(fn ($t) => trim((string) $t), $team->tags ?: [])));

            return [
                'id' => (int) $team->team_id,
                'name' => $team->team_name,
                'title' => $team->project_title,
                'section' => $sections[$team->team_id],
                'members' => $team->schoolYears->count(),
                'member_list' => $team->schoolYears->map(fn (StudentSchoolYear $sy) => $sy->student)->filter()
                    ->map(fn ($student) => [
                        'id' => (int) $student->student_id,
                        'name' => trim($student->l_name.', '.$student->f_name),
                        'returnee' => (bool) $student->is_returnee,
                    ])->sortBy('name')->values()->all(),
                'adviser' => $roles->get('adviser'),
                'consultant' => $roles->get('consultant'),
                'grammarian' => $roles->get('grammarian')['name'] ?? null,
                'tags' => $tags,
                'suggested' => $panelists->filter(fn ($e) => $this->matchedTags($tags, $e['tags']) !== [])->pluck('id')->values(),
                'panel' => $panel && $panel['chair'] ? $panel : null,
                'panel_status' => $panel && $panel['chair'] ? $team->panel_status : null,
                'panel_final_since' => $team->panelIsFinal() ? $team->panel_finalized_at?->toDateString() : null,
                // The CaPA's go, for a date not yet held: Title, Pre Oral and Final Oral only.
                'needs_go' => self::needsCapaGo($stage),
                'capa' => $schedule?->capa_answer,
                'tries' => $tries,
                'waiting' => $schedule && ! $schedule->schedule_date && ! $schedule->outcome
                    ? (end($tries)['kind'] ?? 'new')
                    : null,
                'journey' => $this->journeyOf($team),
                'defense_id' => $schedule?->defense_id,
                'attempt' => (int) ($schedule->attempt ?? 1),
                'schedule' => $schedule?->schedule_date ? [
                    'date' => $schedule->schedule_date->toDateString(),
                    'time' => $schedule->schedule_date->format('H:i'),
                    'end' => self::endOf($schedule)->format('H:i'),
                    'venue' => $schedule->venue,
                ] : null,
                'outcome' => $schedule?->outcome ? $schedule->outcomeLabel() : null,
                // For the Scheduled tab's Result badge: pass/major/fail read
                // straight off DefenseOutcome; a scheduled defense with no
                // outcome yet is its own tone, not "none" (not_scheduled at
                // all) - the JS tells them apart by whether a date exists.
                'outcome_tone' => $schedule?->outcome ? DefenseOutcome::tone($schedule->outcome) : 'ongoing',
                'passed' => $schedule ? DefenseOutcome::isPass($schedule->outcome) : false,
                // Scored or abstained: either way that panelist is done.
                'graded' => $schedule ? $schedule->gradesInCount() : 0,
                'panel_size' => $schedule ? $schedule->panelSize() : 0,
                'paid' => $paid->contains($team->team_id),
                'blockers' => $this->blockers($team, $stage, $roles, $schedule, $paid),
            ];
        })->values()->all();
    }

    /**
     * What stands between this team and a date, in the order it is cleared.
     *
     * @return array<int, array{key: string, text: string}>
     */
    private function blockers(Team $team, string $stage, Collection $roles, ?DefenseSchedule $schedule, Collection $paid): array
    {
        $missing = array_values(array_diff(Team::REQUIRED_EXPERT_ROLES, $roles->keys()->all()));
        $at = array_search($stage, self::STAGES, true);
        $previous = $at > 0 ? self::STAGES[$at - 1] : null;
        $out = [];

        if ($missing !== []) {
            $out[] = ['key' => 'experts', 'text' => 'Needs '.self::listRoles($missing)];
        }

        if ($previous && ! $this->progress->hasPassed($team, $previous, $team->defenseSchedules)) {
            $out[] = ['key' => 'prev', 'text' => 'Has not passed the '.$previous.' yet'];
        }

        // Named, so the CaPSA knows which checklist line is holding the team up.
        if ($open = $team->checklistBlockersFor($stage)) {
            $out[] = ['key' => 'req', 'text' => 'Requirements not done: '.$open[0].(count($open) > 1 ? ' (and '.(count($open) - 1).' more)' : '')];
        }

        if (! $schedule || ! $schedule->panels->contains('panel_role', 'Panel Chair')) {
            $out[] = ['key' => 'panel', 'text' => 'No panel assigned yet'];
        } elseif ($team->panel_status === Team::PANEL_DRAFT) {
            $out[] = ['key' => 'draft', 'text' => 'Panel is still a draft'];
        } else {
            // Assigning a panel refuses the team's own adviser or consultant,
            // but one approved after the panel was set would still be on it.
            foreach (['adviser', 'consultant'] as $role) {
                $own = $roles->get($role);

                if ($own && $schedule->panels->contains('expert_id', $own['id'])) {
                    $out[] = ['key' => 'own-panelist', 'text' => $own['name'].' is this team\'s '.$role.' and sits on its panel. Change the panel'];
                }
            }
        }

        if (in_array($stage, self::PAID_STAGES, true) && ! $paid->contains($team->team_id)) {
            $out[] = ['key' => 'fees', 'text' => 'Fees not verified'];
        }

        return $out;
    }

    /**
     * The tries at this stage that did not end in a pass, oldest first: dates
     * the CaPA cancelled, and defenses the panel sent back for a redefense.
     *
     * @return array<int, array{kind: string, date: string, note: ?string}>
     */
    private function triesOf(Team $team, string $stage, ?DefenseSchedule $current, Collection $cancellations): array
    {
        $tries = $cancellations->map(fn (DefenseCancellation $c) => [
            'kind' => 'cancelled',
            'date' => $c->was_scheduled_at->toDateString(),
            'note' => $c->note,
        ]);

        $redefenses = $team->defenseSchedules
            ->where('defense_type', $stage)
            ->filter(fn (DefenseSchedule $s) => $s !== $current && $s->schedule_date && DefenseOutcome::requiresRedefense($s->outcome))
            ->map(fn (DefenseSchedule $s) => ['kind' => 'redefense', 'date' => $s->schedule_date->toDateString(), 'note' => $s->outcomeLabel()]);

        return $tries->concat($redefenses)->sortBy('date')->values()->all();
    }

    /**
     * Where the team stands at every stage, for the team's drawer.
     *
     * @return array<int, array{stage: string, attempt: int, date: ?string, time: ?string, venue: ?string, outcome: ?string, passed: bool}>
     */
    private function journeyOf(Team $team): array
    {
        return collect(self::STAGES)->map(function (string $stage) use ($team) {
            $s = $team->defenseSchedules->where('defense_type', $stage)->sortByDesc('attempt')->first();

            return [
                'stage' => $stage,
                'attempt' => (int) ($s->attempt ?? 1),
                'date' => $s?->schedule_date?->toDateString(),
                'time' => $s?->schedule_date?->format('H:i'),
                'venue' => $s?->venue,
                'outcome' => $s?->outcome ? $s->outcomeLabel() : null,
                'passed' => $s ? DefenseOutcome::isPass($s->outcome) : false,
            ];
        })->all();
    }

    /**
     * Panelists' days away from today on, for every panel they sit on.
     *
     * @return array<int, array{id: int, expert_id: int, from: string, to: string, reason: string}>
     */
    public function awayDates(): array
    {
        return ExpertAwayDate::whereDate('ends_on', '>=', today()->toDateString())
            ->orderBy('starts_on')
            ->get()
            ->map(fn (ExpertAwayDate $a) => [
                'id' => (int) $a->id,
                'expert_id' => (int) $a->expert_id,
                'from' => $a->starts_on->toDateString(),
                'to' => $a->ends_on->toDateString(),
                'reason' => $a->reason,
            ])->all();
    }

    /**
     * Teams per stage that have a date or a result, for the stage tabs.
     *
     * @return array<string, array{done: int, of: int}>
     */
    public function stageProgress(CapstoneExpert $capsa): array
    {
        $teams = $this->teamsFor($capsa)['teams'];

        return collect(self::STAGES)->mapWithKeys(fn (string $stage) => [$stage => [
            'done' => $teams->filter(fn (Team $t) => $t->defenseSchedules->where('defense_type', $stage)
                ->contains(fn (DefenseSchedule $s) => $s->schedule_date || $s->outcome))->count(),
            'of' => $teams->count(),
        ]])->all();
    }

    /**
     * The approved adviser, consultant and grammarian of each team.
     *
     * @return Collection<int, Collection<string, array{id: int, name: string}>>
     */
    private function teamExperts(Collection $teamIds): Collection
    {
        return TeamExpertAssignment::whereIn('team_id', $teamIds)
            ->where('status', 'approved')
            ->with(['expert', 'expertRole.role'])
            ->get()
            ->groupBy('team_id')
            ->map(fn ($rows) => $rows
                ->filter(fn ($a) => $a->expert && in_array($a->engaged_role_name, Team::REQUIRED_EXPERT_ROLES, true))
                ->mapWithKeys(fn ($a) => [$a->engaged_role_name => ['id' => (int) $a->expert_id, 'name' => $a->expert->name]]));
    }

    /**
     * Everyone who can sit on a panel: approved advisers and consultants who
     * have signed in. An expert added by email who never has is a blank row
     * with no name, and nobody to tell about the defense.
     * Grammarians check the writing and never sit on a panel.
     *
     * @return Collection<int, array{id: int, name: string, degree: string, chair: bool, tags: array<int, string>}>
     */
    public function panelists(): Collection
    {
        $roleIds = Role::whereIn('role_name', ['adviser', 'consultant'])->pluck('role_id');

        return CapstoneExpert::where('approved_status', true)
            ->where(fn ($q) => $q->whereNotNull('first_login_at')->orWhereNotNull('last_seen_at'))
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.role_id', $roleIds))
            ->with('expertise')
            ->orderBy('last_name')->orderBy('first_name')
            ->get()
            ->map(fn (CapstoneExpert $e) => [
                'id' => (int) $e->expert_id,
                // The name alone: the degree is shown beside it, and credentials would repeat it.
                'name' => trim(implode(' ', array_filter([$e->first_name, $e->middle_name ? mb_substr($e->middle_name, 0, 1).'.' : null, $e->last_name, $e->suffix]))) ?: $e->name,
                'degree' => $e->degreeText(),
                'chair' => $e->canChairPanel(),
                'tags' => $e->expertise->pluck('expertise_field')->filter()->values()->all(),
            ]);
    }

    /** @return array{chair: ?int, m1: ?int, m2: ?int} */
    public function panelOf(DefenseSchedule $schedule): array
    {
        $by = $schedule->panels->keyBy('panel_role');

        return [
            'chair' => isset($by['Panel Chair']) ? (int) $by['Panel Chair']->expert_id : null,
            'm1' => isset($by['Panel Member 1']) ? (int) $by['Panel Member 1']->expert_id : null,
            'm2' => isset($by['Panel Member 2']) ? (int) $by['Panel Member 2']->expert_id : null,
        ];
    }

    /**
     * Defenses on the calendar for teams outside this CaPSA's sections. They
     * are not hers to change, but their rooms and panelists are still taken.
     *
     * @param  array<int, int>  $ownTeamIds
     * @return array<int, array<string, mixed>>
     */
    public function otherBookings(array $ownTeamIds): array
    {
        $schedules = DefenseSchedule::whereNotNull('schedule_date')
            ->whereNotIn('team_id', $ownTeamIds)
            ->with(['panels', 'team'])
            ->get();

        $records = StudentSchoolYear::whereIn('team_id', $schedules->pluck('team_id')->unique())->with('student')->get()->groupBy('team_id');

        return $schedules->map(fn (DefenseSchedule $s) => [
            'section' => $s->team ? ($this->scope->sectionFor($s->team, $records->get($s->team_id, collect())) ?: SectionScope::UNASSIGNED) : SectionScope::UNASSIGNED,
            'stage' => $s->defense_type,
            'date' => $s->schedule_date->toDateString(),
            'time' => $s->schedule_date->format('H:i'),
            'end' => self::endOf($s)->format('H:i'),
            'venue' => $s->venue,
            'panel' => $s->panels->pluck('expert_id')->map(fn ($id) => (int) $id)->values(),
            // The chair identifies a panel, so a page can tell the same panel
            // in two rooms from two different panels sharing a panelist.
            'chair' => $this->panelOf($s)['chair'],
        ])->values()->all();
    }

    /** @return array<string, array{start: ?string, end: ?string}> */
    public function windows(): array
    {
        $windows = DefenseWindow::whereIn('defense_type', self::STAGES)->with('updatedBy')->get()->keyBy('defense_type');

        return collect(self::STAGES)->mapWithKeys(function ($stage) use ($windows) {
            $minutes = $windows->get($stage)?->duration_minutes ?? self::DEFAULT_MINUTES;

            return [$stage => [
                'start' => $windows->get($stage)?->start_date?->toDateString(),
                'end' => $windows->get($stage)?->end_date?->toDateString(),
                'minutes' => $minutes,
                'slots' => self::slots($minutes),
                'set_by' => $windows->get($stage)?->updatedBy?->name,
                'set_on' => $windows->get($stage)?->updated_at?->toDateString(),
            ]];
        })->all();
    }

    /** When a defense ends; rows with no end, or one not after the start, count as two hours. */
    public static function endOf(DefenseSchedule $s): Carbon
    {
        return $s->end_date?->gt($s->schedule_date) ? $s->end_date : $s->schedule_date->copy()->addMinutes(self::DEFAULT_MINUTES);
    }

    /**
     * Whether panelists will find a rubric to grade this stage with.
     *
     * With $programs, one for each of them; without, any at all.
     *
     * @param  list<string>  $programs
     */
    public static function hasRubric(string $stage, array $programs = []): bool
    {
        return $programs === []
            ? GradingRubric::inUseFor($stage)->exists()
            : self::programsWithoutRubric($stage, $programs) === [];
    }

    /**
     * Which of $programs have no rubric for this stage - neither their own
     * nor one for every program.
     *
     * @param  list<string>  $programs
     * @return list<string>
     */
    public static function programsWithoutRubric(string $stage, array $programs): array
    {
        $covered = GradingRubric::inUseFor($stage)->pluck('program');

        if ($covered->contains(null)) {
            return [];
        }

        return array_values(array_diff($programs, $covered->all()));
    }

    /**
     * Teams whose every member has a verified receipt for this stage's fee.
     * One payment covers the stage, redefenses included.
     */
    public static function paidTeamIds(Collection $teams, string $stage): Collection
    {
        $postIds = ExpensePost::where('defense_type', $stage)->pluck('id');

        $memberIds = StudentSchoolYear::whereIn('team_id', $teams->pluck('team_id'))
            ->get(['team_id', 'student_id'])
            ->groupBy('team_id')
            ->map(fn ($rows) => $rows->pluck('student_id')->filter()->values());

        $verified = FinancialRecord::whereIn('expense_post_id', $postIds)
            ->where('f_record_status', 'verified')
            ->whereIn('student_id', $memberIds->flatten()->unique())
            ->distinct()
            ->pluck('student_id');

        return $memberIds->filter(fn ($ids) => $ids->isNotEmpty() && $ids->diff($verified)->isEmpty())->keys();
    }

    /** ['consultant', 'grammarian'] reads as "a consultant and a grammarian". */
    public static function listRoles(array $roles): string
    {
        $named = array_map(fn ($r) => (in_array($r[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an ' : 'a ').$r, array_values($roles));

        return count($named) > 1 ? implode(', ', array_slice($named, 0, -1)).' and '.end($named) : ($named[0] ?? '');
    }

    /**
     * Names of every expert these rows and bookings mention. A panelist who
     * is no longer an approved adviser or consultant still sits on the panels
     * they were given, and those still have to read as a name.
     *
     * @return array<int, string> expert_id => name
     */
    public static function names(array $rows, array $other = []): array
    {
        $ids = collect($rows)->flatMap(fn ($r) => $r['panel'] ? array_values($r['panel']) : [])
            ->merge(collect($other)->flatMap(fn ($o) => $o['panel']))
            ->filter()->unique()->values();

        return CapstoneExpert::whereIn('expert_id', $ids)->get()->mapWithKeys(fn ($e) => [(int) $e->expert_id => $e->name])->all();
    }

    /** @return array<int, string> the team tags an expert's expertise covers */
    public static function matchedTags(array $tags, array $expertiseFields): array
    {
        $fields = array_filter(array_map(fn ($f) => mb_strtolower(trim((string) $f)), $expertiseFields));
        $matched = [];

        foreach ($tags as $tag) {
            $candidates = array_merge([mb_strtolower($tag)], self::TAG_SYNONYMS[mb_strtolower($tag)] ?? []);

            foreach ($fields as $field) {
                foreach ($candidates as $candidate) {
                    if (str_contains($field, $candidate) || str_contains($candidate, $field)) {
                        $matched[] = $tag;

                        continue 3;
                    }
                }
            }
        }

        return array_values(array_unique($matched));
    }
}
