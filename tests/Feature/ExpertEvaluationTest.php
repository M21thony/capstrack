<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\ExpertEvaluation;
use App\Models\ExpertRole;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\ExpertEvaluations;
use App\Support\MilestoneProgress;
use App\Support\TeamChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * After every phase of the Progress Tracker the team leader rates each of the
 * team's experts - adviser, consultant, grammarian - from 1 to 5 stars with a
 * comment. The leader is told by bell and email, nudged by a popup, and the
 * ratings live on the Experts page's Evaluations tab.
 */
class ExpertEvaluationTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    private Student $leader;

    private Checklist $checklist;

    private ChecklistMilestone $gettingStarted;

    private ChecklistMilestone $proposal;

    private CapstoneExpert $capsa;

    /** @var array<string, CapstoneExpert> */
    private array $experts = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $capsa = $this->capsa = $this->capsaFor();
        [$this->team, $this->leader] = $this->teamInSection('Alpha');

        $this->checklist = Checklist::create(['capsa_id' => $capsa->expert_id, 'program' => 'BSIT', 'title' => 'Capstone Checklist']);
        $this->gettingStarted = $this->checklist->milestones()->create(['name' => 'Getting Started', 'position' => 0]);
        $this->proposal = $this->checklist->milestones()->create(['name' => 'Pre-proposal', 'position' => 1, 'requires_previous' => true]);

        foreach (['adviser', 'consultant', 'grammarian'] as $roleName) {
            $this->experts[$roleName] = $this->engage($roleName);
        }
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

    private function deliverable(ChecklistMilestone $milestone, string $title): Deliverable
    {
        return $milestone->deliverables()->create([
            'title' => $title,
            'kind' => Deliverable::FORM,
            'position' => (int) $milestone->deliverables()->max('position') + 1,
        ]);
    }

    private function finish(ChecklistMilestone $milestone): void
    {
        foreach ($milestone->deliverables as $line) {
            DeliverableCheck::create(['deliverable_id' => $line->id, 'team_id' => $this->team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);
        }
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

    private function rate(Student $as, CapstoneExpert $expert, string $role, array $overrides = []): TestResponse
    {
        return $this->actingAs($as, 'web')->post(route('student.evaluations.store'), $overrides + [
            'milestone_id' => $this->gettingStarted->id,
            'expert_id' => $expert->expert_id,
            'role' => $role,
            'rating' => 5,
            'comment' => 'Answered every question quickly.',
        ]);
    }

    public function test_nothing_is_owed_until_a_phase_is_finished(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->deliverable($this->gettingStarted, 'Topic List');

        $this->assertCount(0, $this->due());

        // One of two done is not finished.
        DeliverableCheck::create(['deliverable_id' => $this->gettingStarted->deliverables()->first()->id, 'team_id' => $this->team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);
        $this->assertCount(0, $this->due());
    }

    public function test_a_finished_phase_owes_one_rating_per_engaged_expert(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->deliverable($this->proposal, 'Pre-proposal Draft');
        $this->finish($this->gettingStarted);

        $due = $this->due();

        $this->assertSame(['adviser', 'consultant', 'grammarian'], $due->pluck('role')->all());
        $this->assertSame([$this->gettingStarted->id], $due->pluck('milestone_id')->unique()->values()->all());
    }

    public function test_an_empty_phase_or_an_expert_still_pending_owes_nothing(): void
    {
        // The second phase has nothing in it, so it is not "finished".
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);
        $pendingExpert = $this->engage('adviser', 'pending');

        $due = $this->due();

        $this->assertCount(3, $due);
        $this->assertNotContains($this->proposal->id, $due->pluck('milestone_id'));
        $this->assertNotContains($pendingExpert->expert_id, $due->pluck('expert_id'));
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

        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);

        $first = $this->due();
        $cached = $this->due();

        $this->assertCount(3, $cached);
        $this->assertEquals($first, $cached);
        $this->assertSame('Getting Started', $cached->first()->milestone);

        $this->actingAs($this->leader, 'web')->get(route('student.experts', ['tab' => 'evaluations']))->assertOk();

        Cache::store('evaluation-test')->flush();
    }

    public function test_the_leader_rates_an_expert_and_it_is_no_longer_owed(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);

        $this->rate($this->leader, $this->experts['adviser'], 'adviser', ['rating' => 4])
            ->assertRedirect(route('student.experts', ['tab' => 'evaluations']))
            ->assertSessionHas('evaluation_saved');

        $this->assertDatabaseHas('expert_evaluations', [
            'team_id' => $this->team->team_id,
            'expert_id' => $this->experts['adviser']->expert_id,
            'checklist_milestone_id' => $this->gettingStarted->id,
            'role' => 'adviser',
            'rating' => 4,
            'student_id' => $this->leader->student_id,
        ]);
        $this->assertSame(['consultant', 'grammarian'], $this->due()->pluck('role')->all());
    }

    public function test_the_same_rating_cannot_be_sent_twice(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);

        $this->rate($this->leader, $this->experts['adviser'], 'adviser');
        $this->rate($this->leader, $this->experts['adviser'], 'adviser')->assertSessionHas('evaluation_error');

        $this->assertSame(1, ExpertEvaluation::count());
    }

    public function test_a_member_cannot_rate(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);

        $this->rate($this->member(), $this->experts['adviser'], 'adviser')->assertSessionHas('evaluation_error');

        $this->assertSame(0, ExpertEvaluation::count());
    }

    public function test_a_phase_that_is_not_finished_cannot_be_rated(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');

        $this->rate($this->leader, $this->experts['adviser'], 'adviser')->assertSessionHas('evaluation_error');

        $this->assertSame(0, ExpertEvaluation::count());
    }

    public function test_stars_and_a_comment_are_required(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);

        $this->rate($this->leader, $this->experts['adviser'], 'adviser', ['rating' => 6])->assertSessionHasErrors('rating');
        $this->rate($this->leader, $this->experts['adviser'], 'adviser', ['rating' => 0])->assertSessionHasErrors('rating');
        $this->rate($this->leader, $this->experts['adviser'], 'adviser', ['comment' => ''])->assertSessionHasErrors('comment');
        $this->rate($this->leader, $this->experts['adviser'], 'panelist')->assertSessionHasErrors('role');

        $this->assertSame(0, ExpertEvaluation::count());
    }

    public function test_the_leader_is_told_once_per_phase_by_bell_and_email(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);

        $this->artisan('evaluations:send-reminders')->assertSuccessful();
        $this->artisan('evaluations:send-reminders')->assertSuccessful();

        $bell = Notification::where('student_id', $this->leader->student_id)->where('type', 'evaluation_due')->get();
        $this->assertCount(1, $bell);
        $this->assertSame('Evaluate your experts: Getting Started', $bell->first()->title);
        $this->assertStringContainsString('tab=evaluations', $bell->first()->link);
        Mail::assertQueued(NotificationMail::class, fn ($mail) => $mail->hasTo($this->leader->email));
    }

    public function test_opening_any_page_tells_the_leader_without_waiting_for_the_schedule(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);

        $this->actingAs($this->leader, 'web')->get(route('student.dashboard'))->assertOk();

        $this->assertSame(1, Notification::where('student_id', $this->leader->student_id)->where('type', 'evaluation_due')->count());
    }

    /**
     * Every student page works out what the leader owes, for the popup and
     * the reminder alike - once, not once for each.
     */
    public function test_a_page_works_out_what_is_owed_once(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);

        $counting = new class(app(TeamChecklist::class)) extends ExpertEvaluations
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
            ->assertSee('Time to evaluate your experts');

        $this->assertSame(1, $counting->dueCalls);
        $this->assertSame(1, Notification::where('student_id', $this->leader->student_id)->where('type', 'evaluation_due')->count());
    }

    public function test_the_leader_sees_the_popup_until_everything_is_rated(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);

        $this->actingAs($this->leader, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Time to evaluate your experts')
            ->assertSee('Evaluate now');

        foreach ($this->experts as $role => $expert) {
            $this->rate($this->leader, $expert, $role);
        }

        $this->actingAs($this->leader, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Time to evaluate your experts');
    }

    /**
     * The popup is open from the page's first paint: shown after a delay with
     * a fade, every page came up bright and then dimmed under it.
     */
    public function test_the_popup_is_open_from_the_first_paint(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);

        $this->actingAs($this->leader, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee("overlay.classList.add('evp-now', 'active');", false)
            ->assertDontSee('}, 700);', false);
    }

    public function test_members_get_no_popup_and_no_reminder(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);
        $member = $this->member();

        $this->actingAs($member, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Time to evaluate your experts');

        $this->artisan('evaluations:send-reminders');
        $this->assertSame(0, Notification::where('student_id', $member->student_id)->where('type', 'evaluation_due')->count());
    }

    public function test_the_evaluations_tab_shows_the_forms_and_what_was_sent(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);
        $this->rate($this->leader, $this->experts['adviser'], 'adviser', ['rating' => 3, 'comment' => 'Helpful with the scope.']);

        $this->actingAs($this->leader, 'web')->get(route('student.experts', ['tab' => 'evaluations']))
            ->assertOk()
            ->assertSee('id="xh-panel-eval"', false)
            ->assertSee('Getting Started')
            ->assertSee('Submit evaluation')
            ->assertSee($this->experts['consultant']->name)
            ->assertSee('Sent evaluations')
            ->assertSee('Helpful with the scope.')
            ->assertSee('3 out of 5 stars')
            // the popup stays out of the way on the tab it points to
            ->assertDontSee('Time to evaluate your experts');
    }

    public function test_a_member_reads_the_tab_but_has_no_forms(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->finish($this->gettingStarted);

        $this->actingAs($this->member(), 'web')->get(route('student.experts', ['tab' => 'evaluations']))
            ->assertOk()
            ->assertSee('Your team leader evaluates the experts.')
            ->assertDontSee('Submit evaluation');
    }

    private function proposalGate(): object
    {
        return MilestoneProgress::forTeam($this->checklist, $this->team->team_id)
            ->milestones->first(fn ($m) => $m->milestone->id === $this->proposal->id);
    }

    public function test_the_next_phase_stays_locked_until_every_expert_is_rated(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->deliverable($this->proposal, 'Final Title Form');
        $this->finish($this->gettingStarted);

        $gate = $this->proposalGate();
        $this->assertTrue($gate->locked, 'phase 1 is done but its experts are not rated');
        $this->assertSame('the expert evaluation for Getting Started', $gate->requires);

        $this->rate($this->leader, $this->experts['adviser'], 'adviser');
        $this->rate($this->leader, $this->experts['consultant'], 'consultant');
        $this->assertTrue($this->proposalGate()->locked, 'one expert is still not rated');

        $this->rate($this->leader, $this->experts['grammarian'], 'grammarian');
        $this->assertFalse($this->proposalGate()->locked);
    }

    public function test_the_capsa_cannot_close_anything_in_the_locked_phase(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $form = $this->deliverable($this->proposal, 'Final Title Form');
        $this->finish($this->gettingStarted);

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('capsa.deliverables.checks.update', [$form->id, $this->team->team_id]), ['status' => DeliverableCheck::COMPLETE])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Final Title Form can't be checked yet: it waits for the expert evaluation for Getting Started."]);

        foreach ($this->experts as $role => $expert) {
            $this->rate($this->leader, $expert, $role);
        }

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('capsa.deliverables.checks.update', [$form->id, $this->team->team_id]), ['status' => DeliverableCheck::COMPLETE])
            ->assertSuccessful();
    }

    public function test_a_team_with_no_experts_yet_is_not_held_up(): void
    {
        TeamExpertAssignment::where('team_id', $this->team->team_id)->delete();
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->deliverable($this->proposal, 'Final Title Form');
        $this->finish($this->gettingStarted);

        $this->assertFalse($this->proposalGate()->locked);
    }

    public function test_the_progress_tracker_says_why_the_next_phase_is_locked(): void
    {
        $this->deliverable($this->gettingStarted, 'Team Assignment Form');
        $this->deliverable($this->proposal, 'Final Title Form');
        $this->finish($this->gettingStarted);

        $this->actingAs($this->leader, 'web')->get(route('student.progress'))
            ->assertOk()
            ->assertSee('Evaluate your experts to unlock the next phase')
            ->assertSee('Opens after the expert evaluation for Getting Started.');

        $this->actingAs($this->member(), 'web')->get(route('student.progress'))
            ->assertOk()
            ->assertSee('Waiting on your leader to evaluate the experts');
    }
}
