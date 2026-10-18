<?php

namespace App\Support;

use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\EvaluationTemplate;
use App\Models\ExpertEvaluation;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Services\Notifier;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The leader's evaluation of the team's experts, once per passed defense.
 *
 * When a team passes a defense - Pre-proposal, Title, Pre Oral or Final Oral
 * - its leader owes an evaluation of each engaged expert the CaPSA's
 * evaluation for that defense covers. The CaPSA writes the questions
 * (EvaluationTemplate); each is answered 1 to 5, and the expert's rating for
 * that defense is the average. The leader is anonymous to the CaPSA and the
 * expert.
 *
 * Only a defense decided after its evaluation was set up counts: one a team
 * passed before the CaPSA had written any questions owes nothing, so adding
 * an evaluation never reaches back and locks teams that are long past it.
 * What the leader is asked, though, is the evaluation last set for that
 * defense - its questions and its roles - so a CaPSA's edit reaches every
 * leader yet to answer, a role it drops included. Every CaPSA of the team's
 * section counts, when a section has more than one; the one who edited last
 * decides.
 *
 * What is owed is worked out here and nowhere else - the Progress Tracker's
 * gate, the popup, the Experts page and the reminder all ask owedForTeams(),
 * so they cannot disagree.
 */
class ExpertEvaluations
{
    /** A defense that ended in one of these was passed. */
    public const PASSING = [DefenseOutcome::PASSED, DefenseOutcome::PASSED_WITH_REVISION];

    /**
     * What this team's leader still owes, earliest defense first.
     *
     * @return Collection<int, object{defense_id: int, stage: string, expert_id: int, expert: string, name: string, role: string, role_label: string, template_id: int}>
     */
    public function due(Team $team): Collection
    {
        return self::owedForTeams(collect([$team->team_id]))[(int) $team->team_id] ?? collect();
    }

    /**
     * Every team's owed evaluations, in a handful of queries.
     *
     * @param  Collection<int, int>  $teamIds
     * @return array<int, Collection<int, object>>
     */
    public static function owedForTeams(Collection $teamIds): array
    {
        $teamIds = $teamIds->map(fn ($id) => (int) $id)->unique()->values();
        $out = $teamIds->mapWithKeys(fn ($id) => [$id => collect()])->all();

        if ($teamIds->isEmpty()) {
            return $out;
        }

        $passed = DefenseSchedule::whereIn('team_id', $teamIds)
            ->whereIn('outcome', self::PASSING)
            ->orderByDesc('attempt')
            ->get()
            ->groupBy('team_id');

        if ($passed->isEmpty()) {
            return $out;
        }

        // Whose evaluations apply: every CaPSA of each team's section - a
        // section can have more than one.
        $teams = Team::whereIn('team_id', $passed->keys())->get()->keyBy('team_id');
        $records = StudentSchoolYear::with('student')->whereIn('team_id', $passed->keys())->get()->groupBy('team_id');
        $scope = app(SectionScope::class);
        $capsasBySection = self::capsasBySection();
        $capsasOf = [];
        foreach ($teams as $id => $team) {
            $capsasOf[$id] = $capsasBySection[$scope->sectionFor($team, $records->get($id, collect()))] ?? [];
        }

        $templates = EvaluationTemplate::active()
            ->whereIn('capsa_id', collect($capsasOf)->flatten()->unique()->values())
            ->whereHas('questions')
            ->orderByDesc('id')
            ->get();

        if ($templates->isEmpty()) {
            return $out;
        }

        $experts = self::engagedExperts($passed->keys());
        $done = ExpertEvaluation::whereIn('team_id', $passed->keys())->whereNotNull('defense_id')
            ->get(['team_id', 'defense_id', 'expert_id', 'role'])
            ->mapWithKeys(fn ($e) => [$e->defense_id.'|'.$e->expert_id.'|'.$e->role => true]);

        foreach ($passed as $teamId => $defenses) {
            $capsaIds = $capsasOf[$teamId] ?? [];

            // The latest attempt of each stage is the one that passed.
            foreach ($defenses->unique(fn ($d) => mb_strtolower($d->defense_type)) as $defense) {
                $decided = $defense->outcome_recorded_at ?? $defense->schedule_date ?? $defense->updated_at ?? Carbon::now();

                // This defense's evaluations, by any CaPSA of the section.
                $forStage = $templates->filter(fn (EvaluationTemplate $t) => in_array((int) $t->capsa_id, $capsaIds, true)
                    && mb_strtolower($t->defense_type) === mb_strtolower($defense->defense_type));

                if ($forStage->isEmpty()) {
                    continue;
                }

                // The CaPSA who last set this defense's evaluation decides it
                // whole - its questions and its roles - so an edit that drops
                // a role drops it here too, not just the questions.
                $newest = fn (Collection $set) => $set->sortByDesc(fn (EvaluationTemplate $t) => [$t->updated_at?->getTimestamp() ?? 0, $t->id])->first();
                $current = $forStage->where('capsa_id', $newest($forStage)->capsa_id);

                foreach ($experts->get($teamId, collect()) as $expert) {
                    $covering = $current->filter(fn (EvaluationTemplate $t) => $t->covers($expert->role));

                    // Owed only if an evaluation for this role was there when
                    // the defense was decided, so nothing reaches back.
                    $wasSetUp = $forStage->contains(fn (EvaluationTemplate $t) => $t->covers($expert->role) && $t->created_at <= $decided);
                    $template = $wasSetUp && $covering->isNotEmpty() ? $newest($covering) : null;

                    if (! $template || $done->has($defense->defense_id.'|'.$expert->expert_id.'|'.$expert->role)) {
                        continue;
                    }

                    $out[(int) $teamId]->push((object) [
                        'defense_id' => (int) $defense->defense_id,
                        'stage' => (string) $defense->defense_type,
                        'expert_id' => $expert->expert_id,
                        'expert' => $expert->name,
                        'name' => $expert->name,
                        'role' => $expert->role,
                        'role_label' => ExpertEvaluation::ROLES[$expert->role],
                        'template_id' => (int) $template->id,
                    ]);
                }
            }

            $out[(int) $teamId] = $out[(int) $teamId]
                ->sortBy(fn ($row) => [self::stageOrder($row->stage), array_search($row->role, array_keys(ExpertEvaluation::ROLES), true)])
                ->values();
        }

        return $out;
    }

    /**
     * What the team has already sent, newest first.
     *
     * @return Collection<int, ExpertEvaluation>
     */
    public function history(Team $team): Collection
    {
        return ExpertEvaluation::where('team_id', $team->team_id)
            ->with(['expert', 'defense'])
            ->latest()
            ->get();
    }

    /**
     * Save the leader's answers. Only what is actually owed can be sent, only
     * by the team's leader, and every question needs a score from 1 to 5.
     *
     * @param  array<int|string, mixed>  $answers  question id => 1..5
     * @return string|null why it was refused, or null when it was saved
     */
    public function submit(Student $leader, Team $team, int $defenseId, int $expertId, string $role, array $answers): ?string
    {
        if ((int) $team->leader_id !== (int) $leader->student_id) {
            return 'Only your team leader evaluates the experts.';
        }

        $owed = $this->due($team)->first(fn ($row) => $row->defense_id === $defenseId
            && $row->expert_id === $expertId && $row->role === $role);

        if (! $owed) {
            return 'That evaluation is not due, or it was already sent.';
        }

        $template = EvaluationTemplate::with('questions')->find($owed->template_id);
        $scored = [];

        // Answers to none of these questions: the CaPSA changed them while the
        // page was open.
        if ($answers !== [] && $template->questions->pluck('id')->intersect(array_map('intval', array_keys($answers)))->isEmpty()) {
            return 'Your CaPSA changed these questions while you were answering. Here they are again.';
        }

        foreach ($template->questions as $question) {
            $score = (int) ($answers[$question->id] ?? 0);

            if ($score < 1 || $score > 5) {
                return 'Answer every question from 1 to 5.';
            }

            // The wording as it was asked, so editing the template later
            // cannot change what this evaluation said.
            $scored[] = ['question' => $question->question, 'score' => $score];
        }

        ExpertEvaluation::create([
            'team_id' => $team->team_id,
            'expert_id' => $expertId,
            'defense_id' => $defenseId,
            'evaluation_template_id' => $template->id,
            'role' => $role,
            'answers' => $scored,
            'rating' => round(collect($scored)->avg('score'), 2),
            'student_id' => $leader->student_id,
        ]);

        return null;
    }

    /**
     * Tell the leader - bell, desktop popup and email - about each passed
     * defense that still has evaluations owed, once per defense.
     *
     * The caller may pass what due() already worked out for this team, so a
     * page that also shows it does not work it out twice.
     *
     * @param  Collection<int, object>|null  $due
     * @return int how many defenses were announced
     */
    public function remind(Team $team, ?Collection $due = null): int
    {
        $leader = $team->leader_id ? Student::find($team->leader_id) : null;

        if (! $leader) {
            return 0;
        }

        $owedDefenses = ($due ?? $this->due($team))->groupBy('defense_id');
        // Told once per leader: a new leader of the team hears about it too.
        $told = DB::table('expert_evaluation_reminders')->where('team_id', $team->team_id)
            ->where('student_id', $leader->student_id)
            ->whereNotNull('defense_id')->pluck('defense_id')->map(fn ($id) => (int) $id)->all();

        $count = 0;
        foreach ($owedDefenses as $defenseId => $rows) {
            if (in_array((int) $defenseId, $told, true)) {
                continue;
            }

            DB::table('expert_evaluation_reminders')->insertOrIgnore([
                'team_id' => $team->team_id,
                'defense_id' => $defenseId,
                'student_id' => $leader->student_id,
                'notified_at' => now(),
            ]);

            $stage = $rows->first()->stage;
            $names = $rows->map(fn ($r) => $r->expert.' ('.$r->role_label.')')->unique()->implode(', ');

            Notifier::toStudent(
                $leader,
                'evaluation_due',
                'Evaluate your '.self::whom($rows).': '.$stage,
                'Your team passed <strong>'.e($stage).'</strong>. Answer your CaPSA\'s evaluation for '.e($names).'. It is anonymous, and the next phase stays locked until it is done.',
                route('student.experts', ['tab' => 'evaluations']),
                ['tag' => 'evaluation:defense:'.$defenseId],
            );
            $count++;
        }

        return $count;
    }

    /**
     * The defenses coming up in this CaPSA's sections whose stage has no
     * evaluation yet. The leaders of the teams that pass will owe one only if
     * it exists before the defense is decided, so the CaPSA is asked for it
     * as soon as the date is set.
     *
     * @return Collection<int, object{stage: string, teams: int, first: Carbon}>
     */
    public function upcomingWithoutEvaluation(CapstoneExpert $capsa): Collection
    {
        $sections = $capsa->sections ?? [];

        if ($sections === []) {
            return collect();
        }

        $upcoming = DefenseSchedule::whereNull('outcome')
            ->whereNotNull('schedule_date')
            ->where('schedule_date', '>=', now()->startOfDay())
            ->get(['defense_id', 'team_id', 'defense_type', 'schedule_date']);

        if ($upcoming->isEmpty()) {
            return collect();
        }

        $teams = Team::whereIn('team_id', $upcoming->pluck('team_id')->unique())->get();
        $records = StudentSchoolYear::with('student')->whereIn('team_id', $teams->pluck('team_id'))->get()->groupBy('team_id');
        $scope = app(SectionScope::class);
        $sectionOf = $teams->mapWithKeys(fn (Team $team) => [$team->team_id => $scope->sectionFor($team, $records->get($team->team_id, collect()))]);

        // An evaluation by any CaPSA of the team's section covers it.
        $capsasBySection = self::capsasBySection();
        $stagesBy = EvaluationTemplate::active()->whereHas('questions')
            ->whereIn('capsa_id', collect($sections)->flatMap(fn ($s) => $capsasBySection[$s] ?? [])->unique()->values())
            ->get(['capsa_id', 'defense_type'])
            ->groupBy('capsa_id')
            ->map(fn (Collection $rows) => $rows->map(fn ($t) => mb_strtolower($t->defense_type))->unique()->all());
        $covered = fn ($defense) => collect($capsasBySection[$sectionOf[$defense->team_id] ?? ''] ?? [])
            ->contains(fn ($capsaId) => in_array(mb_strtolower((string) $defense->defense_type), $stagesBy->get($capsaId, []), true));

        return $upcoming
            ->filter(fn ($d) => in_array($sectionOf[$d->team_id] ?? '', $sections, true))
            ->reject($covered)
            ->groupBy('defense_type')
            ->map(fn (Collection $rows, string $stage) => (object) [
                'stage' => $stage,
                'teams' => $rows->pluck('team_id')->unique()->count(),
                'first' => $rows->min('schedule_date'),
            ])
            ->sortBy(fn ($row) => self::stageOrder($row->stage))
            ->values();
    }

    /**
     * Every CaPSA of each section, by section.
     *
     * @return array<string, array<int, int>>
     */
    private static function capsasBySection(): array
    {
        $out = [];
        foreach (CapstoneExpert::whereNotNull('sections')->get(['expert_id', 'sections']) as $capsa) {
            foreach ($capsa->sections ?? [] as $section) {
                $out[$section][] = (int) $capsa->expert_id;
            }
        }

        return $out;
    }

    /** Position of a stage in the capstone, for ordering. */
    public static function stageOrder(string $stage): int
    {
        $at = array_search(mb_strtolower($stage), array_map('mb_strtolower', EvaluationTemplate::STAGES), true);

        return $at === false ? 99 : $at;
    }

    /**
     * Who owed rows ask the team to evaluate, by role: "adviser and
     * consultant". Students never see the word "expert" for one of theirs.
     */
    public static function whom(Collection $rows): string
    {
        $roles = array_values(array_intersect(array_keys(ExpertEvaluation::ROLES), $rows->pluck('role')->all()));

        return Arr::join($roles, ', ', ' and ');
    }

    /**
     * Each team's experts by the role they were engaged as.
     *
     * @param  Collection<int, int>  $teamIds
     * @return Collection<int, Collection<int, object{expert_id: int, name: string, role: string}>>
     */
    private static function engagedExperts(Collection $teamIds): Collection
    {
        return TeamExpertAssignment::whereIn('team_id', $teamIds)
            ->where('status', 'approved')
            ->with(['expert', 'expertRole.role'])
            ->get()
            ->groupBy('team_id')
            ->map(fn ($rows) => $rows
                ->map(fn (TeamExpertAssignment $a) => (object) [
                    'expert_id' => (int) $a->expert_id,
                    'name' => $a->expert?->name ?? 'Expert',
                    'role' => strtolower(trim((string) $a->engaged_role_name)),
                ])
                ->filter(fn ($e) => array_key_exists($e->role, ExpertEvaluation::ROLES))
                ->unique(fn ($e) => $e->expert_id.'|'.$e->role)
                ->values());
    }
}
