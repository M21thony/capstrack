<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * A team's milestone progress is for the team, its section's CaPSA, and the
 * adviser and consultant it chose - not for its grammarian or its panel.
 */
class ChecklistProgressVisibilityTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->team] = $this->teamInSection('Tracked Team');
        $checklist = $this->emptyChecklist($this->capsaFor());
        $this->addDeliverable($checklist, ['title' => 'Signed Project Team Assignment Form', 'due_date' => now()->subWeek()->toDateString()]);
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
            $expert = $this->engage($role);

            $this->actingAs($expert, 'expert')->get(route('instructor.teams'))
                ->assertOk()
                ->assertSee('1 overdue');

            $this->actingAs($expert, 'expert')->get(route('instructor.progress'))
                ->assertOk()
                ->assertSee('Tracked Team')
                ->assertSee('Signed Project Team Assignment Form');
        }
    }

    public function test_the_grammarian_does_not(): void
    {
        $grammarian = $this->engage('grammarian');

        $this->actingAs($grammarian, 'expert')->get(route('instructor.teams'))
            ->assertOk()
            ->assertSee('Tracked Team')
            ->assertDontSee('1 overdue');

        $this->actingAs($grammarian, 'expert')->get(route('instructor.progress'))
            ->assertOk()
            ->assertDontSee('Tracked Team');
    }

    /** The adviser's tracker is read-only: there is nothing on it to check. */
    public function test_the_advisers_tracker_offers_no_checks(): void
    {
        $this->actingAs($this->engage('adviser'), 'expert')->get(route('instructor.progress'))
            ->assertOk()
            ->assertSee('data-mode="adviser"', false)
            ->assertDontSee('/checks/', false);
    }
}
