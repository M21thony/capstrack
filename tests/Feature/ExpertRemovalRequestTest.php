<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\ExpertRemovalRequest;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** A team needs its CaPSA's permission to remove an expert who already took them on. */
class ExpertRemovalRequestTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Our adviser moved to another campus this semester.';

    /** @return array{0: Student, 1: Team, 2: TeamExpertAssignment, 3: CapstoneExpert} */
    private function teamWithAdviser(string $status = 'approved'): array
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $team = Team::create(['team_name' => 'Bulbasaur', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        $role = Role::firstOrCreate(['role_name' => 'adviser']);
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $adviser->roles()->attach($role->role_id);

        $assignment = TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $adviser->expert_id,
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $adviser->expert_id)->value('expert_role_id'),
            'status' => $status,
        ]);

        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]));

        return [$leader, $team, $assignment, $capsa];
    }

    private function askToRemove(Student $leader, TeamExpertAssignment $assignment, string $reason = self::REASON)
    {
        return $this->actingAs($leader, 'web')
            ->deleteJson(route('api.student.assignments.destroy', $assignment), ['reason' => $reason]);
    }

    public function test_removing_an_approved_expert_sends_a_request_and_keeps_the_expert(): void
    {
        [$leader, $team, $assignment] = $this->teamWithAdviser();

        $this->askToRemove($leader, $assignment)->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('capstone_expert_application', ['assignment_id' => $assignment->assignment_id, 'status' => 'approved']);
        $this->assertSame(1, ExpertRemovalRequest::pending()->where('team_id', $team->team_id)->count());

        // Asking twice does not stack requests.
        $this->askToRemove($leader, $assignment)->assertStatus(422);
        // A reason the CaPSA can decide on.
        ExpertRemovalRequest::query()->delete();
        $this->askToRemove($leader, $assignment, 'no')->assertStatus(422);
    }

    /** The dialog says whose CaPSA decides, and a waiting request says so on the expert. */
    public function test_the_experts_page_names_the_sections_capsa_and_flags_a_waiting_change(): void
    {
        [$leader, , $assignment] = $this->teamWithAdviser();

        // Before asking: the change dialog carries the section CaPSA line.
        $this->actingAs($leader, 'web')
            ->get(route('student.experts'))
            ->assertOk()
            ->assertSee('change-expert-modal', false)
            ->assertSee('Request an Expert Change')
            ->assertSee('Decided by the CaPSA for your section: BSIT-3A');

        // After asking: the badge replaces the button.
        $this->askToRemove($leader, $assignment)->assertOk();

        $this->actingAs($leader, 'web')
            ->get(route('student.experts'))
            ->assertOk()
            ->assertSee('Cancel request')
            // The change button is gone; only the cancel action marks the expert.
            ->assertDontSee('Ask your section');
    }

    public function test_the_capsa_approving_removes_the_expert(): void
    {
        [$leader, $team, $assignment, $capsa] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::first();

        // It waits on the CaPSA's Leave Requests tab.
        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.team_management', ['tab' => 'leave_requests']))
            ->assertOk()
            ->assertSee('Expert change')
            ->assertSee(route('capsa.expert_removal_requests.update', $removal), false);

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', $removal), ['status' => 'approved'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseMissing('capstone_expert_application', ['assignment_id' => $assignment->assignment_id]);
        $this->assertSame('approved', $removal->fresh()->status);
        $this->assertDatabaseHas('notifications', ['student_id' => $leader->student_id, 'title' => 'Expert change approved']);
    }

    public function test_the_capsa_declining_needs_a_reason_and_keeps_the_expert(): void
    {
        [$leader, , $assignment, $capsa] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::first();

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', $removal), ['status' => 'declined'])
            ->assertStatus(422);

        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', $removal), ['status' => 'declined', 'note' => 'Finish Chapter 1 with them first.'])
            ->assertOk();

        $this->assertDatabaseHas('capstone_expert_application', ['assignment_id' => $assignment->assignment_id, 'status' => 'approved']);
        $this->assertSame('declined', $removal->fresh()->status);
    }

    public function test_a_capsa_of_another_section_cannot_decide(): void
    {
        [$leader, , $assignment] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $other = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIS-4B']]));

        $this->actingAs($other, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', ExpertRemovalRequest::first()), ['status' => 'approved'])
            ->assertForbidden();

        $this->assertDatabaseHas('capstone_expert_application', ['assignment_id' => $assignment->assignment_id]);
    }

    public function test_an_application_the_expert_has_not_answered_is_still_withdrawn_directly(): void
    {
        [$leader, , $assignment] = $this->teamWithAdviser('pending');

        $this->askToRemove($leader, $assignment)->assertOk();

        $this->assertDatabaseMissing('capstone_expert_application', ['assignment_id' => $assignment->assignment_id]);
        $this->assertSame(0, ExpertRemovalRequest::count());
    }

    /** Undo: the team takes back a request its CaPSA has not decided on yet. */
    public function test_the_team_can_take_back_a_change_request_the_capsa_has_not_decided(): void
    {
        [$leader, , $assignment] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::first();

        $this->actingAs($leader, 'web')
            ->deleteJson(route('api.student.expert-removals.destroy', $removal))
            ->assertOk()
            ->assertJson(['success' => true]);

        // Back to where it started: the expert stays, and the team can apply
        // the change again rather than being locked out of it.
        $this->assertSame(ExpertRemovalRequest::WITHDRAWN, $removal->fresh()->status);
        $this->assertDatabaseHas('capstone_expert_application', [
            'assignment_id' => $assignment->assignment_id,
            'status' => 'approved',
        ]);

        $this->actingAs($leader, 'web')
            ->get(route('student.experts'))
            ->assertOk()
            ->assertDontSee('Cancel request')
            ->assertSee('Ask your section');

        // A withdrawn request is not the CaPSA's to decide any more.
        $this->assertSame(0, ExpertRemovalRequest::pending()->count());
    }

    public function test_taking_a_request_back_is_the_leaders_and_only_while_it_waits(): void
    {
        [$leader, $team, $assignment, $capsa] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::first();

        // A member who is not the leader cannot take it back.
        $member = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        $this->actingAs($member, 'web')
            ->deleteJson(route('api.student.expert-removals.destroy', $removal))
            ->assertForbidden();
        $this->assertSame(ExpertRemovalRequest::PENDING, $removal->fresh()->status);

        // Another team's request is not theirs to see at all.
        $outsider = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · B',
        ]);
        $otherTeam = Team::create(['team_name' => 'Charmander', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $outsider->student_id]);
        StudentSchoolYear::create(['student_id' => $outsider->student_id, 'team_id' => $otherTeam->team_id]);

        $this->actingAs($outsider, 'web')
            ->deleteJson(route('api.student.expert-removals.destroy', $removal))
            ->assertNotFound();
        $this->assertSame(ExpertRemovalRequest::PENDING, $removal->fresh()->status);

        // Once the CaPSA has decided there is nothing left to take back.
        $this->actingAs($capsa, 'expert')
            ->patchJson(route('capsa.expert_removal_requests.update', $removal), ['status' => 'approved'])
            ->assertOk();

        $this->actingAs($leader, 'web')
            ->deleteJson(route('api.student.expert-removals.destroy', $removal))
            ->assertStatus(409);
    }
}
