<?php

namespace Database\Seeders;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dummy defenses for trying Team Grading - and its paper copy - as an expert.
 *
 * Four BSIT-3B teams, each at a different point, with the chosen expert on
 * every panel:
 *
 *   Demo Grading Alpha    Pre-proposal Defense, tomorrow - Panel Chair, nothing graded yet
 *   Demo Grading Bravo    Pre-proposal Defense, today    - Panel Member 1, a draft half done
 *   Demo Grading Charlie  Title Defense, yesterday       - Panel Chair, the other two in: grade, then record the decision
 *   Demo Grading Delta    Title Defense, last week       - everyone in, decision recorded (the Done list)
 *
 * The Pre-proposal Defense is graded with the department's rubric form
 * (PreProposal-Rubric.docx); it is added if no Pre-proposal rubric has any
 * criteria. Every other account is @example.test, so nothing emails a real
 * person. Running it again replaces the demo.
 *
 *   php artisan db:seed --class=TeamGradingDemoSeeder
 *   GRADING_DEMO_EXPERT=someone@gmail.com php artisan db:seed --class=TeamGradingDemoSeeder
 *
 * To take it all out again:
 *
 *   php artisan tinker --execute "Database\Seeders\TeamGradingDemoSeeder::clean();"
 */
class TeamGradingDemoSeeder extends Seeder
{
    private const PREFIX = 'Demo Grading ';

    private const EXPERT = 'dongaurillas@gmail.com';

    private const PRE_PROPOSAL = 'Pre-proposal Defense';

    private const TITLE_DEFENSE = 'Title Defense';

    /** Each demo team's members, leader first. */
    private const MEMBERS = [
        'Alpha' => [['Maria', 'Cruz'], ['Jose', 'Santos'], ['Ana', 'Lim']],
        'Bravo' => [['Carlo', 'Mendoza'], ['Bea', 'Ramos'], ['Paolo', 'Garcia']],
        'Charlie' => [['Liza', 'Aquino'], ['Mark', 'Villareal'], ['Joy', 'Navarro']],
        'Delta' => [['Rhea', 'Domingo'], ['Ken', 'Salazar'], ['Ivy', 'Torres']],
    ];

    public function run(): void
    {
        self::clean();

        $me = CapstoneExpert::where('email', env('GRADING_DEMO_EXPERT', self::EXPERT))->first()
            ?? CapstoneExpert::where('is_admin', false)->orderBy('expert_id')->first();

        if (! $me) {
            $this->command?->error('No expert account to put on the panels. Sign in as an expert first, then run this again.');

            return;
        }

        $preProposal = $this->preProposalRubric();
        $titleDefense = GradingRubric::forStage(self::TITLE_DEFENSE, 'BSIT');

        if (! $titleDefense || $titleDefense->criteria()->doesntExist()) {
            $titleDefense = null;
            $this->command?->warn('No Title Defense rubric with criteria - the Title Defense demos use the Pre-proposal rubric instead.');
        }

        $liza = $this->expert('Liza', 'Bautista', 'demo.grading.liza@example.test', 'master', 'MIT');
        $jomar = $this->expert('Jomar', 'Reyes', 'demo.grading.jomar@example.test', 'master', 'MSCS');

        $day = fn (int $days, int $hour) => now()->startOfDay()->addDays($days)->setTime($hour, 0);

        // Alpha: tomorrow, my panel to chair, nobody has graded.
        $alpha = $this->defense('Alpha', 'Clinic Queue Management System for CSPC', self::PRE_PROPOSAL, $day(1, 9), 'Mac Lab',
            [[$me, 'Panel Chair'], [$liza, 'Panel Member 1'], [$jomar, 'Panel Member 2']]);

        // Bravo: today, I sit as a member and have started a draft; the chair is in.
        $bravo = $this->defense('Bravo', 'Barangay Health Records Portal', self::PRE_PROPOSAL, $day(0, 13), 'Room 204',
            [[$liza, 'Panel Chair'], [$me, 'Panel Member 1'], [$jomar, 'Panel Member 2']]);
        $this->grade($bravo, $liza, $preProposal, PanelGrade::SUBMITTED, 0);
        $this->grade($bravo, $me, $preProposal, PanelGrade::DRAFT, 1, half: true);

        // Charlie: yesterday, the other two are in - I grade, then record the decision as chair.
        $charlieRubric = $titleDefense ?? $preProposal;
        $charlie = $this->defense('Charlie', 'Farm Produce Price Board with SMS Alerts', $titleDefense ? self::TITLE_DEFENSE : self::PRE_PROPOSAL, $day(-1, 9), 'Mac Lab',
            [[$me, 'Panel Chair'], [$liza, 'Panel Member 1'], [$jomar, 'Panel Member 2']]);
        $this->grade($charlie, $liza, $charlieRubric, PanelGrade::SUBMITTED, 0);
        $this->grade($charlie, $jomar, $charlieRubric, PanelGrade::SUBMITTED, 1);

        // Delta: last week, all graded and decided.
        $delta = $this->defense('Delta', 'Evacuation Center Monitoring System', $titleDefense ? self::TITLE_DEFENSE : self::PRE_PROPOSAL, $day(-7, 13), 'Room 204',
            [[$jomar, 'Panel Chair'], [$me, 'Panel Member 1'], [$liza, 'Panel Member 2']]);
        foreach ([[$jomar, 0], [$me, 1], [$liza, 2]] as [$panelist, $seed]) {
            $this->grade($delta, $panelist, $charlieRubric, PanelGrade::SUBMITTED, $seed);
        }
        $delta->update(['outcome' => DefenseOutcome::PASSED, 'outcome_recorded_by' => $jomar->expert_id, 'outcome_recorded_at' => now()->subDays(7)->setTime(15, 0)]);

        $this->command?->info('Team Grading demo is ready for '.$me->name.' ('.$me->email.'): open Team Grading in the expert portal.');
    }

    /** Removes every demo team, its students, defenses and grades, and the demo panelists. */
    public static function clean(): void
    {
        DB::transaction(function () {
            $teams = Team::where('team_name', 'like', self::PREFIX.'%')->get();
            $defenseIds = DefenseSchedule::whereIn('team_id', $teams->pluck('team_id'))->pluck('defense_id');

            PanelGrade::whereIn('defense_id', $defenseIds)->delete();
            DefensePanel::whereIn('defense_id', $defenseIds)->delete();
            DefenseSchedule::whereIn('defense_id', $defenseIds)->delete();

            $studentIds = StudentSchoolYear::acrossAllTerms()->whereIn('team_id', $teams->pluck('team_id'))->pluck('student_id');
            StudentSchoolYear::acrossAllTerms()->whereIn('student_id', $studentIds)->delete();
            Student::whereIn('student_id', $studentIds)->where('email', 'like', '%@example.test')->delete();
            Team::whereIn('team_id', $teams->pluck('team_id'))->delete();

            CapstoneExpert::withTrashed()->where('email', 'like', 'demo.grading.%@example.test')->forceDelete();
        });
    }

    /** The department's Pre-proposal rubric form, unless one with criteria is already in use. */
    private function preProposalRubric(): GradingRubric
    {
        $inUse = GradingRubric::forStage(self::PRE_PROPOSAL, 'BSIT');

        if ($inUse && $inUse->criteria()->exists()) {
            return $inUse;
        }

        $rubric = GradingRubric::firstOrCreate(
            ['rubric_name' => 'Pre-Proposal Defense Rubric', 'rubric_type' => self::PRE_PROPOSAL],
            ['description' => 'Manuscript', 'total_points' => 100, 'is_active' => true, 'is_archived' => false],
        );

        if ($rubric->criteria()->exists()) {
            return $rubric;
        }

        $order = 0;
        $add = function (string $category, float $categoryMax, string $name, array $levels) use ($rubric, &$order) {
            $rubric->criteria()->create([
                'criterion_name' => $name,
                'category' => $category,
                'category_max_points' => $categoryMax,
                'max_points' => $levels[0][0],
                'sort_order' => $order++,
                'score_levels' => json_encode(array_map(
                    fn ($l, $i) => ['score' => $l[0], 'label' => ['Excellent', 'Good', 'Fair', 'Poor'][$i] ?? '', 'desc' => $l[1]],
                    $levels, array_keys($levels),
                )),
            ]);
        };
        $same = fn (string $d, array $points) => array_map(fn ($p) => [$p, $d], $points);

        $oral = 'Oral Examination/ Presentation';
        $add($oral, 20, 'Readiness', [[5, 'All the team members appeared to be ready and presentable.'], [3.75, 'All the team members appeared to be ready but not all are presentable.'], [2.5, 'Only 50 % of the team members appeared to be ready and presentable.'], [1.25, 'Less than 50% of the team members appeared to be ready and not presentable.']]);
        $add($oral, 20, 'Presentation slides', [[5, 'All the presentation slides were intelligently and strategically used.'], [3.75, 'At least 75% of the presentation slides were intelligently and strategically used.'], [2.5, 'Only 50 % of the presentation slides were intelligently and strategically used.'], [1.25, 'Less than 50 % of the presentation slides were intelligently and strategically used.']]);
        $add($oral, 20, 'Delivery', [[5, 'Delivery is clear, confident, and articulate; excellent command of language.'], [3.75, 'Delivery is clear with minor lapses.'], [2.5, 'Delivery is understandable but lacks confidence or clarity.'], [1.25, 'Delivery is unclear and difficult to follow.']]);
        $add($oral, 20, 'Answers to the panel', [[5, 'All panel questions and queries were confidently and satisfactorily answered.'], [3.75, 'At least 75 % of the panel questions and queries were confidently and satisfactorily answered.'], [2.5, 'Only 50 % of the panel questions and queries were confidently and satisfactorily answered.'], [1.25, 'Less than 50 % of the panel questions and queries were confidently and satisfactorily answered.']]);

        $merit = 'Topic Quality and Proposal Merit';
        $add($merit, 30, 'Feasibility', $same('Project is clearly achievable within time, skills, and resources; risks are well-considered.', [6, 4, 2.5, 1.25]));
        $add($merit, 30, 'Significance', $same('Topic is highly engaging with strong academic and practical value.', [6, 4, 2.5, 1.25]));
        $add($merit, 30, 'Originality', $same('Demonstrates strong originality or innovative approach.', [6, 4, 2.5, 1.25]));
        $add($merit, 30, 'Ethics and data privacy', $same('Ethical issues and data privacy are clearly identified and properly addressed.', [6, 4, 2.5, 1.25]));
        $add($merit, 30, 'Alignment', $same('Highly aligned with research agenda, SDGs, and stakeholder needs.', [6, 4, 2.5, 1.25]));

        $content = 'Proposal Content and Clarity';
        $add($content, 50, 'Problem', $same('Problem is clear, specific, well-justified, and evidence-based.', [20, 12.5, 8.5, 4.25]));
        $add($content, 50, 'Solution', $same('Solution directly addresses the problem; scope and limitations are well-defined.', [20, 12.5, 8.5, 4.25]));
        $add($content, 50, 'SDM', $same('SDM is appropriate and clearly justified.', [10, 5, 3, 1.5]));

        return $rubric;
    }

    private function expert(string $first, string $last, string $email, string $degree, string $title): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => $email, 'first_name' => $first, 'last_name' => $last,
            'highest_degree' => $degree, 'degree_title' => $title,
            'approved_status' => true, 'is_admin' => false, 'first_login_at' => now(),
        ]);
    }

    /** @param list<array{0: CapstoneExpert, 1: string}> $panel */
    private function defense(string $name, string $title, string $stage, $at, string $venue, array $panel): DefenseSchedule
    {
        $slug = strtolower($name);
        $members = collect(self::MEMBERS[$name])->map(fn ($n, $i) => Student::create([
            'email' => "demo.grading.{$slug}{$i}@example.test",
            'f_name' => $n[0],
            'l_name' => $n[1],
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · B',
            'student_number' => null,
        ]));

        $team = Team::create([
            'team_name' => self::PREFIX.$name,
            'project_title' => $title,
            'team_status' => 'Active',
            'is_confirmed' => true,
            'leader_id' => $members->first()->student_id,
            'member_count' => $members->count(),
        ]);

        foreach ($members as $student) {
            StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);
        }

        $defense = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => $stage,
            'attempt' => 1,
            'schedule_date' => $at,
            'end_date' => $at->copy()->addHour(),
            'venue' => $venue,
        ]);

        foreach ($panel as [$expert, $role]) {
            DefensePanel::create(['defense_id' => $defense->defense_id, 'expert_id' => $expert->expert_id, 'panel_role' => $role, 'created_at' => now()]);
        }

        return $defense;
    }

    /** A grade with each criterion scored at a level - the best one, or one or two below for variety. */
    private function grade(DefenseSchedule $defense, CapstoneExpert $panelist, GradingRubric $rubric, string $status, int $seed, bool $half = false): void
    {
        $criteria = $rubric->criteria()->orderBy('sort_order')->get();
        $scores = [];

        foreach ($criteria as $i => $criterion) {
            if ($half && $i >= intdiv($criteria->count(), 2)) {
                break;
            }
            $levels = collect($criterion->decodedScoreLevels())->pluck('score')->map(fn ($s) => (float) $s)->sortDesc()->values();
            $scores[(string) $criterion->criterion_id] = $levels->get(($i + $seed) % 3 === 0 ? 1 : 0) ?? $levels->first() ?? (float) $criterion->max_points;
        }

        PanelGrade::create([
            'defense_id' => $defense->defense_id,
            'expert_id' => $panelist->expert_id,
            'rubric_id' => $rubric->rubric_id,
            'scores' => $scores,
            'total_score' => array_sum($scores),
            'remarks' => $status === PanelGrade::SUBMITTED ? 'Clear presentation. Tighten the scope and cite more recent studies.' : null,
            'status' => $status,
            'submitted_at' => $status === PanelGrade::SUBMITTED ? now() : null,
        ]);
    }
}
