<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A panelist can abstain from grading a team, with a reason. It counts as
 * turned in, so the panel is not held up, and it is left out of the average.
 */
class PanelAbstainTest extends TestCase
{
    use RefreshDatabase;

    private DefenseSchedule $defense;

    private Team $team;

    private GradingRubric $rubric;

    /** @var array<string, CapstoneExpert> */
    private array $panel = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->rubric = GradingRubric::create([
            'rubric_name' => 'Test Rubric', 'rubric_type' => 'Pre-Proposal Defense', 'total_points' => 100, 'is_active' => true,
        ]);
        $this->rubric->criteria()->create([
            'criterion_name' => 'Everything', 'category' => 'All', 'category_max_points' => 100, 'max_points' => 100,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 100, 'desc' => ''],
                ['label' => 'Fair', 'score' => 60, 'desc' => ''],
            ]),
            'sort_order' => 0,
        ]);

        $this->team = Team::create(['team_name' => 'Abstain Team', 'project_title' => 'A Capstone']);
        $this->defense = DefenseSchedule::create([
            'team_id' => $this->team->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL,
            'schedule_date' => now()->addDay()->setTime(8, 0),
        ]);

        foreach (['chair' => 'Panel Chair', 'one' => 'Panel Member 1', 'two' => 'Panel Member 2'] as $key => $role) {
            $expert = CapstoneExpert::create(['email' => $key.'@example.com', 'first_name' => ucfirst($key), 'last_name' => 'Panel', 'approved_status' => true]);
            DefensePanel::create(['defense_id' => $this->defense->defense_id, 'expert_id' => $expert->expert_id, 'panel_role' => $role, 'created_at' => now()]);
            $this->panel[$key] = $expert;
        }
    }

    private function abstain(string $who, string $reason = 'I advised this team, so I should not grade it.')
    {
        return $this->actingAs($this->panel[$who], 'expert')
            ->postJson(route('instructor.grading.abstain', $this->defense->defense_id), ['reason' => $reason]);
    }

    private function score(string $who, float $points)
    {
        $criterion = $this->rubric->criteria()->first();

        return $this->actingAs($this->panel[$who], 'expert')
            ->patchJson(route('instructor.grading.submit', $this->defense->defense_id), ['scores' => [$criterion->criterion_id => $points]]);
    }

    public function test_a_panelist_abstains_with_a_reason(): void
    {
        $this->abstain('one')->assertOk()->assertJsonPath('data.status', PanelGrade::ABSTAINED);

        $grade = PanelGrade::where('expert_id', $this->panel['one']->expert_id)->firstOrFail();
        $this->assertTrue($grade->isAbstained());
        $this->assertSame('I advised this team, so I should not grade it.', $grade->remarks);
        $this->assertNull($grade->total_score);
        $this->assertSame(1, $this->defense->fresh()->gradesInCount());
    }

    public function test_abstaining_needs_a_real_reason(): void
    {
        $this->abstain('one', 'no')->assertStatus(422);
        $this->abstain('one', '')->assertStatus(422);

        $this->assertSame(0, PanelGrade::count());
    }

    public function test_the_average_leaves_the_abstainer_out_and_the_chair_can_decide(): void
    {
        $this->abstain('one')->assertOk();
        $this->score('two', 60)->assertOk()->assertJsonPath('data.can_record_outcome', false);
        // The chair's own score completes the panel, with one abstention in it.
        $this->score('chair', 100)->assertOk()->assertJsonPath('data.can_record_outcome', true);

        $defense = $this->defense->fresh();
        $this->assertTrue($defense->allGradesIn());
        // (100 + 60) / 2 scorers - not / 3 panelists, and the abstainer is not a zero.
        $this->assertSame(80.0, $defense->panelAverage());

        $this->actingAs($this->panel['chair'], 'expert')
            ->postJson(route('instructor.grading.outcome', $defense->defense_id), ['outcome' => DefenseOutcome::PASSED])
            ->assertOk();
    }

    public function test_the_decision_waits_for_everyone_even_with_an_abstention(): void
    {
        $this->abstain('one')->assertOk();
        $this->score('chair', 100)->assertOk();

        $this->assertFalse($this->defense->fresh()->allGradesIn());
        $this->assertNull($this->defense->fresh()->panelAverage());
        $this->actingAs($this->panel['chair'], 'expert')
            ->postJson(route('instructor.grading.outcome', $this->defense->defense_id), ['outcome' => DefenseOutcome::PASSED])
            ->assertStatus(422);
    }

    /**
     * Abstaining is how a panel gets past grading it will not do - some
     * panelists do for a team that is redefending. Everyone may abstain; the
     * chair still records the decision, and the team's score stays blank.
     */
    public function test_everyone_may_abstain_and_the_chair_still_records_the_decision(): void
    {
        $this->abstain('one')->assertOk();
        $this->abstain('two')->assertOk();
        $this->abstain('chair')->assertOk();

        $defense = $this->defense->fresh();
        $this->assertTrue($defense->allGradesIn());
        $this->assertNull($defense->panelAverage());

        $this->actingAs($this->panel['chair'], 'expert')
            ->postJson(route('instructor.grading.outcome', $this->defense->defense_id), ['outcome' => DefenseOutcome::REDEFENSE])
            ->assertOk();

        $this->assertSame(DefenseOutcome::REDEFENSE, $this->defense->fresh()->outcome);
    }

    /** The redefense comes back to grade on each panelist's list, marked as one, once it has a date. */
    public function test_after_an_abstained_defense_the_redefense_comes_back_to_grade(): void
    {
        foreach (['one', 'two', 'chair'] as $who) {
            $this->abstain($who)->assertOk();
        }
        $this->actingAs($this->panel['chair'], 'expert')
            ->postJson(route('instructor.grading.outcome', $this->defense->defense_id), ['outcome' => DefenseOutcome::REDEFENSE])
            ->assertOk();

        $redefense = DefenseSchedule::where('team_id', $this->team->team_id)->where('attempt', 2)->sole();
        $redefense->update(['schedule_date' => now()->addWeek()]);

        $this->actingAs($this->panel['one'], 'expert')->get(route('instructor.grading'))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->sole()->defense_id === $redefense->defense_id && $rows->sole()->status === 'not_started')
            ->assertSee('Redefense');
    }

    public function test_no_abstaining_after_the_decision_is_recorded(): void
    {
        $this->defense->update(['outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now()]);

        $this->abstain('one')->assertStatus(422);
        $this->assertSame(0, PanelGrade::count());
    }

    public function test_an_abstention_can_be_withdrawn_to_grade_after_all(): void
    {
        $this->abstain('one')->assertOk();

        $this->actingAs($this->panel['one'], 'expert')
            ->deleteJson(route('instructor.grading.abstain.withdraw', $this->defense->defense_id))
            ->assertOk();

        $grade = PanelGrade::where('expert_id', $this->panel['one']->expert_id)->firstOrFail();
        $this->assertSame(PanelGrade::DRAFT, $grade->status);
        $this->assertNull($grade->remarks);
        $this->assertSame(0, $this->defense->fresh()->gradesInCount());
    }

    public function test_only_the_panel_can_abstain(): void
    {
        $outsider = CapstoneExpert::create(['email' => 'outsider@example.com', 'first_name' => 'Out', 'last_name' => 'Sider', 'approved_status' => true]);

        $this->actingAs($outsider, 'expert')
            ->postJson(route('instructor.grading.abstain', $this->defense->defense_id), ['reason' => 'I would like to abstain please.'])
            ->assertForbidden();
    }

    public function test_the_grading_pages_show_the_abstention(): void
    {
        $this->abstain('one')->assertOk();

        $this->actingAs($this->panel['one'], 'expert')->get(route('instructor.grading'))
            ->assertOk()
            ->assertSee('Abstained');

        $this->actingAs($this->panel['one'], 'expert')->get(route('instructor.grading.show', $this->defense->defense_id))
            ->assertOk()
            ->assertSee('You abstained from grading this team.')
            ->assertSee('Withdraw abstention and grade')
            ->assertDontSee('id="gs-submit"', false);

        // Everyone else is offered the choice.
        $this->actingAs($this->panel['two'], 'expert')->get(route('instructor.grading.show', $this->defense->defense_id))
            ->assertOk()
            ->assertSee('id="abstain-modal"', false)
            ->assertSee('openAbstain()', false);
    }

    /** The team sees who abstained, never why. */
    public function test_the_team_sees_the_abstention_but_not_the_reason(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $this->team->team_id]);

        $this->abstain('one', 'Private reason the team should not read.')->assertOk();
        $this->score('two', 60)->assertOk();
        $this->score('chair', 100)->assertOk();
        $this->defense->update(['outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now()]);

        $this->actingAs($student, 'web')->get(route('student.defense-scores'))
            ->assertOk()
            ->assertSee('Abstained from grading this defense.')
            ->assertSee('80')
            ->assertDontSee('Private reason the team should not read.');
    }
}
