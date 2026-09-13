<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertRole;
use App\Models\Milestone;
use App\Models\MilestoneItem;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A team's checklist progress is for the team, its section's CaPSA, and the
 * adviser and consultant it chose - not for its grammarian or its panel.
 */
class ChecklistProgressVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $leader = Student::factory()->withCompleteProfile()->create(['course' => 'BSIT', 'year_section' => '3rd · A']);
        $this->team = Team::create(['team_name' => 'Tracked Team', 'leader_id' => $leader->student_id, 'team_status' => 'Active']);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $this->team->team_id]);

        $milestone = Milestone::create([
            'team_id' => $this->team->team_id, 'title' => 'Capstone 1', 'status' => 'todo',
            'due_date' => now()->subWeek()->toDateString(),
        ]);
        $milestone->items()->create(['title' => 'Signed Project Team Assignment Form', 'kind' => MilestoneItem::FORM]);
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

    public function test_the_adviser_and_consultant_see_the_teams_progress(): void
    {
        foreach (['adviser', 'consultant'] as $role) {
            $this->actingAs($this->engage($role), 'expert')->get(route('instructor.teams'))
                ->assertOk()
                ->assertSee('1 overdue');
        }
    }

    public function test_the_grammarian_does_not(): void
    {
        $this->actingAs($this->engage('grammarian'), 'expert')->get(route('instructor.teams'))
            ->assertOk()
            ->assertSee('Tracked Team')
            ->assertDontSee('1 overdue');
    }
}
