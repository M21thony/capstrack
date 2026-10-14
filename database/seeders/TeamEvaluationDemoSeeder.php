<?php

namespace Database\Seeders;

use App\Models\DefenseSchedule;
use App\Models\EvaluationTemplate;
use App\Models\ExpertEvaluation;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use App\Support\ExpertEvaluations;
use App\Support\SectionScope;
use App\Support\TeamChecklist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Puts a real team's leader in front of the expert evaluation, for trying
 * the student side: the popup, the bell, the Evaluations tab's questions and
 * the next phase waiting on it.
 *
 * The chosen student becomes the leader of their team (only a leader
 * evaluates), the team passes a Pre-proposal Defense yesterday, and the
 * CaPSA of its section has an evaluation for that defense from before it -
 * the one already there, or a demo one. Nothing the team had is changed but
 * its leader, and clean() puts that back.
 *
 *   php artisan db:seed --class=TeamEvaluationDemoSeeder
 *   EVAL_DEMO_TEAM="Some Team" EVAL_DEMO_LEADER=someone@my.cspc.edu.ph php artisan db:seed --class=TeamEvaluationDemoSeeder
 *
 * To take it all out again:
 *
 *   php artisan tinker --execute "Database\Seeders\TeamEvaluationDemoSeeder::clean();"
 */
class TeamEvaluationDemoSeeder extends Seeder
{
    private const TEAM = 'Aptenodytes Fosteri';

    private const LEADER = 'joaurillas@my.cspc.edu.ph';

    /** Where the demo remembers what it changed, so clean() can undo exactly that. */
    private const STATE = 'demo.team_evaluation';

    public function run(): void
    {
        self::clean();

        $team = Team::where('team_name', env('EVAL_DEMO_TEAM', self::TEAM))->first();
        $student = Student::where('email', env('EVAL_DEMO_LEADER', self::LEADER))->first();

        if (! $team || ! $student) {
            $this->command?->error(! $team ? 'No team named '.env('EVAL_DEMO_TEAM', self::TEAM).'.' : 'No student '.env('EVAL_DEMO_LEADER', self::LEADER).'.');

            return;
        }

        if (! StudentSchoolYear::where('team_id', $team->team_id)->where('student_id', $student->student_id)->exists()) {
            $this->command?->error($student->email.' is not on '.$team->team_name.' this term.');

            return;
        }

        $capsa = app(TeamChecklist::class)->capsaFor(app(SectionScope::class)->sectionFor($team));

        if (! $capsa) {
            $this->command?->error($team->team_name.' has no CaPSA for its section, so there is no one to set the questions.');

            return;
        }

        $state = ['team_id' => $team->team_id, 'leader_id' => $team->leader_id];

        DB::transaction(function () use ($team, $student, $capsa, &$state) {
            $team->update(['leader_id' => $student->student_id]);

            $decided = now()->subDay()->setTime(15, 0);
            $defense = DefenseSchedule::create([
                'team_id' => $team->team_id,
                'defense_type' => DefenseOutcome::PRE_PROPOSAL,
                'attempt' => (int) DefenseSchedule::where('team_id', $team->team_id)->where('defense_type', DefenseOutcome::PRE_PROPOSAL)->max('attempt') + 1,
                'schedule_date' => $decided->copy()->setTime(9, 0),
                'end_date' => $decided->copy()->setTime(10, 0),
                'venue' => 'Mac Lab',
                'outcome' => DefenseOutcome::PASSED,
                'outcome_recorded_at' => $decided,
            ]);
            $state['defense_id'] = $defense->defense_id;

            // The CaPSA's own evaluation for the defense if one was there
            // before it; a demo one otherwise.
            $existing = EvaluationTemplate::active()->where('capsa_id', $capsa->expert_id)
                ->where('defense_type', DefenseOutcome::PRE_PROPOSAL)->whereHas('questions')
                ->where('created_at', '<=', $decided)->exists();

            if (! $existing) {
                $template = new EvaluationTemplate([
                    'capsa_id' => $capsa->expert_id,
                    'title' => 'Demo team evaluation: Pre-proposal Defense',
                    'defense_type' => DefenseOutcome::PRE_PROPOSAL,
                    'roles' => array_keys(ExpertEvaluation::ROLES),
                    'is_active' => true,
                ]);
                $template->created_at = $decided->copy()->subDays(3);
                $template->save();
                foreach (EvaluationTemplate::STANDARD_QUESTIONS as $i => $question) {
                    $template->questions()->create(['question' => $question, 'sort_order' => $i]);
                }
                $state['template_id'] = $template->id;
            }
        });

        Setting::set(self::STATE, json_encode($state));

        $owed = app(ExpertEvaluations::class)->due($team->fresh());

        if ($owed->isEmpty()) {
            $this->command?->warn('Done, but nothing is owed: '.$team->team_name.' has no approved adviser, consultant or grammarian.');

            return;
        }

        $this->command?->info(sprintf(
            '%s now leads %s, which passed its Pre-proposal Defense yesterday. Sign in as them and open Experts > Evaluations: %s.',
            $student->email,
            $team->team_name,
            $owed->map(fn ($row) => $row->expert.' ('.$row->role_label.')')->implode(', '),
        ));
    }

    /** Puts the team's leader back and removes the demo defense, its evaluations and the demo questions. */
    public static function clean(): void
    {
        $state = json_decode((string) Setting::get(self::STATE), true);

        if (! is_array($state)) {
            return;
        }

        DB::transaction(function () use ($state) {
            if (! empty($state['team_id']) && array_key_exists('leader_id', $state)) {
                Team::where('team_id', $state['team_id'])->update(['leader_id' => $state['leader_id']]);
            }

            if (! empty($state['defense_id'])) {
                ExpertEvaluation::where('defense_id', $state['defense_id'])->delete();
                DB::table('expert_evaluation_reminders')->where('defense_id', $state['defense_id'])->delete();
                DefenseSchedule::where('defense_id', $state['defense_id'])->delete();
            }

            if (! empty($state['template_id'])) {
                ExpertEvaluation::where('evaluation_template_id', $state['template_id'])->delete();
                DB::table('evaluation_template_questions')->where('evaluation_template_id', $state['template_id'])->delete();
                EvaluationTemplate::withTrashed()->whereKey($state['template_id'])->forceDelete();
            }

            Setting::where('key', self::STATE)->delete();
        });
    }
}
