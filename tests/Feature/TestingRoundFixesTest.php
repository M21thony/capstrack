<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\RubricCriterion;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel Assignment opens on the stage the teams are at, and a team sees
 * every panelist's copy of the rubric once the panel has decided.
 */
class TestingRoundFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_assignment_opens_on_the_stage_the_teams_are_at(): void
    {
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]));
        $team = Team::create(['team_name' => 'Alpha', 'team_status' => 'Active', 'is_confirmed' => true]);

        $opens = fn () => $this->actingAs($capsa, 'expert')->get(route('capsa.panel_assignment'))->assertOk()->viewData('state')['stage'];

        $this->assertSame('Pre-proposal Defense', $opens());

        DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => 'Pre-proposal Defense', 'attempt' => 1, 'outcome' => 'passed']);
        $this->assertSame('Title Defense', $opens());

        // Asking for a stage still shows that stage.
        $this->actingAs($capsa, 'expert')->get(route('capsa.panel_assignment', ['defense_type' => 'Final Oral Defense']))
            ->assertViewHas('state', fn ($state) => $state['stage'] === 'Final Oral Defense');
    }

    public function test_a_team_sees_each_panelists_scores_once_the_panel_has_decided(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'Bravo', 'team_status' => 'Active', 'is_confirmed' => true]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        $rubric = GradingRubric::create(['rubric_name' => 'Pre-proposal', 'rubric_type' => 'Pre-proposal Defense', 'total_points' => 10, 'is_active' => true]);
        $criterion = RubricCriterion::create(['rubric_id' => $rubric->rubric_id, 'criterion_name' => 'Problem statement', 'category' => 'Chapter 1', 'max_points' => 10, 'category_max_points' => 10, 'score_levels' => '[]', 'sort_order' => 0]);

        $decided = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => 'Pre-proposal Defense', 'attempt' => 1, 'outcome' => 'redefense']);
        $pending = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => 'Pre-proposal Defense', 'attempt' => 2]);

        foreach (['Panel Chair' => 8, 'Panel Member 1' => 6, 'Panel Member 2' => 7] as $role => $score) {
            $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'first_name' => str_replace(' ', '', $role)]);
            foreach ([$decided, $pending] as $defense) {
                DefensePanel::create(['defense_id' => $defense->defense_id, 'expert_id' => $expert->expert_id, 'panel_role' => $role]);
                PanelGrade::create([
                    'defense_id' => $defense->defense_id, 'expert_id' => $expert->expert_id, 'rubric_id' => $rubric->rubric_id,
                    'scores' => [(string) $criterion->criterion_id => $score], 'total_score' => $score, 'status' => 'submitted',
                    'remarks' => $defense->is($decided) ? "{$role} remark" : 'Not out yet',
                ]);
            }
        }

        $this->actingAs($student)->get(route('student.defense-scores'))
            ->assertOk()
            ->assertSee('Redefense with major revision')
            ->assertSee('PanelChair')
            ->assertSee('Panel Member 2 remark')
            ->assertDontSee('Not out yet');
    }
}
