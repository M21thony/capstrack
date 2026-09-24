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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A panelist may abstain on any criterion - some do for a team that is
 * redefending. An abstention is not a zero against the team.
 */
class GradingAbstainTest extends TestCase
{
    use RefreshDatabase;

    private GradingRubric $rubric;

    private DefenseSchedule $defense;

    /** @var array<int, CapstoneExpert> */
    private array $panel = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->rubric = GradingRubric::create(['rubric_name' => 'Rubric', 'rubric_type' => 'Pre-proposal Defense', 'total_points' => 100, 'is_active' => true]);
        foreach (['Presentation' => 40, 'Content' => 60] as $i => $max) {
            $this->rubric->criteria()->create([
                'criterion_name' => $i, 'category' => $i, 'category_max_points' => $max, 'max_points' => $max, 'sort_order' => $max,
                'score_levels' => json_encode([['label' => 'Top', 'score' => $max, 'desc' => ''], ['label' => 'Half', 'score' => $max / 2, 'desc' => '']]),
            ]);
        }

        $leader = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'Bulbasaur', 'project_title' => 'A Title', 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        $this->defense = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => 'Pre-proposal Defense', 'attempt' => 2, 'schedule_date' => now()]);

        foreach (['Panel Chair', 'Panel Member 1', 'Panel Member 2'] as $i => $role) {
            $this->panel[$i] = CapstoneExpert::factory()->create(['approved_status' => true]);
            DefensePanel::create(['defense_id' => $this->defense->defense_id, 'expert_id' => $this->panel[$i]->expert_id, 'panel_role' => $role, 'created_at' => now()]);
        }
    }

    private function criterion(string $name): int
    {
        return (int) $this->rubric->criteria()->where('criterion_name', $name)->value('criterion_id');
    }

    private function submit(CapstoneExpert $expert, array $scores, array $abstained = [])
    {
        return $this->actingAs($expert, 'expert')->patchJson(
            route('instructor.grading.submit', $this->defense->defense_id),
            ['scores' => $scores, 'abstained' => $abstained],
        );
    }

    public function test_a_criterion_can_be_abstained_instead_of_scored(): void
    {
        $this->submit($this->panel[0], [$this->criterion('Content') => 60], [$this->criterion('Presentation')])->assertOk();

        $grade = PanelGrade::sole();
        $this->assertSame('submitted', $grade->status);
        $this->assertSame([$this->criterion('Presentation')], $grade->abstained);
        $this->assertEquals(60, (float) $grade->total_score);
        $this->assertTrue($grade->abstainedOn($this->criterion('Presentation')));
    }

    public function test_every_criterion_still_needs_a_score_or_an_abstention(): void
    {
        $this->submit($this->panel[0], [$this->criterion('Content') => 60])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Score or abstain on every criterion before submitting.');
    }

    public function test_abstaining_on_everything_leaves_no_total(): void
    {
        $this->submit($this->panel[0], [], [$this->criterion('Presentation'), $this->criterion('Content')])->assertOk();

        $this->assertNull(PanelGrade::sole()->total_score);
    }

    public function test_an_abstention_wins_over_a_score_sent_for_the_same_criterion(): void
    {
        $p = $this->criterion('Presentation');
        $this->submit($this->panel[0], [$p => 40, $this->criterion('Content') => 60], [$p])->assertOk();

        $this->assertArrayNotHasKey($p, PanelGrade::sole()->scores);
    }

    /** Each criterion is averaged over the panelists who scored it, so an abstention is not a zero. */
    public function test_the_team_average_leaves_the_abstention_out(): void
    {
        [$p, $c] = [$this->criterion('Presentation'), $this->criterion('Content')];

        $this->submit($this->panel[0], [$p => 40, $c => 60])->assertOk();
        $this->submit($this->panel[1], [$p => 20, $c => 30])->assertOk();
        $this->submit($this->panel[2], [$c => 30], [$p])->assertOk();

        // Presentation (40 + 20) / 2 = 30, Content (60 + 30 + 30) / 3 = 40.
        $this->assertSame(70.0, $this->defense->fresh()->panelAverage());
    }

    public function test_the_average_is_unchanged_when_nobody_abstains(): void
    {
        [$p, $c] = [$this->criterion('Presentation'), $this->criterion('Content')];

        $this->submit($this->panel[0], [$p => 40, $c => 60])->assertOk();
        $this->submit($this->panel[1], [$p => 20, $c => 30])->assertOk();
        $this->submit($this->panel[2], [$p => 20, $c => 30])->assertOk();

        $this->assertSame(66.67, $this->defense->fresh()->panelAverage());
    }

    public function test_a_panel_that_abstains_on_everything_gives_no_score(): void
    {
        $all = [$this->criterion('Presentation'), $this->criterion('Content')];

        foreach ($this->panel as $expert) {
            $this->submit($expert, [], $all)->assertOk();
        }

        $this->assertTrue($this->defense->fresh()->allGradesIn());
        $this->assertNull($this->defense->fresh()->panelAverage());
    }

    /** Abstaining turns the panelist's sheet in, so the chair can record the panel's decision without scores. */
    public function test_the_chair_can_record_the_decision_once_every_panelist_abstained(): void
    {
        $all = [$this->criterion('Presentation'), $this->criterion('Content')];

        $this->submit($this->panel[1], [], $all)->assertOk();
        $this->submit($this->panel[2], [], $all)->assertOk();
        $this->submit($this->panel[0], [], $all)
            ->assertOk()
            ->assertJsonPath('data.can_record_outcome', true);

        $this->actingAs($this->panel[0], 'expert')
            ->postJson(route('instructor.grading.outcome', $this->defense->defense_id), ['outcome' => 'passed'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('passed', $this->defense->fresh()->outcome);
    }

    public function test_the_decision_still_waits_for_a_panelist_who_has_not_turned_in(): void
    {
        $this->submit($this->panel[0], [], [$this->criterion('Presentation'), $this->criterion('Content')])
            ->assertOk()
            ->assertJsonPath('data.can_record_outcome', false);

        $this->actingAs($this->panel[0], 'expert')
            ->postJson(route('instructor.grading.outcome', $this->defense->defense_id), ['outcome' => 'passed'])
            ->assertStatus(422);
    }

    /** Abstain on the first try, send the team to redefense, and the redefense comes back to grade, marked as one. */
    public function test_the_redefense_comes_back_on_the_list_to_grade(): void
    {
        $this->defense->update(['attempt' => 1]);
        $all = [$this->criterion('Presentation'), $this->criterion('Content')];
        foreach ([1, 2, 0] as $i) {
            $this->submit($this->panel[$i], [], $all)->assertOk();
        }
        $this->actingAs($this->panel[0], 'expert')
            ->postJson(route('instructor.grading.outcome', $this->defense->defense_id), ['outcome' => 'redefense'])
            ->assertOk();

        $this->get(route('instructor.grading'))
            ->assertOk()
            ->assertSee('Submitted · Abstained');

        $redefense = DefenseSchedule::where('team_id', $this->defense->team_id)->where('attempt', 2)->sole();
        $redefense->update(['schedule_date' => now()->addWeek()]);

        $this->get(route('instructor.grading'))
            ->assertOk()
            ->assertViewHas('rows', fn ($rows) => $rows->sole()->defense_id === $redefense->defense_id
                && $rows->sole()->attempt === 2 && $rows->sole()->status === 'not_started')
            ->assertSee('Redefense · 2nd attempt')
            ->assertDontSee('Submitted · Abstained');
    }

    public function test_the_team_sees_abstained_instead_of_a_score(): void
    {
        [$p, $c] = [$this->criterion('Presentation'), $this->criterion('Content')];
        $this->submit($this->panel[0], [$p => 40, $c => 60])->assertOk();
        $this->submit($this->panel[1], [$p => 20, $c => 30])->assertOk();
        $this->submit($this->panel[2], [], [$p, $c])->assertOk();
        $this->defense->update(['outcome' => 'passed', 'outcome_recorded_at' => now()]);

        $leader = Student::find($this->defense->team->leader_id);

        $this->actingAs($leader, 'web')->get(route('student.defense-scores'))
            ->assertOk()
            ->assertSee('Abstained')
            // (40 + 20) / 2 + (60 + 30) / 2
            ->assertSee('75');
    }

    public function test_the_grading_sheet_offers_abstain_all_and_keeps_a_saved_one(): void
    {
        $this->actingAs($this->panel[0], 'expert')->putJson(
            route('instructor.grading.save', $this->defense->defense_id),
            ['scores' => [], 'abstained' => [$this->criterion('Presentation')]],
        )->assertOk();

        $this->get(route('instructor.grading.show', $this->defense->defense_id))
            ->assertOk()
            ->assertSee('Abstain all')
            ->assertSee("const abstained = new Set(JSON.parse('[\\u0022".$this->criterion('Presentation')."\\u0022]'));", false);
    }
}
