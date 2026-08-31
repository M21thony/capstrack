<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingTest extends TestCase
{
    use RefreshDatabase;

    private function makeExpert(string $email = 'grom.hellscream@gmail.com'): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => $email,
            'first_name' => 'Grom',
            'last_name' => 'Hellscream',
            'approved_status' => true,
        ]);
    }

    private function makeRubric(string $type = 'Pre-Proposal Defense'): GradingRubric
    {
        $rubric = GradingRubric::create([
            'rubric_name' => 'Test Rubric',
            'rubric_type' => $type,
            'total_points' => 100,
            'is_active' => true,
        ]);

        $rubric->criteria()->create([
            'criterion_name' => 'Presentation',
            'category' => 'Oral Examination',
            'category_max_points' => 40,
            'max_points' => 40,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 40, 'desc' => 'Great'],
                ['label' => 'Good', 'score' => 30, 'desc' => 'Good'],
                ['label' => 'Fair', 'score' => 20, 'desc' => 'Fair'],
                ['label' => 'Poor', 'score' => 10, 'desc' => 'Poor'],
            ]),
            'sort_order' => 0,
        ]);

        $rubric->criteria()->create([
            'criterion_name' => 'Content',
            'category' => 'Content Quality',
            'category_max_points' => 60,
            'max_points' => 60,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 60, 'desc' => 'Great'],
                ['label' => 'Good', 'score' => 45, 'desc' => 'Good'],
                ['label' => 'Fair', 'score' => 30, 'desc' => 'Fair'],
                ['label' => 'Poor', 'score' => 15, 'desc' => 'Poor'],
            ]),
            'sort_order' => 1,
        ]);

        return $rubric;
    }

    private function makeAssignedDefense(CapstoneExpert $expert, string $defenseType = 'Pre-proposal Defense', string $panelRole = 'Panel Chair'): DefenseSchedule
    {
        $team = Team::create(['team_name' => 'Test Team', 'project_title' => 'A Great Capstone']);

        $schedule = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => $defenseType,
        ]);

        DefensePanel::create([
            'defense_id' => $schedule->defense_id,
            'expert_id' => $expert->expert_id,
            'panel_role' => $panelRole,
            'created_at' => now(),
        ]);

        return $schedule;
    }

    public function test_grading_index_lists_teams_expert_is_assigned_as_panel(): void
    {
        $expert = $this->makeExpert();
        $this->makeAssignedDefense($expert);

        $response = $this->actingAs($expert, 'expert')->get('/instructor/grading');

        $response->assertOk();
        $response->assertSee('Test Team');
        $response->assertSee('Not Started');
    }

    public function test_grading_index_is_empty_when_expert_has_no_panel_assignment(): void
    {
        $expert = $this->makeExpert();

        $response = $this->actingAs($expert, 'expert')->get('/instructor/grading');

        $response->assertOk();
        $response->assertSee('No Teams Assigned Yet');
    }

    public function test_expert_not_on_panel_cannot_view_grading_show_page(): void
    {
        $expert = $this->makeExpert();
        $otherExpert = $this->makeExpert('other.expert@gmail.com');
        $defense = $this->makeAssignedDefense($expert);
        $this->makeRubric();

        $response = $this->actingAs($otherExpert, 'expert')->get('/instructor/grading/'.$defense->defense_id);

        $response->assertStatus(403);
    }

    public function test_grading_show_renders_rubric_criteria_dynamically(): void
    {
        $expert = $this->makeExpert();
        $defense = $this->makeAssignedDefense($expert);
        $this->makeRubric();

        $response = $this->actingAs($expert, 'expert')->get('/instructor/grading/'.$defense->defense_id);

        $response->assertOk();
        $response->assertSee('Presentation');
        $response->assertSee('Content');
        $response->assertSee('Oral Examination');
    }

    public function test_no_rubric_configured_redirects_with_error(): void
    {
        $expert = $this->makeExpert();
        $defense = $this->makeAssignedDefense($expert, 'Final Oral Defense');

        $response = $this->actingAs($expert, 'expert')->get('/instructor/grading/'.$defense->defense_id);

        $response->assertRedirect(route('instructor.grading'));
        $response->assertSessionHas('error');
    }

    public function test_rubric_matches_case_insensitively_against_defense_type(): void
    {
        $expert = $this->makeExpert();
        // Real-world mismatch: panel-assignment uses "Pre-proposal Defense",
        // grading rubrics use "Pre-Proposal Defense".
        $defense = $this->makeAssignedDefense($expert, 'Pre-proposal Defense');
        $this->makeRubric('Pre-Proposal Defense');

        $response = $this->actingAs($expert, 'expert')->get('/instructor/grading/'.$defense->defense_id);

        $response->assertOk();
        $response->assertSee('Presentation');
    }

    public function test_save_draft_persists_partial_scores(): void
    {
        $expert = $this->makeExpert();
        $defense = $this->makeAssignedDefense($expert);
        $rubric = $this->makeRubric();
        $criterion = $rubric->criteria()->first();

        $response = $this->actingAs($expert, 'expert')->postJson(
            "/instructor/grading/{$defense->defense_id}/save",
            ['scores' => [$criterion->criterion_id => 40]]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $grade = PanelGrade::where('defense_id', $defense->defense_id)->where('expert_id', $expert->expert_id)->first();
        $this->assertNotNull($grade);
        $this->assertEquals('draft', $grade->status);
        $this->assertEquals(40.0, (float) $grade->total_score);
    }

    public function test_submit_requires_all_criteria_scored(): void
    {
        $expert = $this->makeExpert();
        $defense = $this->makeAssignedDefense($expert);
        $rubric = $this->makeRubric();
        $criterion = $rubric->criteria()->first();

        $response = $this->actingAs($expert, 'expert')->postJson(
            "/instructor/grading/{$defense->defense_id}/submit",
            ['scores' => [$criterion->criterion_id => 40], 'verdict' => 'approved']
        );

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    // A panelist submits scores and remarks; the verdict is no longer theirs
    // to give. The panel agrees one outcome in a briefing and the chair
    // records it, so requiring each panelist to pick one made three verdicts
    // where the process only ever produces one.
    public function test_submit_does_not_require_a_verdict_from_each_panelist(): void
    {
        $expert = $this->makeExpert();
        $defense = $this->makeAssignedDefense($expert);
        $rubric = $this->makeRubric();

        $scores = [];
        foreach ($rubric->criteria as $c) {
            $scores[$c->criterion_id] = (float) $c->max_points;
        }

        $response = $this->actingAs($expert, 'expert')->postJson(
            "/instructor/grading/{$defense->defense_id}/submit",
            ['scores' => $scores]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('panel_grades', [
            'defense_id' => $defense->defense_id,
            'expert_id' => $expert->expert_id,
            'status' => 'submitted',
        ]);
    }

    public function test_submit_saves_complete_grade_and_marks_submitted(): void
    {
        $expert = $this->makeExpert();
        $defense = $this->makeAssignedDefense($expert);
        $rubric = $this->makeRubric();

        $scores = [];
        $expectedTotal = 0;
        foreach ($rubric->criteria as $c) {
            $scores[$c->criterion_id] = (float) $c->max_points;
            $expectedTotal += (float) $c->max_points;
        }

        $response = $this->actingAs($expert, 'expert')->postJson(
            "/instructor/grading/{$defense->defense_id}/submit",
            ['scores' => $scores, 'verdict' => 'approved', 'remarks' => 'Great job']
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $grade = PanelGrade::where('defense_id', $defense->defense_id)->where('expert_id', $expert->expert_id)->first();
        $this->assertEquals('submitted', $grade->status);
        $this->assertEquals('approved', $grade->verdict);
        $this->assertEquals('Great job', $grade->remarks);
        $this->assertEquals($expectedTotal, (float) $grade->total_score);
        $this->assertNotNull($grade->submitted_at);
    }

    public function test_submit_rejects_score_not_in_criterion_levels(): void
    {
        $expert = $this->makeExpert();
        $defense = $this->makeAssignedDefense($expert);
        $rubric = $this->makeRubric();
        $criterion = $rubric->criteria()->first();

        $response = $this->actingAs($expert, 'expert')->postJson(
            "/instructor/grading/{$defense->defense_id}/save",
            ['scores' => [$criterion->criterion_id => 999]]
        );

        $response->assertStatus(422);
    }

    public function test_index_shows_submitted_status_after_grading(): void
    {
        $expert = $this->makeExpert();
        $defense = $this->makeAssignedDefense($expert);
        $rubric = $this->makeRubric();

        $scores = [];
        foreach ($rubric->criteria as $c) {
            $scores[$c->criterion_id] = (float) $c->max_points;
        }

        $this->actingAs($expert, 'expert')->postJson(
            "/instructor/grading/{$defense->defense_id}/submit",
            ['scores' => $scores, 'verdict' => 'approved']
        );

        $response = $this->actingAs($expert, 'expert')->get('/instructor/grading');

        $response->assertOk();
        $response->assertSee('Submitted');
    }
}
