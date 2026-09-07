<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorAssignmentApproveButtonTest extends TestCase
{
    use RefreshDatabase;

    // Regression test: a team name containing an apostrophe (e.g. "JOLINA's
    // TEAM") was interpolated raw into a single-quoted onclick="..."
    // attribute (onclick="openApproveModal(7, 'JOLINA's TEAM', ...)"). The
    // apostrophe prematurely closed the JS string literal, so the browser
    // couldn't compile the handler at all - clicking Approve/Reject/Remove
    // silently did nothing. Fixed by passing only the assignment id and
    // looking the rest up from the JSON-encoded assignmentData object,
    // matching the pattern openTeamModal already used safely.
    public function test_a_team_name_with_an_apostrophe_does_not_break_the_approve_button_markup(): void
    {
        $consultantRole = Role::create(['role_name' => 'consultant']);

        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'max_teams' => 4,
        ]);
        $expert->roles()->attach($consultantRole->role_id);

        $team = Team::create([
            'team_name' => "JOLINA's TEAM",
            'project_title' => 'A Capstone Project',
            'tags' => [],
            'team_status' => 'Active',
            'is_confirmed' => true,
        ]);

        $assignment = TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($expert, 'expert')->get(route('instructor.assignments'));

        $response->assertOk();

        // The unsafe pattern must never appear again - the raw team name
        // embedded as a second argument inside the onclick attribute.
        $response->assertDontSee("openApproveModal({$assignment->assignment_id}, '", false);
        $response->assertDontSee("openRejectModal({$assignment->assignment_id}, '", false);

        // Only the id is passed now - the rest is looked up client-side
        // from the safely JSON-encoded assignmentData object. Since the
        // redesign the row selects rather than decides, so the id travels
        // through selectAssignment and the panel calls the same two
        // functions with it; what must never come back is a team name
        // interpolated into an attribute, which is what the two assertions
        // above pin down.
        $response->assertSee("selectAssignment({$assignment->assignment_id})", false);
        $response->assertDontSee("selectAssignment({$assignment->assignment_id}, '", false);
        $response->assertSee('openApproveModal(id)', false);
        $response->assertSee('openRejectModal(id)', false);

        // The team name still reaches the page - just through the safe
        // Js::from()-encoded assignmentData object, not raw string
        // concatenation into an inline event handler.
        $response->assertSee('JOLINA');
    }
}
