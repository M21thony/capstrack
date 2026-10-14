<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\Student;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar folds to its icons on a laptop - by default below 1280px, and
 * on the grading sheet at any width - with a button to fold or open it.
 */
class SidebarRailTest extends TestCase
{
    use RefreshDatabase;

    private function expert(): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => 'rail.expert@gmail.com',
            'first_name' => 'Rail',
            'last_name' => 'Expert',
            'approved_status' => true,
        ]);
    }

    public function test_an_ordinary_expert_page_folds_the_menu_only_on_a_narrow_screen(): void
    {
        $response = $this->actingAs($this->expert(), 'expert')->get(route('instructor.grading'));

        $response->assertOk()
            ->assertSee('var focus = false;', false)
            ->assertSee("'sidebarRail'", false)
            ->assertSee('sb-toggle', false);
    }

    public function test_the_grading_sheet_opens_with_the_menu_folded(): void
    {
        $expert = $this->expert();
        $team = Team::create(['team_name' => 'Rail Team', 'project_title' => 'Rails']);
        $defense = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Pre-proposal Defense',
            'schedule_date' => now()->addDay(),
        ]);
        DefensePanel::create([
            'defense_id' => $defense->defense_id,
            'expert_id' => $expert->expert_id,
            'panel_role' => 'Panel Chair',
            'created_at' => now(),
        ]);
        $rubric = GradingRubric::create([
            'rubric_name' => 'Rail Rubric',
            'rubric_type' => 'Pre-proposal Defense',
            'total_points' => 100,
            'is_active' => true,
        ]);
        $rubric->criteria()->create([
            'criterion_name' => 'Presentation',
            'category' => 'Oral',
            'category_max_points' => 100,
            'max_points' => 100,
            'score_levels' => json_encode([['label' => 'Excellent', 'score' => 100, 'desc' => 'Great']]),
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($expert, 'expert')->get(route('instructor.grading.show', $defense->defense_id));

        $response->assertOk()
            ->assertSee('var focus = true;', false)
            ->assertSee("'sidebarRailFocus'", false);
    }

    public function test_the_student_portal_has_the_same_folding_menu(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();

        $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('var focus = false;', false)
            ->assertSee('sb-toggle', false);
    }
}
