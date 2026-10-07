<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\EvaluationTemplate;
use App\Models\ExpertEvaluation;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\DefenseOutcome;
use App\Support\ExpertEvaluations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The CaPSA's side of expert evaluations: the questions per defense - the
 * five standard ones in a click, or their own - the popup when a defense is
 * scheduled with none, and the anonymous results the CaPSA and the expert
 * read.
 */
class EvaluationTemplateTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private Team $team;

    private Student $leader;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2027-02-01 09:00:00');

        $this->capsa = $this->capsaFor();
        [$this->team, $this->leader] = $this->teamInSection('Alpha Squad');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function engage(string $roleName): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $role = Role::firstOrCreate(['role_name' => $roleName]);
        $expert->roles()->attach($role->role_id);
        TeamExpertAssignment::create([
            'team_id' => $this->team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
        ]);

        return $expert;
    }

    private function schedule(string $stage = DefenseOutcome::TITLE_DEFENSE, string $when = '2027-02-12 08:00:00'): DefenseSchedule
    {
        return DefenseSchedule::create(['team_id' => $this->team->team_id, 'defense_type' => $stage, 'attempt' => 1, 'schedule_date' => $when]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function create(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->capsa, 'expert')->post(route('capsa.evaluations.store'), $overrides + [
            'title' => 'Title Defense check-in',
            'defense_type' => DefenseOutcome::TITLE_DEFENSE,
            'roles' => ['adviser', 'grammarian'],
            'questions' => ['Answered us quickly.', '', 'Read our chapters closely.'],
        ]);
    }

    /**
     * Nothing is laid out per defense up front, and there is one way in:
     * Create new evaluation, where the CaPSA picks the defense.
     */
    public function test_with_no_evaluations_the_page_points_to_create_new_evaluation(): void
    {
        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations'))
            ->assertOk()
            ->assertSee('No evaluations yet')
            ->assertSee('href="'.route('capsa.evaluations.create').'"', false)
            ->assertDontSee('Pick a defense')
            ->assertDontSee('name="defense_type"', false)
            ->assertDontSee('<span class="ev-stage-tag">', false);

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations.create'))
            ->assertOk()
            ->assertSee('name="defense_type"', false)
            ->assertSeeInOrder(EvaluationTemplate::STAGES)
            ->assertSee('Use the 5 standard questions');
    }

    public function test_only_the_evaluations_the_capsa_made_are_listed(): void
    {
        $this->create();

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations'))
            ->assertOk()
            ->assertDontSee('No evaluations yet')
            ->assertSee('<span class="ev-stage-tag">Title Defense</span>', false)
            ->assertDontSee('<span class="ev-stage-tag">Pre-proposal Defense</span>', false)
            ->assertSee('Title Defense check-in')
            ->assertDontSee('Pick a defense');
    }

    public function test_the_standard_questions_need_a_defense_picked(): void
    {
        $this->actingAs($this->capsa, 'expert')->post(route('capsa.evaluations.store'), ['standard' => 1])
            ->assertSessionHasErrors('defense_type');

        $this->assertSame(0, EvaluationTemplate::count());
    }

    public function test_one_click_sets_up_the_five_standard_questions_for_every_role(): void
    {
        $this->actingAs($this->capsa, 'expert')
            ->post(route('capsa.evaluations.store'), ['standard' => 1, 'defense_type' => DefenseOutcome::PRE_PROPOSAL])
            ->assertRedirect(route('capsa.evaluations'))
            ->assertSessionHas('evaluation_status');

        $template = EvaluationTemplate::with('questions')->sole();
        $this->assertSame($this->capsa->expert_id, $template->capsa_id);
        $this->assertSame(DefenseOutcome::PRE_PROPOSAL, $template->defense_type);
        $this->assertSame(['adviser', 'consultant', 'grammarian'], $template->roles);
        $this->assertSame(EvaluationTemplate::STANDARD_QUESTIONS, $template->questions->pluck('question')->all());

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations'))
            ->assertSee('Pre-proposal Defense expert evaluation')
            ->assertSee('<dt>Questions</dt><dd>5</dd>', false)
            ->assertSee('<dt>Answered</dt><dd>0</dd>', false)
            // The browser's own confirm box is never used.
            ->assertDontSee('window.confirm(', false)
            ->assertSee('data-confirm-ok="Turn off"', false);
    }

    public function test_a_new_evaluation_takes_the_roles_picked_and_its_own_questions(): void
    {
        $this->create()->assertRedirect(route('capsa.evaluations'));

        $template = EvaluationTemplate::with('questions')->sole();
        $this->assertSame(['adviser', 'grammarian'], $template->roles);
        $this->assertSame(['Answered us quickly.', 'Read our chapters closely.'], $template->questions->pluck('question')->all());
    }

    public function test_an_evaluation_needs_a_role_a_question_and_a_real_defense(): void
    {
        $this->create(['roles' => []])->assertSessionHasErrors('roles');
        $this->create(['roles' => ['panelist']])->assertSessionHasErrors('roles.0');
        $this->create(['questions' => ['', '  ']])->assertSessionHasErrors('questions');
        $this->create(['questions' => array_map(fn ($n) => "Question {$n}", range(1, 21))])->assertSessionHasErrors('questions');
        $this->create(['questions' => ['Same.', 'Same.']])->assertSessionHasErrors('questions.1');
        $this->create(['defense_type' => 'Coffee Break'])->assertSessionHasErrors('defense_type');
        $this->create(['title' => ''])->assertSessionHasErrors('title');

        $this->assertSame(0, EvaluationTemplate::count());
    }

    public function test_a_role_is_evaluated_once_per_defense(): void
    {
        $this->create();

        $this->create(['title' => 'Again', 'roles' => ['grammarian', 'consultant']])
            ->assertSessionHasErrors(['roles' => 'Grammarian already has an evaluation for Title Defense: "Title Defense check-in". Edit that one, or turn it off first.']);

        $this->create(['title' => 'For the consultant', 'roles' => ['consultant']])->assertSessionHasNoErrors();
        $this->create(['title' => 'Another defense', 'defense_type' => DefenseOutcome::PRE_PROPOSAL])->assertSessionHasNoErrors();

        $this->assertSame(3, EvaluationTemplate::count());
    }

    public function test_editing_replaces_the_questions_and_keeps_the_defense(): void
    {
        $this->create();
        $template = EvaluationTemplate::sole();

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations.edit', $template))
            ->assertOk()
            ->assertSee('Edit evaluation')
            ->assertSee('Read our chapters closely.');

        $this->actingAs($this->capsa, 'expert')->put(route('capsa.evaluations.update', $template), [
            'title' => 'Renamed',
            'defense_type' => 'Final Oral Defense',
            'roles' => ['consultant'],
            'questions' => ['Only one now.'],
        ])->assertRedirect(route('capsa.evaluations'));

        $template->refresh()->load('questions');
        $this->assertSame('Renamed', $template->title);
        $this->assertSame(DefenseOutcome::TITLE_DEFENSE, $template->defense_type);
        $this->assertSame(['consultant'], $template->roles);
        $this->assertSame(['Only one now.'], $template->questions->pluck('question')->all());
    }

    public function test_another_capsas_evaluation_cannot_be_touched(): void
    {
        $theirs = EvaluationTemplate::factory()->withQuestions()->create(['capsa_id' => $this->capsaFor(['BSCS-3A'])->expert_id]);

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations.edit', $theirs))->assertNotFound();
        $this->actingAs($this->capsa, 'expert')->patch(route('capsa.evaluations.turn_off', $theirs))->assertNotFound();
        $theirs->update(['is_active' => false]);
        $this->actingAs($this->capsa, 'expert')->delete(route('capsa.evaluations.destroy', $theirs))->assertNotFound();
        $this->assertNotNull($theirs->fresh());
    }

    public function test_turning_an_evaluation_off_stops_it_being_owed_and_offers_it_again(): void
    {
        $this->engage('adviser');
        $this->create(['defense_type' => DefenseOutcome::PRE_PROPOSAL]);
        $template = EvaluationTemplate::sole();
        Carbon::setTestNow(now()->addDay());
        DefenseSchedule::create(['team_id' => $this->team->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'attempt' => 1, 'schedule_date' => now(), 'outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now()]);
        $this->assertCount(1, app(ExpertEvaluations::class)->due($this->team));

        $this->actingAs($this->capsa, 'expert')->patch(route('capsa.evaluations.turn_off', $template))
            ->assertRedirect(route('capsa.evaluations'));

        $this->assertFalse($template->fresh()->is_active);
        $this->assertCount(0, app(ExpertEvaluations::class)->due($this->team));
        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations'))
            ->assertSee('Turned off')
            ->assertSee(route('capsa.evaluations.create', ['from' => $template->id]), false)
            ->assertSee('action="'.route('capsa.evaluations.destroy', $template).'"', false);
        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations.create', ['from' => $template->id]))
            ->assertOk()
            ->assertSee('Read our chapters closely.');
    }

    public function test_an_evaluation_must_be_turned_off_before_it_is_deleted(): void
    {
        $this->create();
        $template = EvaluationTemplate::sole();

        $this->actingAs($this->capsa, 'expert')->delete(route('capsa.evaluations.destroy', $template))
            ->assertRedirect(route('capsa.evaluations'))
            ->assertSessionHasErrors('evaluation');

        $this->assertNotNull($template->fresh());
    }

    public function test_deleting_an_unanswered_evaluation_erases_it(): void
    {
        $this->create();
        $template = EvaluationTemplate::sole();
        $template->update(['is_active' => false]);

        $this->actingAs($this->capsa, 'expert')->delete(route('capsa.evaluations.destroy', $template))
            ->assertRedirect(route('capsa.evaluations'))
            ->assertSessionHas('evaluation_status', 'Deleted "Title Defense check-in".');

        $this->assertSame(0, EvaluationTemplate::withTrashed()->count());
        $this->assertDatabaseCount('evaluation_template_questions', 0);
        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations'))
            ->assertDontSee('<b>Title Defense check-in</b>', false)
            ->assertSee('No evaluations yet');
    }

    /** What leaders answered still counts after the evaluation is deleted. */
    public function test_deleting_an_answered_evaluation_keeps_its_answers_in_the_results(): void
    {
        $adviser = $this->engage('adviser');
        $template = EvaluationTemplate::factory()->inactive()->withQuestions(['Was there for us.'])
            ->create(['capsa_id' => $this->capsa->expert_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'title' => 'Old one']);
        ExpertEvaluation::create([
            'team_id' => $this->team->team_id, 'expert_id' => $adviser->expert_id, 'evaluation_template_id' => $template->id,
            'role' => 'adviser', 'student_id' => $this->leader->student_id, 'rating' => 4,
            'answers' => [['question' => 'Was there for us.', 'score' => 4]],
        ]);

        $this->actingAs($this->capsa, 'expert')->delete(route('capsa.evaluations.destroy', $template))
            ->assertSessionHas('evaluation_status', 'Deleted "Old one". Its 1 answer stays in the results.');

        $this->assertSoftDeleted($template);
        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations'))->assertDontSee('<span>Old one ·', false);
        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations', ['tab' => 'results']))
            ->assertSee($adviser->name)
            ->assertSee('Was there for us.');
    }

    public function test_a_scheduled_defense_without_an_evaluation_asks_the_capsa_for_one(): void
    {
        $this->schedule();

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.dashboard'))
            ->assertOk()
            ->assertSee('Expert evaluation required')
            ->assertSee('1 team · from Feb 12, 2027');

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations'))
            ->assertSee('Title Defense needs an evaluation')
            ->assertSee('1 team defends')
            // not on top of the page that sets it up
            ->assertDontSee('Expert evaluation required');

        $this->create(['roles' => ['adviser']]);

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.dashboard'))
            ->assertOk()
            ->assertDontSee('Expert evaluation required');
    }

    public function test_only_upcoming_defenses_in_the_capsas_own_sections_ask(): void
    {
        $this->schedule(when: '2027-01-20 08:00:00');
        $this->assertCount(0, app(ExpertEvaluations::class)->upcomingWithoutEvaluation($this->capsa));

        [$other] = $this->teamInSection('Elsewhere', 'Bachelor of Science in Computer Science');
        DefenseSchedule::create(['team_id' => $other->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => 1, 'schedule_date' => '2027-02-15 08:00:00']);

        $this->assertCount(0, app(ExpertEvaluations::class)->upcomingWithoutEvaluation($this->capsa));
    }

    public function test_results_are_anonymous_with_the_average_and_the_count(): void
    {
        $adviser = $this->engage('adviser');
        $template = EvaluationTemplate::factory()->withQuestions(['Was there for us.', 'Knew the field.'])
            ->create(['capsa_id' => $this->capsa->expert_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE]);
        $defense = DefenseSchedule::create(['team_id' => $this->team->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => 1, 'schedule_date' => now(), 'outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now()]);
        foreach ([[5, 4], [3, 3]] as [$a, $b]) {
            ExpertEvaluation::create([
                'team_id' => $this->team->team_id, 'expert_id' => $adviser->expert_id, 'defense_id' => $defense->defense_id,
                'evaluation_template_id' => $template->id, 'role' => 'adviser', 'student_id' => $this->leader->student_id,
                'answers' => [['question' => 'Was there for us.', 'score' => $a], ['question' => 'Knew the field.', 'score' => $b]],
                'rating' => ($a + $b) / 2,
            ]);
            $defense = DefenseSchedule::create(['team_id' => $this->team->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => $defense->attempt + 1, 'schedule_date' => now()]);
        }

        $page = $this->actingAs($this->capsa, 'expert')->get(route('capsa.evaluations', ['tab' => 'results']))
            ->assertOk()
            ->assertSee($adviser->name)
            ->assertSee('3.8<small>/5</small></b>2 reviews', false)
            ->assertSee('Anonymous team leader')
            ->assertSee('Was there for us.')
            ->assertDontSee('Alpha Squad')
            ->assertDontSee($this->leader->email);
        $this->assertStringNotContainsString($this->leader->l_name, $page->getContent());

        $this->actingAs($adviser, 'expert')->get(route('instructor.evaluations'))
            ->assertOk()
            ->assertSee('My Evaluations')
            ->assertSee('3.8<small>/5</small>', false)
            ->assertSee('2 reviews')
            ->assertSee('Anonymous team leader')
            ->assertSee('Knew the field.')
            ->assertDontSee('Alpha Squad')
            ->assertDontSee($this->leader->email);
    }

    public function test_an_expert_sees_only_their_own_evaluations(): void
    {
        $adviser = $this->engage('adviser');
        $other = $this->engage('consultant');
        ExpertEvaluation::create([
            'team_id' => $this->team->team_id, 'expert_id' => $other->expert_id, 'role' => 'consultant',
            'student_id' => $this->leader->student_id, 'rating' => 2, 'answers' => [['question' => 'Showed up.', 'score' => 2]],
        ]);

        $this->actingAs($adviser, 'expert')->get(route('instructor.evaluations'))
            ->assertOk()
            ->assertSee('No evaluations yet')
            ->assertDontSee('Showed up.');
    }

    public function test_an_expert_who_is_not_a_capsa_cannot_open_the_capsa_page(): void
    {
        $adviser = $this->engage('adviser');

        $this->actingAs($adviser, 'expert')->get(route('capsa.evaluations'))->assertForbidden();
        $this->actingAs($adviser, 'expert')->post(route('capsa.evaluations.store'), ['standard' => 1, 'defense_type' => DefenseOutcome::TITLE_DEFENSE])->assertForbidden();

        $this->assertSame(0, EvaluationTemplate::count());
    }
}
