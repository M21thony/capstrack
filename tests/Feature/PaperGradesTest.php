<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\Team;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel comment 13: the panel graded on paper. Whoever records the decision
 * types each panelist's total off their sheet; each panelist still sees it
 * and can replace it with their own until the decision is recorded.
 */
class PaperGradesTest extends TestCase
{
    use RefreshDatabase;

    private DefenseSchedule $defense;

    private int $criterionId;

    /** @var array{chair: CapstoneExpert, one: CapstoneExpert, two: CapstoneExpert} */
    private array $panel;

    protected function setUp(): void
    {
        parent::setUp();

        $team = Team::create(['team_name' => 'Paper Team', 'project_title' => 'Ink and Sheets']);
        $this->defense = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Pre-proposal Defense',
            'schedule_date' => now()->subHour(),
        ]);

        foreach (['chair' => 'Panel Chair', 'one' => 'Panel Member 1', 'two' => 'Panel Member 2'] as $key => $role) {
            $expert = CapstoneExpert::create([
                'email' => $key.'.paper@gmail.com',
                'first_name' => ucfirst($key),
                'last_name' => 'Panelist',
                'approved_status' => true,
            ]);
            DefensePanel::create([
                'defense_id' => $this->defense->defense_id,
                'expert_id' => $expert->expert_id,
                'panel_role' => $role,
                'created_at' => now(),
            ]);
            $this->panel[$key] = $expert;
        }

        $rubric = GradingRubric::create([
            'rubric_name' => 'Paper Rubric',
            'rubric_type' => 'Pre-proposal Defense',
            'total_points' => 100,
            'is_active' => true,
        ]);
        $this->criterionId = $rubric->criteria()->create([
            'criterion_name' => 'Everything',
            'category' => 'All',
            'category_max_points' => 100,
            'max_points' => 100,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 100, 'desc' => 'Great'],
                ['label' => 'Good', 'score' => 80, 'desc' => 'Good'],
            ]),
            'sort_order' => 0,
        ])->criterion_id;
    }

    private function id(string $who): int
    {
        return (int) $this->panel[$who]->expert_id;
    }

    private function url(string $suffix = ''): string
    {
        return '/instructor/grading/'.$this->defense->defense_id.$suffix;
    }

    /** @param  array<string, float|int>  $totals */
    private function paper(string $by, array $totals, ?string $outcome = null)
    {
        $keyed = [];
        foreach ($totals as $who => $total) {
            $keyed[$this->id($who)] = $total;
        }

        return $this->actingAs($this->panel[$by], 'expert')
            ->postJson($this->url('/paper'), ['totals' => $keyed, 'outcome' => $outcome]);
    }

    private function gradeOf(string $who): ?PanelGrade
    {
        return PanelGrade::where('defense_id', $this->defense->defense_id)->where('expert_id', $this->id($who))->first();
    }

    public function test_the_chair_types_every_total_and_they_count_as_turned_in(): void
    {
        $this->paper('chair', ['chair' => 90, 'one' => 80, 'two' => 85.5])
            ->assertOk()->assertJson(['success' => true, 'message' => 'Grades saved from paper.']);

        foreach (['chair' => 90, 'one' => 80, 'two' => 85.5] as $who => $total) {
            $grade = $this->gradeOf($who);
            $this->assertSame(PanelGrade::SUBMITTED, $grade->status);
            $this->assertEquals($total, (float) $grade->total_score);
            $this->assertSame('Entered from paper by Chair Panelist (Panel Chair).', $grade->remarks);
            $this->assertTrue($grade->isFromPaper());
        }

        $defense = $this->defense->fresh();
        $this->assertTrue($defense->allGradesIn());
        $this->assertSame(85.17, $defense->panelAverage());
        $this->assertNull($defense->outcome);
    }

    public function test_only_whoever_records_the_decision_sees_the_form_and_can_save(): void
    {
        $this->actingAs($this->panel['chair'], 'expert')->get($this->url())->assertOk()
            ->assertSee('Enter from paper sheets')
            ->assertSee('class="gs-paper-score" data-id="'.$this->id('two').'"', false)
            ->assertSee('Entered from paper by Chair Panelist (Panel Chair)');

        $this->actingAs($this->panel['one'], 'expert')->get($this->url())->assertOk()
            ->assertDontSee('id="gs-paper"', false);

        $this->paper('one', ['chair' => 90, 'one' => 80, 'two' => 85])
            ->assertForbidden()->assertJson(['message' => 'Only the Panel Chair enters grades from paper.']);

        $this->assertSame(0, PanelGrade::count());
    }

    public function test_a_total_out_of_range_or_missing_saves_nothing(): void
    {
        $this->paper('chair', ['chair' => 90, 'one' => 120, 'two' => 85])
            ->assertStatus(422)->assertJson(['message' => "A total cannot be more than the rubric's 100."]);

        $this->paper('chair', ['chair' => 90, 'one' => -5, 'two' => 85])->assertStatus(422);

        $this->paper('chair', ['chair' => 90, 'one' => 80])
            ->assertStatus(422)->assertJson(['message' => "Type every panelist's total."]);

        $this->paper('chair', ['chair' => 90, 'one' => 80, 'two' => 85], 'made_up')->assertStatus(422);

        $this->assertSame(0, PanelGrade::count());
    }

    public function test_a_grade_already_turned_in_is_left_alone_and_a_join_is_cleared(): void
    {
        // Member 1 scored on their own laptop; Member 2 was joined to the chair's grade.
        $this->actingAs($this->panel['one'], 'expert')
            ->patchJson($this->url(), ['scores' => [$this->criterionId => 80], 'remarks' => 'Tighten chapter 2.'])->assertOk();
        $this->actingAs($this->panel['chair'], 'expert')
            ->putJson($this->url('/grader'), ['grader_id' => $this->id('chair'), 'joined' => [$this->id('two')]])->assertOk();
        $this->assertTrue($this->gradeOf('two')->isJoined());

        $this->actingAs($this->panel['chair'], 'expert')->get($this->url())->assertOk()
            ->assertSee('data-own="80"', false)->assertSee('turned in')
            ->assertDontSee('class="gs-paper-score" data-id="'.$this->id('one').'"', false);

        // A total typed for Member 1 is ignored: theirs is already in.
        $this->paper('chair', ['chair' => 92, 'one' => 10, 'two' => 70])->assertOk();

        $one = $this->gradeOf('one');
        $this->assertEquals(80, (float) $one->total_score);
        $this->assertSame('Tighten chapter 2.', $one->remarks);

        $two = $this->gradeOf('two');
        $this->assertFalse($two->isJoined());
        $this->assertEquals(70, (float) $two->total_score);
        $this->assertTrue($two->isFromPaper());
        $this->assertSame(80.67, $this->defense->fresh()->panelAverage());
    }

    public function test_a_panelist_can_correct_theirs_and_the_decision_can_ride_along(): void
    {
        $this->paper('chair', ['chair' => 90, 'one' => 80, 'two' => 85])->assertOk();

        // Member 1 sees who typed it, with an empty remarks box, and replaces it.
        $this->actingAs($this->panel['one'], 'expert')->get($this->url())->assertOk()
            ->assertSee('<b>Entered from paper by Chair Panelist (Panel Chair).</b> Total 80 / 100.', false)
            ->assertSee('placeholder="What should the team fix, and what worked?"></textarea>', false);
        $this->actingAs($this->panel['one'], 'expert')
            ->patchJson($this->url(), ['scores' => [$this->criterionId => 100], 'remarks' => 'Strong scope.'])->assertOk();
        $this->assertFalse($this->gradeOf('one')->isFromPaper());

        // The chair saves again with the decision: Member 1's own grade stays.
        $this->paper('chair', ['chair' => 90, 'two' => 85], 'passed')
            ->assertOk()->assertJson(['message' => 'Grades saved from paper. Outcome recorded: '.DefenseOutcome::label('passed', 'Pre-proposal Defense').'.']);

        $defense = $this->defense->fresh();
        $this->assertSame('passed', $defense->outcome);
        $this->assertSame($this->id('chair'), (int) $defense->outcome_recorded_by);
        $this->assertEquals(100, (float) $this->gradeOf('one')->total_score);
        $this->assertSame(91.67, $defense->panelAverage());

        // Decided: the form is gone and nothing more is typed in.
        $this->actingAs($this->panel['chair'], 'expert')->get($this->url())->assertOk()->assertDontSee('id="gs-paper"', false);
        $this->paper('chair', ['chair' => 50, 'two' => 50])
            ->assertStatus(422)->assertJson(['message' => 'The panel has already recorded its decision for this defense.']);
    }
}
