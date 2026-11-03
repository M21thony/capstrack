<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\DefenseSchedule;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\EvaluationTemplate;
use App\Models\ExpertEvaluation;
use App\Models\ExpertRole;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\DefenseOutcome;
use App\Support\ExpertEvaluations;
use App\Support\MilestoneProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * After each defense a team passes - Pre-proposal, Title, Pre Oral, Final -
 * the leader answers the CaPSA's evaluation of each expert it covers, 1 to 5
 * stars per question; the rating is the average. The leader is told by bell
 * and email, nudged by a popup, and the next phase waits for it.
 */
class ExpertEvaluationTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    private Student $leader;

    private Checklist $checklist;

    private ChecklistMilestone $proposal;

    private ChecklistMilestone $chapters;

    private CapstoneExpert $capsa;

    /** @var array<string, CapstoneExpert> */
    private array $experts = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2027-02-01 09:00:00');

        $capsa = $this->capsa = $this->capsaFor();
        [$this->team, $this->leader] = $this->teamInSection('Alpha');

        $this->checklist = Checklist::create(['capsa_id' => $capsa->expert_id, 'program' => 'BSIT', 'title' => 'Capstone Checklist']);
        $this->proposal = $this->checklist->milestones()->create(['name' => 'Pre-proposal', 'position' => 0, 'opened_at' => now()]);
        $this->proposal->deliverables()->create(['title' => 'Pre-Proposal Oral Presentation', 'kind' => Deliverable::DEFENSE, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'position' => 0]);
        $this->chapters = $this->checklist->milestones()->create(['name' => 'Chapters', 'position' => 1, 'requires_previous' => true, 'opened_at' => now()]);
        $this->chapters->deliverables()->create(['title' => 'Chapter 1 Form', 'kind' => Deliverable::FORM, 'position' => 0]);

        foreach (['adviser', 'consultant', 'grammarian'] as $roleName) {
            $this->experts[$roleName] = $this->engage($roleName);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function engage(string $roleName, string $status = 'approved'): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $role = Role::firstOrCreate(['role_name' => $roleName]);
        $expert->roles()->attach($role->role_id);
        TeamExpertAssignment::create([
            'team_id' => $this->team->team_id, 'expert_id' => $expert->expert_id, 'status' => $status,
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
        ]);

        return $expert;
    }

    /** @param  array<int, string>|null  $roles */
    private function template(string $stage = DefenseOutcome::PRE_PROPOSAL, ?array $roles = null, ?CapstoneExpert $capsa = null, ?array $questions = null): EvaluationTemplate
    {
        return EvaluationTemplate::factory()->withQuestions($questions)->create([
            'capsa_id' => ($capsa ?? $this->capsa)->expert_id,
            'defense_type' => $stage,
            'roles' => $roles ?? array_keys(ExpertEvaluation::ROLES),
        ])->load('questions');
    }

    private function decide(string $stage = DefenseOutcome::PRE_PROPOSAL, string $outcome = DefenseOutcome::PASSED, int $attempt = 1): DefenseSchedule
    {
        Carbon::setTestNow(now()->addDay());

        return DefenseSchedule::create([
            'team_id' => $this->team->team_id,
            'defense_type' => $stage,
            'attempt' => $attempt,
            'schedule_date' => now(),
            'outcome' => $outcome,
            'outcome_recorded_at' => now(),
        ]);
    }

    private function member(): Student
    {
        $member = Student::factory()->withCompleteProfile()->create(['email' => 'member.'.uniqid().'@my.cspc.edu.ph']);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $this->team->team_id]);

        return $member;
    }

    private function due(): Collection
    {
        return app(ExpertEvaluations::class)->due($this->team->fresh());
    }

    /** @param  array<int, int>|null  $scores  one per question, in order; 5 each by default */
    private function answer(Student $as, DefenseSchedule $defense, CapstoneExpert $expert, string $role, EvaluationTemplate $template, ?array $scores = null, array $overrides = []): TestResponse
    {
        $answers = [];
        foreach ($template->questions->values() as $i => $question) {
            $answers[$question->id] = $scores[$i] ?? 5;
        }

        return $this->actingAs($as, 'web')->post(route('student.evaluations.store'), $overrides + [
            'defense_id' => $defense->defense_id,
            'expert_id' => $expert->expert_id,
            'role' => $role,
            'answers' => $answers,
        ]);
    }

    private function answerAll(DefenseSchedule $defense, EvaluationTemplate $template): void
    {
        foreach ($this->experts as $role => $expert) {
            $this->answer($this->leader, $defense, $expert, $role, $template)->assertSessionHas('evaluation_saved');
        }
    }

    public function test_nothing_is_owed_until_a_defense_is_passed(): void
    {
        $this->template();
        DefenseSchedule::create(['team_id' => $this->team->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'attempt' => 1, 'schedule_date' => now()->addWeek()]);
        $this->assertCount(0, $this->due());

        $this->decide(outcome: DefenseOutcome::REDEFENSE, attempt: 2);
        $this->assertCount(0, $this->due(), 'a re-defense is not a pass');
    }

    public function test_a_passed_defense_owes_one_evaluation_per_expert_the_capsas_evaluation_covers(): void
    {
        $this->template(roles: ['adviser', 'consultant']);
        $pending = $this->engage('adviser', 'pending');
        $defense = $this->decide(outcome: DefenseOutcome::PASSED_WITH_REVISION);

        $due = $this->due();

        $this->assertSame(['adviser', 'consultant'], $due->pluck('role')->all());
        $this->assertSame([$defense->defense_id], $due->pluck('defense_id')->unique()->values()->all());
        $this->assertSame(DefenseOutcome::PRE_PROPOSAL, $due->first()->stage);
        $this->assertNotContains($pending->expert_id, $due->pluck('expert_id'));
    }

    public function test_only_the_teams_capsas_active_evaluation_for_that_defense_counts(): void
    {
        $this->template(stage: DefenseOutcome::TITLE_DEFENSE);
        $this->template(capsa: $this->capsaFor(['BSCS-3A']));
        EvaluationTemplate::factory()->inactive()->withQuestions()->create(['capsa_id' => $this->capsa->expert_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL]);
        $this->decide();

        $this->assertCount(0, $this->due());
    }

    /** Setting up an evaluation never reaches back and locks a team long past that defense. */
    public function test_an_evaluation_set_up_after_the_defense_was_decided_owes_nothing(): void
    {
        $this->decide();
        Carbon::setTestNow(now()->addDay());
        $this->template();

        $this->assertCount(0, $this->due());
    }

    public function test_the_rating_is_the_average_of_the_answers(): void
    {
        $template = $this->template();
        $defense = $this->decide();

        $this->answer($this->leader, $defense, $this->experts['adviser'], 'adviser', $template, [5, 4, 4, 3, 5])
            ->assertRedirect(route('student.experts', ['tab' => 'evaluations']))
            ->assertSessionHas('evaluation_saved');

        $evaluation = ExpertEvaluation::sole();
        $this->assertSame(4.2, $evaluation->rating);
        $this->assertSame($defense->defense_id, $evaluation->defense_id);
        $this->assertSame($template->id, $evaluation->evaluation_template_id);
        $this->assertCount(5, $evaluation->answers);
        $this->assertSame(['question' => EvaluationTemplate::STANDARD_QUESTIONS[3], 'score' => 3], $evaluation->answers[3]);
        $this->assertNull($evaluation->comment);
        $this->assertSame(['consultant', 'grammarian'], $this->due()->pluck('role')->all());
    }

    public function test_all_fives_rate_the_expert_five(): void
    {
        $template = $this->template();
        $defense = $this->decide();

        $this->answer($this->leader, $defense, $this->experts['consultant'], 'consultant', $template);

        $this->assertSame(5.0, ExpertEvaluation::sole()->rating);
    }

    public function test_the_wording_answered_is_kept_when_the_capsa_edits_the_questions(): void
    {
        $template = $this->template();
        $defense = $this->decide();
        $this->answer($this->leader, $defense, $this->experts['adviser'], 'adviser', $template);

        $template->questions()->first()->update(['question' => 'Something else entirely.']);

        $this->assertSame(EvaluationTemplate::STANDARD_QUESTIONS[0], ExpertEvaluation::sole()->answers[0]['question']);
    }

    public function test_every_question_needs_an_answer_from_one_to_five(): void
    {
        $template = $this->template();
        $defense = $this->decide();
        $adviser = $this->experts['adviser'];
        $questions = $template->questions->pluck('id');

        // One left out.
        $this->actingAs($this->leader, 'web')->post(route('student.evaluations.store'), [
            'defense_id' => $defense->defense_id, 'expert_id' => $adviser->expert_id, 'role' => 'adviser',
            'answers' => $questions->take(4)->mapWithKeys(fn ($id) => [$id => 4])->all(),
        ])->assertSessionHas('evaluation_error', 'Answer every question from 1 to 5.');

        $this->answer($this->leader, $defense, $adviser, 'adviser', $template, [6])->assertSessionHasErrors('answers.'.$questions[0]);
        $this->answer($this->leader, $defense, $adviser, 'adviser', $template, [0])->assertSessionHasErrors('answers.'.$questions[0]);
        $this->answer($this->leader, $defense, $adviser, 'adviser', $template, overrides: ['answers' => []])->assertSessionHasErrors('answers');
        $this->answer($this->leader, $defense, $adviser, 'panelist', $template)->assertSessionHasErrors('role');

        $this->assertSame(0, ExpertEvaluation::count());
    }

    public function test_the_same_evaluation_cannot_be_sent_twice(): void
    {
        $template = $this->template();
        $defense = $this->decide();

        $this->answer($this->leader, $defense, $this->experts['adviser'], 'adviser', $template);
        $this->answer($this->leader, $defense, $this->experts['adviser'], 'adviser', $template)->assertSessionHas('evaluation_error');

        $this->assertSame(1, ExpertEvaluation::count());
    }

    public function test_a_member_cannot_evaluate(): void
    {
        $template = $this->template();
        $defense = $this->decide();

        $this->answer($this->member(), $defense, $this->experts['adviser'], 'adviser', $template)
            ->assertSessionHas('evaluation_error', 'Only your team leader evaluates the experts.');

        $this->assertSame(0, ExpertEvaluation::count());
    }

    public function test_the_leader_sends_every_expert_of_a_defense_at_once(): void
    {
        $template = $this->template();
        $defense = $this->decide();

        $this->actingAs($this->leader, 'web')->post(route('student.evaluations.store_all'), $this->sheet($defense, $template, ['adviser' => [5, 4, 4, 3, 5], 'consultant' => [], 'grammarian' => []]))
            ->assertRedirect(route('student.experts', ['tab' => 'evaluations']))
            ->assertSessionHas('evaluation_saved', '3 evaluations sent. Thank you!');

        $this->assertSame(3, ExpertEvaluation::count());
        $this->assertSame(4.2, ExpertEvaluation::where('role', 'adviser')->sole()->rating);
        $this->assertCount(0, $this->due());
    }

    public function test_one_refused_in_the_sheet_does_not_hold_back_the_others(): void
    {
        $template = $this->template();
        $defense = $this->decide();
        $this->answer($this->leader, $defense, $this->experts['adviser'], 'adviser', $template);

        $this->actingAs($this->leader, 'web')->post(route('student.evaluations.store_all'), $this->sheet($defense, $template, ['adviser' => [], 'consultant' => []]))
            ->assertSessionHas('evaluation_error', fn ($m) => str_starts_with($m, '1 sent. '));

        $this->assertSame(2, ExpertEvaluation::count());
        $this->assertSame(['grammarian'], $this->due()->pluck('role')->all());
    }

    public function test_a_member_cannot_send_the_sheet_either(): void
    {
        $template = $this->template();
        $defense = $this->decide();

        $this->actingAs($this->member(), 'web')->post(route('student.evaluations.store_all'), $this->sheet($defense, $template, ['adviser' => []]))
            ->assertSessionHas('evaluation_error', 'Only your team leader evaluates the experts.');
        $this->actingAs($this->leader, 'web')->post(route('student.evaluations.store_all'), ['defense_id' => $defense->defense_id, 'evaluations' => [
            'x' => ['expert_id' => $this->experts['adviser']->expert_id, 'role' => 'adviser', 'answers' => [$template->questions[0]->id => 6]],
        ]])->assertSessionHasErrors('evaluations.x.answers.'.$template->questions[0]->id);

        $this->assertSame(0, ExpertEvaluation::count());
    }

    /** @param  array<string, array<int, int>>  $byRole  scores per role, 5 where left out */
    private function sheet(DefenseSchedule $defense, EvaluationTemplate $template, array $byRole): array
    {
        $evaluations = [];
        foreach ($byRole as $role => $scores) {
            $expert = $this->experts[$role];
            $answers = [];
            foreach ($template->questions->values() as $i => $question) {
                $answers[$question->id] = $scores[$i] ?? 5;
            }
            $evaluations[$expert->expert_id.'-'.$role] = ['expert_id' => $expert->expert_id, 'role' => $role, 'answers' => $answers];
        }

        return ['defense_id' => $defense->defense_id, 'evaluations' => $evaluations];
    }

    public function test_a_defense_not_passed_cannot_be_evaluated(): void
    {
        $template = $this->template();
        $defense = $this->decide(outcome: DefenseOutcome::REDEFENSE);

        $this->answer($this->leader, $defense, $this->experts['adviser'], 'adviser', $template)->assertSessionHas('evaluation_error');

        $this->assertSame(0, ExpertEvaluation::count());
    }

    /** The CaPSA edits the questions after the defense: the leader yet to answer is asked the new ones. */
    public function test_a_capsas_edit_reaches_the_leader_who_has_not_answered_yet(): void
    {
        $template = $this->template();
        $defense = $this->decide();

        $this->actingAs($this->capsa, 'expert')->put(route('capsa.evaluations.update', $template), [
            'title' => 'Renamed',
            'roles' => ['adviser', 'consultant', 'grammarian'],
            'questions' => ['Did the expert reply within two days?', 'Would you work with this expert again?'],
        ])->assertRedirect(route('capsa.evaluations'));

        $this->actingAs($this->leader, 'web')->get(route('student.experts', ['tab' => 'evaluations']))
            ->assertOk()
            ->assertSee('Did the expert reply within two days?')
            ->assertSee('Would you work with this expert again?')
            ->assertDontSee(EvaluationTemplate::STANDARD_QUESTIONS[0]);

        $this->answer($this->leader, $defense, $this->experts['adviser'], 'adviser', $template->fresh('questions'), [5, 3])
            ->assertSessionHas('evaluation_saved');
        $this->assertSame(4.0, ExpertEvaluation::sole()->rating);
        $this->assertSame('Did the expert reply within two days?', ExpertEvaluation::sole()->answers[0]['question']);
    }

    public function test_answers_to_questions_that_changed_meanwhile_are_asked_again(): void
    {
        $template = $this->template();
        $defense = $this->decide();
        $stale = $template->questions;

        $this->actingAs($this->capsa, 'expert')->put(route('capsa.evaluations.update', $template), [
            'title' => $template->title, 'roles' => $template->roles, 'questions' => ['A brand new question.'],
        ]);

        $this->actingAs($this->leader, 'web')->post(route('student.evaluations.store'), [
            'defense_id' => $defense->defense_id, 'expert_id' => $this->experts['adviser']->expert_id, 'role' => 'adviser',
            'answers' => $stale->mapWithKeys(fn ($q) => [$q->id => 5])->all(),
        ])->assertSessionHas('evaluation_error', 'Your CaPSA changed these questions while you were answering. Here they are again.');

        $this->assertSame(0, ExpertEvaluation::count());
    }

    /**
     * A section can have two CaPSAs. The other one's evaluation counts for the
     * team, and the newest of them is what the leader answers.
     */
    public function test_every_capsa_of_the_section_counts_and_the_newest_evaluation_is_asked(): void
    {
        $this->template(questions: ['An older question from the first CaPSA.']);
        $defense = $this->decide();

        // The second CaPSA of BSIT-3A writes theirs after the defense.
        Carbon::setTestNow(now()->addHour());
        $second = $this->capsaFor(['BSIT-3A']);
        $this->actingAs($second, 'expert')->post(route('capsa.evaluations.store'), [
            'title' => 'Second CaPSA evaluation', 'defense_type' => DefenseOutcome::PRE_PROPOSAL,
            'roles' => ['adviser', 'consultant', 'grammarian'], 'questions' => ['A newer question from the second CaPSA.'],
        ])->assertSessionHasNoErrors();
        $newer = EvaluationTemplate::where('capsa_id', $second->expert_id)->sole();

        $this->assertSame([$newer->id], $this->due()->pluck('template_id')->unique()->values()->all());
        $this->actingAs($this->leader, 'web')->get(route('student.experts', ['tab' => 'evaluations']))
            ->assertOk()
            ->assertSee('A newer question from the second CaPSA.')
            ->assertDontSee('An older question from the first CaPSA.');

        $this->answer($this->leader, $defense, $this->experts['adviser'], 'adviser', $newer->load('questions'))->assertSessionHas('evaluation_saved');
        $this->actingAs($second, 'expert')->get(route('capsa.evaluations', ['tab' => 'results']))
            ->assertOk()
            ->assertSee($this->experts['adviser']->name);
    }

    /**
     * What happened on BSIT-3B: the first CaPSA's evaluation covers every role,
     * the second CaPSA edits theirs to cover only the adviser and consultant.
     * The edit decides - the grammarian is no longer asked about.
     */
    public function test_the_evaluation_edited_last_decides_the_roles_too(): void
    {
        $this->template();
        $this->decide();
        $second = $this->capsaFor(['BSIT-3A']);
        $theirs = $this->template(capsa: $second);
        $this->assertSame(['adviser', 'consultant', 'grammarian'], $this->due()->pluck('role')->all());

        Carbon::setTestNow(now()->addHour());
        $this->actingAs($second, 'expert')->put(route('capsa.evaluations.update', $theirs), [
            'title' => 'Pre-proposal', 'roles' => ['adviser', 'consultant'], 'questions' => EvaluationTemplate::STANDARD_QUESTIONS,
        ])->assertRedirect(route('capsa.evaluations'));

        $due = $this->due();
        $this->assertSame(['adviser', 'consultant'], $due->pluck('role')->all());
        $this->assertSame([$theirs->id], $due->pluck('template_id')->unique()->values()->all());

        $this->actingAs($this->leader, 'web')->get(route('student.experts', ['tab' => 'evaluations']))
            ->assertOk()
            ->assertSee('of 2 experts done')
            ->assertDontSee('of 3 experts done');
        $this->assertTrue($this->chaptersGateOpensAfter(['adviser', 'consultant'], $theirs->fresh('questions')));
    }

    /** One CaPSA may split a defense's evaluation by role; both halves are asked. */
    public function test_one_capsas_evaluations_for_different_roles_are_all_asked(): void
    {
        $this->template(roles: ['adviser', 'consultant'], questions: ['For the adviser and consultant.']);
        Carbon::setTestNow(now()->addMinute());
        $this->template(roles: ['grammarian'], questions: ['For the grammarian.']);
        $this->decide();

        $due = $this->due();
        $this->assertSame(['adviser', 'consultant', 'grammarian'], $due->pluck('role')->all());
        $this->assertCount(2, $due->pluck('template_id')->unique());
    }

    /** @param  array<int, string>  $roles */
    private function chaptersGateOpensAfter(array $roles, EvaluationTemplate $template): bool
    {
        $defense = DefenseSchedule::where('team_id', $this->team->team_id)->sole();
        foreach ($roles as $role) {
            $this->answer($this->leader, $defense, $this->experts[$role], $role, $template)->assertSessionHas('evaluation_saved');
        }

        return ! $this->chaptersGate()->locked;
    }

    /** Still nothing reaches back: with no evaluation when it was decided, a newer one asks nothing. */
    public function test_a_newer_evaluation_does_not_reach_a_defense_that_had_none(): void
    {
        $this->decide();
        Carbon::setTestNow(now()->addHour());
        $this->template();
        $this->template(capsa: $this->capsaFor(['BSIT-3A']));

        $this->assertCount(0, $this->due());
    }

    public function test_each_defense_is_evaluated_on_its_own(): void
    {
        $proposal = $this->template();
        $title = $this->template(stage: DefenseOutcome::TITLE_DEFENSE, roles: ['adviser']);
        $first = $this->decide();
        $this->answerAll($first, $proposal);
        $this->assertCount(0, $this->due());

        $second = $this->decide(stage: DefenseOutcome::TITLE_DEFENSE);

        $this->assertSame([[$second->defense_id, 'adviser']], $this->due()->map(fn ($r) => [$r->defense_id, $r->role])->all());
        $this->answer($this->leader, $second, $this->experts['adviser'], 'adviser', $title)->assertSessionHas('evaluation_saved');
        $this->assertSame(4, ExpertEvaluation::count());
    }

    public function test_the_leader_is_told_once_per_defense_by_bell_and_email(): void
    {
        $this->template();
        $this->decide();

        $this->artisan('evaluations:send-reminders')->assertSuccessful();
        $this->artisan('evaluations:send-reminders')->assertSuccessful();

        $bell = Notification::where('student_id', $this->leader->student_id)->where('type', 'evaluation_due')->get();
        $this->assertCount(1, $bell);
        $this->assertSame('Evaluate your adviser, consultant and grammarian: Pre-proposal Defense', $bell->first()->title);
        $this->assertStringContainsString('anonymous', $bell->first()->message);
        $this->assertStringContainsString('tab=evaluations', $bell->first()->link);
        Mail::assertQueued(NotificationMail::class, fn ($mail) => $mail->hasTo($this->leader->email));
    }

    public function test_opening_any_page_tells_the_leader_without_waiting_for_the_schedule(): void
    {
        $this->template();
        $this->decide();

        $this->actingAs($this->leader, 'web')->get(route('student.dashboard'))->assertOk();

        $this->assertSame(1, Notification::where('student_id', $this->leader->student_id)->where('type', 'evaluation_due')->count());
    }

    /**
     * Every student page works out what the leader owes, for the popup and
     * the reminder alike - once, not once for each.
     */
    public function test_a_page_works_out_what_is_owed_once(): void
    {
        $this->template();
        $this->decide();

        $counting = new class extends ExpertEvaluations
        {
            public int $dueCalls = 0;

            public function due(Team $team): Collection
            {
                $this->dueCalls++;

                return parent::due($team);
            }
        };
        $this->app->instance(ExpertEvaluations::class, $counting);

        $this->actingAs($this->leader, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Time to evaluate your adviser, consultant and grammarian');

        $this->assertSame(1, $counting->dueCalls);
        $this->assertSame(1, Notification::where('student_id', $this->leader->student_id)->where('type', 'evaluation_due')->count());
    }

    /**
     * The popup is open from the page's first paint: shown after a delay with
     * a fade, every page came up bright and then dimmed under it.
     */
    public function test_the_popup_is_open_from_the_first_paint(): void
    {
        $this->template();
        $this->decide();

        $this->actingAs($this->leader, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee("overlay.classList.add('evp-now', 'active');", false)
            ->assertDontSee('}, 700);', false);
    }

    public function test_the_leader_sees_the_popup_until_every_expert_is_evaluated(): void
    {
        $template = $this->template();
        $defense = $this->decide();

        $this->actingAs($this->leader, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Time to evaluate your adviser, consultant and grammarian')
            ->assertSee('Your team passed Pre-proposal Defense.')
            ->assertSee('Evaluate now');

        $this->answerAll($defense, $template);

        $this->actingAs($this->leader, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Time to evaluate your adviser, consultant and grammarian');
    }

    /**
     * "Remind me later" quiets the popup, and the bell entry can be read or
     * marked read - the evaluation still waits at the top of the bell, and in
     * its count, until it is sent.
     */
    public function test_the_bell_keeps_an_owed_evaluation_at_the_top_until_it_is_sent(): void
    {
        $template = $this->template();
        $defense = $this->decide();

        $this->actingAs($this->leader, 'web')->get(route('student.dashboard'))->assertOk();
        Notification::where('student_id', $this->leader->student_id)->update(['is_read' => true]);

        $this->actingAs($this->leader, 'web')->get(route('student.progress'))
            ->assertOk()
            ->assertSee('data-todo="evaluation"', false)
            ->assertSee('To do · stays here until you send it')
            ->assertSee('Evaluate your adviser, consultant and grammarian: Pre-proposal Defense')
            ->assertSee('3 waiting')
            ->assertSee('<div class="notif-dot">1</div>', false)
            ->assertSee('It stays at the top of your notifications until it is done.');

        $this->answerAll($defense, $template);

        $this->actingAs($this->leader, 'web')->get(route('student.progress'))
            ->assertOk()
            ->assertDontSee('data-todo="evaluation"', false)
            ->assertDontSee('<div class="notif-dot">', false);
    }

    public function test_a_member_has_no_to_do_in_the_bell(): void
    {
        $this->template();
        $this->decide();

        $this->actingAs($this->member(), 'web')->get(route('student.progress'))
            ->assertOk()
            ->assertDontSee('data-todo="evaluation"', false);
    }

    /** The leader changes while an evaluation waits: the new leader is told too, once. */
    public function test_a_new_leader_is_told_about_an_evaluation_already_owed(): void
    {
        $this->template();
        $this->decide();
        $this->artisan('evaluations:send-reminders')->assertSuccessful();

        // A teammate in the same section takes over.
        $newLeader = $this->member();
        $newLeader->update(['course' => $this->leader->course, 'year_section' => $this->leader->year_section]);
        $this->team->update(['leader_id' => $newLeader->student_id]);
        $this->artisan('evaluations:send-reminders')->assertSuccessful();
        $this->artisan('evaluations:send-reminders')->assertSuccessful();

        $this->assertSame(1, Notification::where('student_id', $this->leader->student_id)->where('type', 'evaluation_due')->count());
        $this->assertSame(1, Notification::where('student_id', $newLeader->student_id)->where('type', 'evaluation_due')->count());
        $this->actingAs($newLeader, 'web')->get(route('student.progress'))->assertOk()->assertSee('data-todo="evaluation"', false);
    }

    public function test_members_get_no_popup_and_no_reminder(): void
    {
        $this->template();
        $this->decide();
        $member = $this->member();

        $this->actingAs($member, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Time to evaluate your adviser, consultant and grammarian');

        $this->artisan('evaluations:send-reminders');
        $this->assertSame(0, Notification::where('student_id', $member->student_id)->where('type', 'evaluation_due')->count());
    }

    public function test_the_evaluations_tab_shows_the_questions_and_what_was_sent(): void
    {
        $template = $this->template(questions: ['Answered our questions quickly.', 'Explained the panel\'s comments.']);
        $defense = $this->decide();
        $this->answer($this->leader, $defense, $this->experts['adviser'], 'adviser', $template, [5, 4]);

        $this->actingAs($this->leader, 'web')->get(route('student.experts', ['tab' => 'evaluations']))
            ->assertOk()
            ->assertSee('id="xh-panel-eval"', false)
            ->assertSee('Defense passed')
            ->assertSee('Pre-proposal Defense')
            ->assertSee('Answered our questions quickly.')
            ->assertSee('Explained the panel&#039;s comments.', false)
            ->assertSee('name="evaluations['.$this->experts['consultant']->expert_id.'-consultant][answers]['.$template->questions[0]->id.']"', false)
            ->assertSee('5 · Excellent')
            ->assertSee('Anonymous.')
            ->assertSee('Send all 2 evaluations')
            ->assertSee($this->experts['consultant']->name)
            ->assertSee('Sent evaluations')
            ->assertSee('4.5 out of 5', false)
            // the popup stays out of the way on the tab it points to
            ->assertDontSee('Time to evaluate your adviser, consultant and grammarian');
    }

    public function test_a_member_reads_the_tab_but_has_no_forms(): void
    {
        $this->template();
        $this->decide();

        $this->actingAs($this->member(), 'web')->get(route('student.experts', ['tab' => 'evaluations']))
            ->assertOk()
            ->assertSee('Your team leader evaluates the experts.')
            ->assertDontSee(route('student.evaluations.store_all'));
    }

    /**
     * The real cache serializes, and refuses to bring objects back
     * (cache.serializable_classes = false). A cached Collection came back as
     * __PHP_Incomplete_Class and every student page of a leader crashed.
     */
    public function test_what_is_owed_survives_a_serializing_cache(): void
    {
        config([
            'cache.serializable_classes' => false,
            'cache.stores.evaluation-test' => ['driver' => 'file', 'path' => storage_path('framework/testing/evaluation-cache')],
            'cache.default' => 'evaluation-test',
        ]);
        Cache::store('evaluation-test')->flush();
        $this->template();
        $this->decide();

        $first = $this->due();
        $cached = $this->due();

        $this->assertCount(3, $cached);
        $this->assertEquals($first, $cached);

        $this->actingAs($this->leader, 'web')->get(route('student.experts', ['tab' => 'evaluations']))->assertOk();

        Cache::store('evaluation-test')->flush();
    }

    private function chaptersGate(): object
    {
        return MilestoneProgress::forTeam($this->checklist, $this->team->team_id)
            ->milestones->first(fn ($m) => $m->milestone->id === $this->chapters->id);
    }

    public function test_the_next_phase_stays_locked_until_every_expert_is_evaluated(): void
    {
        $template = $this->template();
        $defense = $this->decide();

        $gate = $this->chaptersGate();
        $this->assertTrue($gate->locked, 'the defense is passed but its experts are not evaluated');
        $this->assertSame('the expert evaluation for Pre-proposal Defense', $gate->requires);

        $this->answer($this->leader, $defense, $this->experts['adviser'], 'adviser', $template);
        $this->answer($this->leader, $defense, $this->experts['consultant'], 'consultant', $template);
        $this->assertTrue($this->chaptersGate()->locked, 'one expert is still not evaluated');

        $this->answer($this->leader, $defense, $this->experts['grammarian'], 'grammarian', $template);
        $this->assertFalse($this->chaptersGate()->locked);
    }

    public function test_without_an_evaluation_the_passed_defense_opens_the_next_phase(): void
    {
        $this->decide();

        $this->assertFalse($this->chaptersGate()->locked);
    }

    public function test_the_capsa_cannot_close_anything_in_the_locked_phase(): void
    {
        $template = $this->template();
        $defense = $this->decide();
        $form = $this->chapters->deliverables()->first();

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('capsa.deliverables.checks.update', [$form->id, $this->team->team_id]), ['status' => DeliverableCheck::COMPLETE])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Chapter 1 Form can't be checked yet: it waits for the expert evaluation for Pre-proposal Defense."]);

        $this->answerAll($defense, $template);

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('capsa.deliverables.checks.update', [$form->id, $this->team->team_id]), ['status' => DeliverableCheck::COMPLETE])
            ->assertSuccessful();
    }

    public function test_a_team_with_no_experts_yet_is_not_held_up(): void
    {
        TeamExpertAssignment::where('team_id', $this->team->team_id)->delete();
        $this->template();
        $this->decide();

        $this->assertFalse($this->chaptersGate()->locked);
    }

    public function test_the_progress_tracker_says_why_the_next_phase_is_locked(): void
    {
        $this->template();
        $this->decide();

        $this->actingAs($this->leader, 'web')->get(route('student.progress'))
            ->assertOk()
            ->assertSee('Evaluate your adviser, consultant and grammarian to unlock the next phase')
            ->assertSee('your anonymous evaluation for Pre-proposal Defense')
            ->assertSee('Opens after the expert evaluation for Pre-proposal Defense.');

        $this->actingAs($this->member(), 'web')->get(route('student.progress'))
            ->assertOk()
            ->assertSee('Waiting on your leader to evaluate your adviser, consultant and grammarian');
    }
}
