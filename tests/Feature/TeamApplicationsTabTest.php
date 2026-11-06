<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Team Assignments became the Team Applications tab of Teams, the same for an
 * expert and a CaPSA; the To do keeps the waiting ones as a shortcut.
 */
class TeamApplicationsTabTest extends TestCase
{
    use RefreshDatabase;

    private int $students = 0;

    private function expert(int $maxTeams = 3, string ...$roles): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'max_teams' => $maxTeams]);
        foreach ($roles ?: ['adviser', 'consultant'] as $role) {
            $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);
        }

        return $expert;
    }

    private function application(CapstoneExpert $expert, string $teamName, string $role, string $status = 'pending', array $extra = []): TeamExpertAssignment
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'email' => 'leader'.(++$this->students).'@my.cspc.edu.ph',
            'f_name' => 'Kenn', 'l_name' => 'Aurillas',
        ]);
        $team = Team::create([
            'team_name' => $teamName, 'team_status' => 'Active', 'leader_id' => $leader->student_id,
            'project_title' => 'A model that grades essays', 'tags' => [],
        ]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)
                ->where('role_id', Role::where('role_name', $role)->value('role_id'))
                ->value('expert_role_id'),
            'status' => $status,
            'message' => 'Good day po! Please be our '.$role.'.',
        ] + $extra);
    }

    public function test_the_tab_lists_who_is_waiting_and_who_was_declined(): void
    {
        $expert = $this->expert();
        $waiting = $this->application($expert, 'Team Codebreakers', 'adviser');
        $declined = $this->application($expert, 'Team BlockSlash', 'consultant', 'rejected', ['rejection_reason' => 'My slots for consultants are taken this term.']);
        $this->application($expert, 'Team Accepted', 'adviser', 'approved');

        $html = $this->actingAs($expert, 'expert')->get(route('instructor.teams', ['tab' => 'applications']))
            ->assertOk()
            ->assertSeeInOrder(['class="hub-tabs"', 'To do', 'Team Applications', 'Teams'], false)
            ->assertSee('<section class="hub-pane" id="tm-apps-tab" role="tabpanel" >', false)
            // Waiting: read it, decline with a reason, or accept.
            ->assertSeeInOrder(['id="tm-apps-waiting"', 'Team Codebreakers', 'Adviser', 'data-read="application" data-read-id="'.$waiting->assignment_id.'"',
                'data-value="rejected" data-note="reason" data-min="20"', 'data-value="approved"'], false)
            // Declined, with the reason given, and a way to clear it.
            ->assertSeeInOrder(['id="tm-apps-declined" hidden', 'Team BlockSlash', 'Your reason: My slots for consultants are taken this term.',
                'data-method="DELETE"', 'data-url="'.route('instructor.assignments.remove', $declined->assignment_id).'"'], false)
            // An accepted team is on the Teams tab, not listed here again.
            ->assertDontSee('Accept Team Team Accepted')
            // The slots: one of three taken.
            ->assertSee('<b>1 of 3</b> used', false)
            ->assertSee('2 open')
            ->getContent();

        // What the application window shows beside the document.
        preg_match('#<script type="application/json" id="tm-apps-data">(.*?)</script>#s', $html, $m);
        $data = json_decode($m[1], true);
        $this->assertSame('Team Codebreakers', $data['details'][$waiting->assignment_id]['team']);
        $this->assertSame('Good day po! Please be our adviser.', $data['details'][$waiting->assignment_id]['message']);
        $this->assertSame('pending', $data['details'][$waiting->assignment_id]['status']);
        $this->assertSame(1, $data['usedSlots']);
        $this->assertSame(3, $data['maxSlots']);
    }

    public function test_the_to_do_keeps_the_waiting_ones_as_a_shortcut(): void
    {
        $expert = $this->expert();
        $waiting = $this->application($expert, 'Team Codebreakers', 'adviser');

        $this->actingAs($expert, 'expert')->get(route('instructor.teams'))
            ->assertOk()
            // The slots at the top of Teams, whichever tab is open.
            ->assertSeeInOrder(['class="hub-head"', '<b>0 of 3</b> used', 'class="hub-tabs"'], false)
            ->assertSeeInOrder(['class="group-h group-apps"', 'Team applications',
                'href="'.route('instructor.teams', ['tab' => 'applications']).'" data-hub-tab="applications">See all',
                'Invited as adviser', 'Team Codebreakers', 'Read application'], false)
            ->assertSee('data-read-id="'.$waiting->assignment_id.'"', false);
    }

    public function test_accepting_and_declining_from_the_tab_reach_the_same_endpoint(): void
    {
        $expert = $this->expert();
        $accept = $this->application($expert, 'Team Codebreakers', 'adviser');
        $decline = $this->application($expert, 'Team BlockSlash', 'consultant');

        $this->actingAs($expert, 'expert')
            ->patchJson(route('instructor.assignments.update', $decline->assignment_id), ['status' => 'rejected', 'reason' => 'too short'])
            ->assertStatus(422);
        $this->actingAs($expert, 'expert')
            ->patchJson(route('instructor.assignments.update', $decline->assignment_id), ['status' => 'rejected', 'reason' => 'My consultant slots are full for this term.'])
            ->assertOk()->assertJson(['success' => true]);
        $this->actingAs($expert, 'expert')
            ->patchJson(route('instructor.assignments.update', $accept->assignment_id), ['status' => 'approved'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame('rejected', $decline->fresh()->status);
        $this->assertSame('approved', $accept->fresh()->status);

        $this->actingAs($expert, 'expert')->get(route('instructor.teams', ['tab' => 'applications']))
            ->assertOk()
            ->assertSee('No teams are waiting for you')
            ->assertSee('<b>1 of 3</b> used', false);
    }

    public function test_the_old_address_and_the_sidebar_point_at_teams(): void
    {
        $expert = $this->expert();
        $this->application($expert, 'Team Codebreakers', 'adviser');

        $this->actingAs($expert, 'expert')->get(route('instructor.assignments'))
            ->assertRedirect(route('instructor.teams', ['tab' => 'applications']));

        // One Teams item, with the new application on its badge.
        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertDontSee('Team Assignments')
            ->assertSeeInOrder(['data-icon="team"', 'Teams', '<span class="nav-badge red">1</span>'], false);

        // Seen once Teams is opened.
        $this->actingAs($expert, 'expert')->get(route('instructor.teams'))->assertOk();
        $this->actingAs($expert, 'expert')->get(route('instructor.dashboard'))
            ->assertOk()
            ->assertDontSee('<span class="nav-badge red">1</span>', false);
    }

    public function test_a_capsa_has_the_same_tab_on_team_management(): void
    {
        $capsa = $this->makeCapsa($this->expert(2, 'adviser'));
        $capsa->update(['sections' => ['BSIT-3A']]);
        $this->application($capsa, 'Team Codebreakers', 'adviser');

        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management', ['tab' => 'applications']))
            ->assertOk()
            ->assertSee('<section class="hub-pane" id="tm-apps-tab" role="tabpanel" >', false)
            ->assertSee('Team Codebreakers')
            ->assertSee('<b>0 of 2</b> used', false)
            // The name is in the top bar; the hub does not say it again.
            ->assertDontSee('<h1>Teams</h1>', false);

        $this->actingAs($capsa, 'expert')->get(route('capsa.dashboard'))
            ->assertOk()
            ->assertDontSee('Team Assignments');
    }

    public function test_a_capsa_no_team_can_apply_to_has_no_applications_tab(): void
    {
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'max_teams' => 4]));
        $capsa->update(['sections' => ['BSIT-3A']]);

        $this->actingAs($capsa, 'expert')->get(route('capsa.team_management'))
            ->assertOk()
            ->assertDontSee('data-hub-tab="applications"', false)
            ->assertDontSee('<span class="slots-l">Your slots</span>', false);
    }
}
