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
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A team cannot drop an expert who already took them on.
 *
 * Team1 picks Expert1 as adviser, Expert1 accepts, and Team1 later decides
 * they want someone else. They may ask - but only Expert1 can let them go.
 * The expert can also end it themselves from their Approved tab, which
 * answers any request that was waiting.
 */
class ExpertRemovalRequestTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Our adviser moved to another campus this semester.';

    /** @return array{0: Student, 1: Team, 2: TeamExpertAssignment, 3: CapstoneExpert, 4: Student} */
    private function teamWithAdviser(string $status = 'approved'): array
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $member = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $team = Team::create(['team_name' => 'Bulbasaur', 'team_status' => 'Active', 'is_confirmed' => true, 'leader_id' => $leader->student_id]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        $role = Role::firstOrCreate(['role_name' => 'adviser']);
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $adviser->roles()->attach($role->role_id);

        $assignment = TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $adviser->expert_id,
            'expert_role_id' => DB::table('expert_roles')->where('expert_id', $adviser->expert_id)->value('expert_role_id'),
            'status' => $status,
        ]);

        return [$leader, $team, $assignment, $adviser, $member];
    }

    private function askToRemove(Student $leader, TeamExpertAssignment $assignment, string $reason = self::REASON)
    {
        return $this->actingAs($leader, 'web')
            ->deleteJson(route('api.student.assignments.destroy', $assignment), ['reason' => $reason]);
    }

    private function decide(CapstoneExpert $expert, ExpertRemovalRequest $removal, array $data)
    {
        return $this->actingAs($expert, 'expert')
            ->from(route('instructor.assignments'))
            ->patch(route('instructor.removal-requests.update', $removal), $data + ['removal_id' => $removal->id]);
    }

    public function test_a_team_cannot_remove_an_approved_expert_it_can_only_ask(): void
    {
        [$leader, $team, $assignment, $adviser] = $this->teamWithAdviser();

        $this->askToRemove($leader, $assignment)->assertOk()->assertJson(['success' => true]);

        // Still their adviser: asking is all the team gets to do.
        $this->assertDatabaseHas('capstone_expert_application', ['assignment_id' => $assignment->assignment_id, 'status' => 'approved']);
        $removal = ExpertRemovalRequest::pending()->where('team_id', $team->team_id)->sole();
        $this->assertSame((int) $adviser->expert_id, (int) $removal->expert_id);

        // Asking twice does not stack requests.
        $this->askToRemove($leader, $assignment)->assertStatus(422);
        // And the expert is owed a real reason.
        ExpertRemovalRequest::query()->delete();
        $this->askToRemove($leader, $assignment, 'no')->assertStatus(422);
    }

    public function test_the_leader_sees_the_request_waiting_instead_of_a_remove_button(): void
    {
        [$leader, , $assignment, $adviser] = $this->teamWithAdviser();

        $this->actingAs($leader, 'web')->get(route('student.experts'))
            ->assertOk()
            ->assertSee('class="yx-x"', false)
            ->assertDontSee('Removal requested');

        $this->askToRemove($leader, $assignment)->assertOk();

        $this->actingAs($leader, 'web')->get(route('student.experts'))
            ->assertOk()
            ->assertSee('<span class="yx-pending" title="Waiting on '.$adviser->name.' to decide">Removal requested</span>', false)
            ->assertDontSee('class="yx-x"', false);
    }

    public function test_only_the_leader_can_ask(): void
    {
        [, , $assignment, , $member] = $this->teamWithAdviser();

        $this->askToRemove($member, $assignment)->assertForbidden();

        $this->assertSame(0, ExpertRemovalRequest::count());
    }

    public function test_the_request_waits_on_the_expert_it_names(): void
    {
        [$leader, , $assignment, $adviser] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::sole();

        $this->actingAs($adviser, 'expert')
            ->get(route('instructor.assignments'))
            ->assertOk()
            ->assertSee('Bulbasaur asks you to let them go')
            ->assertSee(self::REASON)
            ->assertSee(route('instructor.removal-requests.update', $removal), false);

        // Nobody else is shown it.
        $someoneElse = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->actingAs($someoneElse, 'expert')
            ->get(route('instructor.assignments'))
            ->assertOk()
            ->assertDontSee('asks you to let them go');
    }

    public function test_the_expert_releasing_the_team_removes_the_assignment(): void
    {
        [$leader, , $assignment, $adviser, $member] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::sole();

        $this->decide($adviser, $removal, ['decision' => 'release'])
            ->assertRedirect(route('instructor.assignments'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('capstone_expert_application', ['assignment_id' => $assignment->assignment_id]);
        $removal->refresh();
        $this->assertSame(ExpertRemovalRequest::APPROVED, $removal->status);
        $this->assertSame((int) $adviser->expert_id, (int) $removal->decided_by);

        // The whole team hears it, not just the leader who asked.
        foreach ([$leader, $member] as $student) {
            $this->assertDatabaseHas('notifications', ['student_id' => $student->student_id, 'title' => 'Expert released your team']);
        }
    }

    public function test_the_expert_declining_needs_a_reason_and_keeps_the_team(): void
    {
        [$leader, , $assignment, $adviser] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::sole();

        $this->decide($adviser, $removal, ['decision' => 'decline'])
            ->assertSessionHasErrorsIn('removal', 'note');
        $this->assertTrue($removal->fresh()->isPending());

        $this->decide($adviser, $removal, ['decision' => 'decline', 'note' => 'Finish Chapter 1 with me first.'])
            ->assertRedirect(route('instructor.assignments'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('capstone_expert_application', ['assignment_id' => $assignment->assignment_id, 'status' => 'approved']);
        $this->assertSame(ExpertRemovalRequest::DECLINED, $removal->fresh()->status);
        $this->assertDatabaseHas('notifications', ['student_id' => $leader->student_id, 'title' => 'Expert is staying on']);
    }

    public function test_another_expert_cannot_decide(): void
    {
        [$leader, , $assignment] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $someoneElse = CapstoneExpert::factory()->create(['approved_status' => true]);

        $this->decide($someoneElse, ExpertRemovalRequest::sole(), ['decision' => 'release'])->assertNotFound();

        $this->assertDatabaseHas('capstone_expert_application', ['assignment_id' => $assignment->assignment_id, 'status' => 'approved']);
        $this->assertTrue(ExpertRemovalRequest::sole()->isPending());
    }

    public function test_a_decided_request_cannot_be_decided_again(): void
    {
        [$leader, , $assignment, $adviser] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();
        $removal = ExpertRemovalRequest::sole();

        $this->decide($adviser, $removal, ['decision' => 'decline', 'note' => 'Finish Chapter 1 with me first.']);
        $this->decide($adviser, $removal, ['decision' => 'release'])->assertRedirect(route('instructor.assignments'));

        // The decline stands; the second answer did not remove anybody.
        $this->assertSame(ExpertRemovalRequest::DECLINED, $removal->fresh()->status);
        $this->assertDatabaseHas('capstone_expert_application', ['assignment_id' => $assignment->assignment_id]);
    }

    /** The expert ending it from their Approved tab answers the team's request. */
    public function test_the_expert_removing_the_team_directly_closes_the_request(): void
    {
        [$leader, , $assignment, $adviser] = $this->teamWithAdviser();
        $this->askToRemove($leader, $assignment)->assertOk();

        $this->actingAs($adviser, 'expert')
            ->deleteJson(route('instructor.assignments.remove', $assignment->assignment_id), [
                'reason' => 'I am taking leave next term.',
                'confirm' => 'remove',
            ])
            ->assertOk();

        $this->assertDatabaseMissing('capstone_expert_application', ['assignment_id' => $assignment->assignment_id]);
        $this->assertSame(ExpertRemovalRequest::APPROVED, ExpertRemovalRequest::sole()->status);
    }

    /** Requests filed while the CaPSA decided these now wait on the expert. */
    public function test_the_capsa_no_longer_decides(): void
    {
        [, $team, $assignment, $adviser, $member] = $this->teamWithAdviser();

        $this->assertFalse(Route::has('capsa.expert_removal_requests.update'));

        // A request left over from before, still pending.
        $leftover = ExpertRemovalRequest::create([
            'team_id' => $team->team_id,
            'assignment_id' => $assignment->assignment_id,
            'expert_id' => $adviser->expert_id,
            'requested_by' => $member->student_id,
            'reason' => self::REASON,
            'status' => ExpertRemovalRequest::PENDING,
        ]);

        $this->actingAs($adviser, 'expert')
            ->get(route('instructor.assignments'))
            ->assertOk()
            ->assertSee(route('instructor.removal-requests.update', $leftover), false);
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
            ->assertDontSee('Removal requested')
            ->assertSee('to let your team go');

        // A withdrawn request is not the expert's to decide any more.
        $this->assertSame(0, ExpertRemovalRequest::pending()->count());
    }

    public function test_taking_a_request_back_is_the_leaders_and_only_while_it_waits(): void
    {
        [$leader, $team, $assignment, $adviser] = $this->teamWithAdviser();
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

        // Once the expert has decided there is nothing left to take back.
        $this->decide($adviser, $removal, ['decision' => 'release'])
            ->assertSessionHasNoErrors();

        $this->actingAs($leader, 'web')
            ->deleteJson(route('api.student.expert-removals.destroy', $removal))
            ->assertStatus(409);
    }
}
