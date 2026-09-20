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

        // A date: only a scheduled defense is on the grading list.
        $schedule = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => $defenseType,
            'schedule_date' => now()->addDay()->setTime(8, 0),
        ]);

        DefensePanel::create([
            'defense_id' => $schedule->defense_id,
            'expert_id' => $expert->expert_id,
            'panel_role' => $panelRole,
            'created_at' => now(),
        ]);

        return $schedule;
    }

    public function test_expanding_a_team_lists_its_defenses(): void
    {
        // The list of a team's attempts sits at /grading/team-defenses, which
        // reads as a defense id to the route right below it. Registered the
        // other way round, every expand returns a 404 instead of the rows.
        $expert = $this->makeExpert();
        $defense = $this->makeAssignedDefense($expert);

        $response = $this->actingAs($expert, 'expert')
            ->getJson('/instructor/grading/team-defenses?team=Test Team');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('defenses.0.defense_id', $defense->defense_id);
    }

    public function test_grading_index_lists_teams_expert_is_assigned_as_panel(): void
    {
        $expert = $this->makeExpert();
        $this->makeAssignedDefense($expert);

        $response = $this->actingAs($expert, 'expert')->get('/instructor/grading');

        $response->assertOk();
        $response->assertSee('Test Team');
        $response->assertSee('Not started');
    }

    /** An archived rubric is out of use - even one whose active flag was left on. */
    public function test_an_archived_rubric_is_never_used_for_grading(): void
    {
        $expert = $this->makeExpert();
        $defense = $this->makeAssignedDefense($expert);
        $rubric = $this->makeRubric();
        $rubric->update(['is_archived' => true, 'is_active' => true]);

        $this->actingAs($expert, 'expert')
            ->get('/instructor/grading/'.$defense->defense_id)
            ->assertRedirect(route('instructor.grading'));

        $this->actingAs($expert, 'expert')
            ->patchJson('/instructor/grading/'.$defense->defense_id, ['scores' => []])
            ->assertStatus(422);

        // With a current rubric beside it, the current one is what panels see.
        $current = $this->makeRubric();
        $current->update(['rubric_name' => 'Current Rubric']);

        $this->actingAs($expert, 'expert')
            ->get('/instructor/grading/'.$defense->defense_id)
            ->assertOk()
            ->assertSee('Current Rubric');
    }

    public function test_editing_an_archived_rubric_does_not_put_it_back_into_use(): void
    {
        $admin = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]));
        $rubric = $this->makeRubric();
        $rubric->update(['is_archived' => true, 'is_active' => false]);

        $this->actingAs($admin, 'expert')
            ->putJson('/api/admin/grading-rubrics/'.$rubric->rubric_id, [
                'rubric_name' => 'Renamed', 'rubric_type' => 'pre_proposal', 'total_points' => 100,
            ])
            ->assertOk();

        $this->assertFalse($rubric->fresh()->is_active);
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

        $response = $this->actingAs($expert, 'expert')->putJson(
            "/instructor/grading/{$defense->defense_id}",
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

        $response = $this->actingAs($expert, 'expert')->patchJson(
            "/instructor/grading/{$defense->defense_id}",
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

        $response = $this->actingAs($expert, 'expert')->patchJson(
            "/instructor/grading/{$defense->defense_id}",
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

        $response = $this->actingAs($expert, 'expert')->patchJson(
            "/instructor/grading/{$defense->defense_id}",
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

    // The chair is normally the last to score, so their own submission is what
    // completes the panel. The page keeps them there to record the decision
    // instead of sending them to the team list to reopen the same defense.
    public function test_the_chair_is_told_when_their_own_submission_completes_the_panel(): void
    {
        $chair = $this->makeExpert();
        $member = $this->makeExpert('kenn.aurillas@gmail.com');
        $defense = $this->makeAssignedDefense($chair);
        DefensePanel::create([
            'defense_id' => $defense->defense_id,
            'expert_id' => $member->expert_id,
            'panel_role' => 'Panel Member 1',
            'created_at' => now(),
        ]);
        $rubric = $this->makeRubric();

        $scores = [];
        foreach ($rubric->criteria as $c) {
            $scores[$c->criterion_id] = (float) $c->max_points;
        }

        // The member goes first: one still to come, and it is not theirs to record.
        $this->actingAs($member, 'expert')
            ->patchJson("/instructor/grading/{$defense->defense_id}", ['scores' => $scores])
            ->assertOk()
            ->assertJsonPath('data.can_record_outcome', false);

        $this->actingAs($chair, 'expert')
            ->patchJson("/instructor/grading/{$defense->defense_id}", ['scores' => $scores])
            ->assertOk()
            ->assertJsonPath('data.can_record_outcome', true);
    }

    // The last one in, but not the chair: the decision is still not theirs.
    public function test_a_member_completing_the_panel_is_not_offered_the_decision(): void
    {
        $chair = $this->makeExpert();
        $member = $this->makeExpert('kenn.aurillas@gmail.com');
        $defense = $this->makeAssignedDefense($chair);
        DefensePanel::create([
            'defense_id' => $defense->defense_id,
            'expert_id' => $member->expert_id,
            'panel_role' => 'Panel Member 1',
            'created_at' => now(),
        ]);
        $rubric = $this->makeRubric();

        $scores = [];
        foreach ($rubric->criteria as $c) {
            $scores[$c->criterion_id] = (float) $c->max_points;
        }

        $this->actingAs($chair, 'expert')
            ->patchJson("/instructor/grading/{$defense->defense_id}", ['scores' => $scores])
            ->assertOk();

        $this->actingAs($member, 'expert')
            ->patchJson("/instructor/grading/{$defense->defense_id}", ['scores' => $scores])
            ->assertOk()
            ->assertJsonPath('data.can_record_outcome', false);
    }

    public function test_submit_rejects_score_not_in_criterion_levels(): void
    {
        $expert = $this->makeExpert();
        $defense = $this->makeAssignedDefense($expert);
        $rubric = $this->makeRubric();
        $criterion = $rubric->criteria()->first();

        $response = $this->actingAs($expert, 'expert')->putJson(
            "/instructor/grading/{$defense->defense_id}",
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

        $this->actingAs($expert, 'expert')->patchJson(
            "/instructor/grading/{$defense->defense_id}",
            ['scores' => $scores, 'verdict' => 'approved']
        );

        $response = $this->actingAs($expert, 'expert')->get('/instructor/grading');

        $response->assertOk();
        $response->assertSee('Submitted');
    }
}
