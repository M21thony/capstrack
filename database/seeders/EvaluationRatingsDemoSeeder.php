<?php

namespace Database\Seeders;

use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\EvaluationTemplate;
use App\Models\ExpertEvaluation;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use App\Support\TeamChecklist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dummy expert evaluations, for seeing the ratings: the CaPSA's Expert
 * Evaluations (questions and results), the expert's My Evaluations, and the
 * stars and review counts on the students' Find experts.
 *
 * Ten BSIT-3B teams passed their Pre-proposal Defense over the last two
 * months, six of them their Title Defense too, and each leader evaluated the
 * team's experts on the CaPSA's questions:
 *
 *   Pre-proposal Defense   the five standard questions, every role
 *   Title Defense          four questions, adviser and consultant
 *   Pre Oral Defense       three questions on grammar, grammarian (no answers yet)
 *
 * The experts are the chosen one (an adviser on six teams, the consultant on
 * four) and three demo experts - Liza Bautista (adviser), Jomar Reyes
 * (consultant) and Ana Villanueva (grammarian) - each rated on a different
 * curve, so the averages differ. The evaluations belong to the CaPSA of
 * BSIT-3B. Every other account is @example.test, so nothing emails a real
 * person. Running it again replaces the demo.
 *
 *   php artisan db:seed --class=EvaluationRatingsDemoSeeder
 *   EVAL_DEMO_EXPERT=someone@gmail.com EVAL_DEMO_CAPSA=capsa@gmail.com php artisan db:seed --class=EvaluationRatingsDemoSeeder
 *
 * To take it all out again:
 *
 *   php artisan tinker --execute "Database\Seeders\EvaluationRatingsDemoSeeder::clean();"
 */
class EvaluationRatingsDemoSeeder extends Seeder
{
    private const PREFIX = 'Demo Eval ';

    private const TEMPLATE_PREFIX = 'Demo: ';

    private const EXPERT = 'dongaurillas@gmail.com';

    private const SECTION = 'BSIT-3B';

    private const TITLE_QUESTIONS = [
        'The expert reviewed our title and chapters before the defense.',
        'The expert helped us answer the panel\'s likely questions.',
        'The expert was available for consultation when we needed it.',
        'The expert\'s comments made our manuscript clearly better.',
    ];

    private const GRAMMAR_QUESTIONS = [
        'The grammarian returned our papers on time.',
        'The corrections were clear and easy to apply.',
        'The grammarian explained the rules behind each correction.',
    ];

    /** How each expert tends to be rated: the chance of a 5, a 4 and a 3; the rest are 2s. */
    private const CURVES = [
        'me' => [0.62, 0.30, 0.08],
        'liza' => [0.40, 0.42, 0.15],
        'jomar' => [0.18, 0.40, 0.32],
        'ana' => [0.72, 0.24, 0.04],
    ];

    /** Each demo team's project, and who leads it. */
    private const TEAMS = [
        ['Clinic Queue Management System', 'Maria', 'Cruz'],
        ['Barangay Health Records Portal', 'Carlo', 'Mendoza'],
        ['Farm Produce Price Board', 'Liza', 'Aquino'],
        ['Evacuation Center Monitoring', 'Rhea', 'Domingo'],
        ['Library Seat Reservation App', 'Paolo', 'Garcia'],
        ['Tricycle Fare Calculator', 'Bea', 'Ramos'],
        ['Canteen Pre-order System', 'Mark', 'Villareal'],
        ['Lost and Found Board for CSPC', 'Joy', 'Navarro'],
        ['Water Refill Delivery Tracker', 'Ken', 'Salazar'],
        ['Boarding House Finder', 'Ivy', 'Torres'],
    ];

    public function run(): void
    {
        self::clean();
        mt_srand(20261010);

        $me = CapstoneExpert::where('email', env('EVAL_DEMO_EXPERT', self::EXPERT))->first()
            ?? CapstoneExpert::where('is_admin', false)->orderBy('expert_id')->first();
        $capsa = env('EVAL_DEMO_CAPSA')
            ? CapstoneExpert::where('email', env('EVAL_DEMO_CAPSA'))->first()
            : app(TeamChecklist::class)->capsaFor(self::SECTION);

        if (! $me || ! $capsa) {
            $this->command?->error(! $me
                ? 'No expert account to rate. Sign in as an expert first, then run this again.'
                : 'No CaPSA covers '.self::SECTION.'. Give a CaPSA that section, or set EVAL_DEMO_CAPSA.');

            return;
        }

        $liza = $this->expert('Liza', 'Bautista', 'adviser');
        $jomar = $this->expert('Jomar', 'Reyes', 'consultant');
        $ana = $this->expert('Ana', 'Villanueva', 'grammarian');

        // Set up before any of the defenses, so every one of them counts.
        $set = now()->subDays(75);
        $proposal = $this->template($capsa, 'Pre-proposal Defense expert evaluation', DefenseOutcome::PRE_PROPOSAL, ['adviser', 'consultant', 'grammarian'], EvaluationTemplate::STANDARD_QUESTIONS, $set);
        $title = $this->template($capsa, 'Title Defense check-in', DefenseOutcome::TITLE_DEFENSE, ['adviser', 'consultant'], self::TITLE_QUESTIONS, $set);
        $this->template($capsa, 'Grammar and style check', 'Pre Oral Defense', ['grammarian'], self::GRAMMAR_QUESTIONS, $set);

        foreach (self::TEAMS as $i => [$project, $first, $last]) {
            $team = $this->team($i, $project, $first, $last);
            $leader = $team->leader_id;

            // The chosen expert advises the first six and consults for the rest.
            $experts = $i < 6
                ? [['adviser', $me, 'me'], ['consultant', $jomar, 'jomar'], ['grammarian', $ana, 'ana']]
                : [['adviser', $liza, 'liza'], ['consultant', $me, 'me'], ['grammarian', $ana, 'ana']];

            $passedProposal = now()->subDays(60 - $i * 4)->setTime(15, 0);
            $defense = $this->passed($team, DefenseOutcome::PRE_PROPOSAL, $passedProposal, $i === 3 ? DefenseOutcome::PASSED_WITH_REVISION : DefenseOutcome::PASSED);
            foreach ($experts as [$role, $expert, $curve]) {
                $this->evaluate($team, $leader, $defense, $proposal, $expert, $role, $curve, $passedProposal->copy()->addDays(mt_rand(0, 2))->setTime(mt_rand(8, 21), mt_rand(0, 59)));
            }

            if ($i < 6) {
                $passedTitle = now()->subDays(12 - $i * 2)->setTime(16, 0);
                $defense = $this->passed($team, DefenseOutcome::TITLE_DEFENSE, $passedTitle);
                foreach (array_slice($experts, 0, 2) as [$role, $expert, $curve]) {
                    $this->evaluate($team, $leader, $defense, $title, $expert, $role, $curve, $passedTitle->copy()->addDays(mt_rand(0, 1))->setTime(mt_rand(8, 21), mt_rand(0, 59)));
                }
            }
        }

        $this->command?->info(sprintf(
            'Evaluation demo is ready: %d evaluations. CaPSA %s - Expert Evaluations > Results. Expert %s - My Evaluations.',
            ExpertEvaluation::whereIn('team_id', Team::where('team_name', 'like', self::PREFIX.'%')->pluck('team_id'))->count(),
            $capsa->email,
            $me->email,
        ));
    }

    /** Removes every demo team, its students, defenses and evaluations, the demo questions and the demo experts. */
    public static function clean(): void
    {
        DB::transaction(function () {
            $teams = Team::where('team_name', 'like', self::PREFIX.'%')->pluck('team_id');

            ExpertEvaluation::whereIn('team_id', $teams)->delete();
            DB::table('expert_evaluation_reminders')->whereIn('team_id', $teams)->delete();
            DefenseSchedule::whereIn('team_id', $teams)->delete();

            $studentIds = StudentSchoolYear::acrossAllTerms()->whereIn('team_id', $teams)->pluck('student_id');
            StudentSchoolYear::acrossAllTerms()->whereIn('student_id', $studentIds)->delete();
            Student::whereIn('student_id', $studentIds)->where('email', 'like', '%@example.test')->delete();
            Team::whereIn('team_id', $teams)->delete();

            $templates = EvaluationTemplate::withTrashed()->where('title', 'like', self::TEMPLATE_PREFIX.'%')->pluck('id');
            ExpertEvaluation::whereIn('evaluation_template_id', $templates)->delete();
            DB::table('evaluation_template_questions')->whereIn('evaluation_template_id', $templates)->delete();
            EvaluationTemplate::withTrashed()->whereIn('id', $templates)->forceDelete();

            $experts = CapstoneExpert::withTrashed()->where('email', 'like', 'demo.eval.%@example.test')->get();
            foreach ($experts as $expert) {
                $expert->roles()->detach();
                ExpertEvaluation::where('expert_id', $expert->expert_id)->delete();
                $expert->forceDelete();
            }
        });
    }

    private function expert(string $first, string $last, string $role): CapstoneExpert
    {
        $expert = CapstoneExpert::create([
            'email' => 'demo.eval.'.strtolower($first).'@example.test',
            'first_name' => $first,
            'last_name' => $last,
            'highest_degree' => 'master',
            'degree_title' => 'MIT',
            'approved_status' => true,
            'is_admin' => false,
            'first_login_at' => now(),
            'max_teams' => 6,
            'program_slots' => '6 - BSIT',
        ]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);

        return $expert;
    }

    /** @param  array<int, string>  $roles  @param  array<int, string>  $questions */
    private function template(CapstoneExpert $capsa, string $title, string $stage, array $roles, array $questions, $at): EvaluationTemplate
    {
        $template = new EvaluationTemplate([
            'capsa_id' => $capsa->expert_id,
            'title' => self::TEMPLATE_PREFIX.$title,
            'defense_type' => $stage,
            'roles' => $roles,
            'is_active' => true,
        ]);
        $template->created_at = $at;
        $template->updated_at = $at;
        $template->save();

        foreach ($questions as $i => $question) {
            $template->questions()->create(['question' => $question, 'sort_order' => $i]);
        }

        return $template->load('questions');
    }

    private function team(int $i, string $project, string $first, string $last): Team
    {
        $members = collect([[$first, $last], ['Member', 'Two'], ['Member', 'Three']])->map(fn ($n, $k) => Student::create([
            'email' => 'demo.eval.team'.($i + 1).'.'.$k.'@example.test',
            'f_name' => $n[0],
            'l_name' => $k === 0 ? $n[1] : $n[1].' '.($i + 1),
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · B',
            'student_number' => null,
        ]));

        $team = Team::create([
            'team_name' => self::PREFIX.sprintf('%02d', $i + 1),
            'project_title' => $project,
            'team_status' => 'Active',
            'is_confirmed' => true,
            'leader_id' => $members->first()->student_id,
            'member_count' => $members->count(),
        ]);

        foreach ($members as $student) {
            StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);
        }

        return $team;
    }

    private function passed(Team $team, string $stage, $at, string $outcome = DefenseOutcome::PASSED): DefenseSchedule
    {
        return DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => $stage,
            'attempt' => 1,
            'schedule_date' => $at->copy()->setTime(9, 0),
            'end_date' => $at->copy()->setTime(10, 0),
            'venue' => 'Mac Lab',
            'outcome' => $outcome,
            'outcome_recorded_at' => $at,
        ]);
    }

    /** The leader's answers, each a 2 to 5 drawn on the expert's curve, and their average as the rating. */
    private function evaluate(Team $team, int $leaderId, DefenseSchedule $defense, EvaluationTemplate $template, CapstoneExpert $expert, string $role, string $curve, $at): void
    {
        [$five, $four, $three] = self::CURVES[$curve];
        $answers = $template->questions->map(function ($question) use ($five, $four, $three) {
            $roll = mt_rand() / mt_getrandmax();
            $score = match (true) {
                $roll < $five => 5,
                $roll < $five + $four => 4,
                $roll < $five + $four + $three => 3,
                default => 2,
            };

            return ['question' => $question->question, 'score' => $score];
        })->all();

        $evaluation = new ExpertEvaluation([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'defense_id' => $defense->defense_id,
            'evaluation_template_id' => $template->id,
            'role' => $role,
            'answers' => $answers,
            'rating' => round(collect($answers)->avg('score'), 2),
            'student_id' => $leaderId,
        ]);
        $evaluation->created_at = $at;
        $evaluation->updated_at = $at;
        $evaluation->save();
    }
}
