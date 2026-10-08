<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\CapstonePreProposal;
use App\Models\DuplicationCheck;
use App\Models\ExpertRole;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentExpertApplyTest extends TestCase
{
    use RefreshDatabase;

    /** A leader and team; by default their pre-proposal is already recommended, since nothing can be applied for without one. */
    private function makeLeaderWithTeam(bool $recommended = true): array
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph',
            'f_name' => 'Team',
            'l_name' => 'Leader',
        ]);

        $team = Team::create([
            'team_name' => 'Applying Team',
            'team_status' => 'Active',
            'leader_id' => $student->student_id,
            'is_confirmed' => false,
            'member_count' => 5,
        ]);

        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id]);

        if ($recommended) {
            $this->approvedPreProposal($team, $student);
        }

        return [$student, $team];
    }

    private function makeAdviserExpert(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true, 'max_teams' => 4,
            // Applying is refused outright until an expert has said which
            // programmes they take teams from.
            'program_slots' => '4 - BSIT',
        ]);
        $adviserRole = Role::firstOrCreate(['role_name' => 'adviser']);
        $expert->roles()->attach($adviserRole->role_id);

        return $expert;
    }

    /**
     * The expert_roles row id for one of an expert's roles - what an
     * application stores to record the role it is for.
     */
    private function expertRoleId(CapstoneExpert $expert, string $roleName): int
    {
        $role = Role::firstOrCreate(['role_name' => $roleName]);

        if (! $expert->roles()->where('roles.role_id', $role->role_id)->exists()) {
            $expert->roles()->attach($role->role_id);
        }

        return (int) ExpertRole::where('expert_id', $expert->expert_id)
            ->where('role_id', $role->role_id)
            ->value('expert_role_id');
    }

    public function test_apply_records_the_role_the_team_applied_for(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();
        $adviserRoleId = $this->expertRoleId($expert, 'adviser');
        $this->actingAs($student, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $adviserRoleId,
            'message' => 'We would like to work with you.',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('capstone_expert_application', [
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $adviserRoleId,
            'status' => 'pending',
        ]);
    }

    // The role has to be chosen, not inferred. An expert who is both an
    // adviser and a consultant gives the same answer to "what roles does
    // this expert have?" for every team, so there is nothing to fall back
    // to if the application doesn't say.
    public function test_apply_requires_a_role_to_be_chosen(): void
    {
        [$student] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();
        $this->actingAs($student, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'message' => 'We would like to work with you.',
        ]);

        $response->assertStatus(422);
    }

    // Validation only proves the id exists in expert_roles - it could name
    // another expert's role entirely.
    public function test_apply_rejects_a_role_belonging_to_a_different_expert(): void
    {
        [$student] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();
        $otherExpert = $this->makeAdviserExpert();
        $this->actingAs($student, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($otherExpert, 'consultant'),
            'message' => 'We would like to work with you.',
        ]);

        $response->assertStatus(422);
    }

    // One role per expert, per team: taking someone as your adviser takes
    // them off the table as your consultant.
    public function test_a_team_cannot_engage_the_same_expert_in_a_second_role(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();

        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'adviser'),
            'status' => 'approved',
        ]);

        $this->actingAs($student, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'consultant'),
            'message' => 'Also be our consultant please.',
        ]);

        $response->assertStatus(409);
    }

    // ...but that lock is per team. Another team is free to take the same
    // expert in the role the first team didn't use them for.
    public function test_another_team_can_engage_that_expert_in_the_other_role(): void
    {
        [, $teamA] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();

        TeamExpertAssignment::create([
            'team_id' => $teamA->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'adviser'),
            'status' => 'approved',
        ]);

        $studentB = Student::create([
            'email' => 'leader-b@my.cspc.edu.ph',
            'f_name' => 'Second',
            'l_name' => 'Leader',
        ]);
        $teamB = Team::create([
            'team_name' => 'Second Team',
            'team_status' => 'Active',
            'leader_id' => $studentB->student_id,
            'is_confirmed' => false,
            'member_count' => 5,
        ]);
        StudentSchoolYear::create(['student_id' => $studentB->student_id, 'team_id' => $teamB->team_id]);
        $this->approvedPreProposal($teamB, $studentB);

        $this->actingAs($studentB, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'consultant'),
            'message' => 'Please be our consultant.',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_apply_rejects_an_expert_with_no_qualifying_role(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true, 'program_slots' => '4 - BSIT',
        ]);
        $capstoneAdviserRole = Role::firstOrCreate(['role_name' => 'capstone_adviser']);
        $expert->roles()->attach($capstoneAdviserRole->role_id);

        $this->actingAs($student, 'web');

        $capsaExpertRoleId = ExpertRole::where('expert_id', $expert->expert_id)
            ->where('role_id', $capstoneAdviserRole->role_id)
            ->value('expert_role_id');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $capsaExpertRoleId,
            'message' => 'We would like to work with you.',
        ]);

        $response->assertStatus(422);
    }

    public function test_apply_rejects_a_duplicate_pending_application_to_the_same_expert(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();

        $assignment = new TeamExpertAssignment;
        $assignment->team_id = $team->team_id;
        $assignment->expert_id = $expert->expert_id;
        $assignment->status = 'pending';
        $assignment->save();

        $this->actingAs($student, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'adviser'),
            'message' => 'Trying again.',
        ]);

        $response->assertStatus(409);
    }

    // Regression test: destroy() used to build its activity-log description
    // from a role relationship that broke when role_id was dropped. The role
    // is recorded again now (as expert_role_id), but this assignment has
    // none, so this also covers the unknown-role case.
    public function test_destroy_removes_an_assignment_without_error(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();

        $assignment = new TeamExpertAssignment;
        $assignment->team_id = $team->team_id;
        $assignment->expert_id = $expert->expert_id;
        $assignment->status = 'pending';
        $assignment->save();

        $this->actingAs($student, 'web');

        $response = $this->deleteJson("/api/assignments/{$assignment->assignment_id}", [
            'reason' => 'Changed our minds.',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseMissing('capstone_expert_application', ['assignment_id' => $assignment->assignment_id]);
    }

    /**
     * A role a team has already filled is filled. Applying to a second
     * adviser once one has accepted could only end with two people holding
     * one role, or a rejection the team waited on for nothing.
     */
    public function test_a_team_cannot_apply_for_a_role_it_has_already_filled(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $taken = $this->makeAdviserExpert();
        $another = $this->makeAdviserExpert();

        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $taken->expert_id,
            'expert_role_id' => $this->expertRoleId($taken, 'adviser'),
            'status' => 'approved',
        ]);

        $this->actingAs($student, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $another->expert_id,
            'expert_role_id' => $this->expertRoleId($another, 'adviser'),
            'message' => 'Be our adviser too.',
        ]);

        $response->assertStatus(409);
        $this->assertStringContainsString('already has an approved', $response->json('message'));
    }

    /** A different role is still open, though. */
    public function test_an_approved_adviser_does_not_block_applying_for_a_consultant(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $adviser = $this->makeAdviserExpert();
        $consultant = $this->makeAdviserExpert();

        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $adviser->expert_id,
            'expert_role_id' => $this->expertRoleId($adviser, 'adviser'),
            'status' => 'approved',
        ]);

        $this->actingAs($student, 'web');

        $this->postJson('/api/assignments', [
            'expert_id' => $consultant->expert_id,
            'expert_role_id' => $this->expertRoleId($consultant, 'consultant'),
            'message' => 'Be our consultant.',
        ])->assertOk();
    }

    /**
     * A waiting application holds the role too.
     *
     * It used to be left alone, on the reasoning that a team could apply to
     * several advisers and take whoever said yes first - but that is a team
     * asking four people to be their one adviser, and three of them read,
     * thought about and answered a request that was never theirs to accept.
     */
    public function test_a_pending_application_blocks_a_second_one_for_the_same_role(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $first = $this->makeAdviserExpert();
        $second = $this->makeAdviserExpert();

        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $first->expert_id,
            'expert_role_id' => $this->expertRoleId($first, 'adviser'),
            'status' => 'pending',
        ]);

        $this->actingAs($student, 'web');

        $this->postJson('/api/assignments', [
            'expert_id' => $second->expert_id,
            'expert_role_id' => $this->expertRoleId($second, 'adviser'),
            'message' => 'Be our adviser.',
        ])
            ->assertStatus(409)
            ->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, 'already waiting on'))->etc());

        $this->assertSame(1, TeamExpertAssignment::where('team_id', $team->team_id)->count());
    }

    /** One role being spoken for does not touch the others. */
    public function test_a_pending_adviser_does_not_block_applying_for_a_consultant(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $first = $this->makeAdviserExpert();
        $second = $this->makeAdviserExpert();

        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $first->expert_id,
            'expert_role_id' => $this->expertRoleId($first, 'adviser'),
            'status' => 'pending',
        ]);

        $this->actingAs($student, 'web');

        $this->postJson('/api/assignments', [
            'expert_id' => $second->expert_id,
            'expert_role_id' => $this->expertRoleId($second, 'consultant'),
            'message' => 'Be our consultant.',
        ])->assertOk();
    }

    /**
     * The apply dialog offers only what is still open.
     *
     * An expert who is both an adviser and a consultant is still worth
     * applying to while the adviser question is out with somebody else - as
     * a consultant, which is the part of them the team can still use.
     */
    public function test_the_apply_dialog_drops_a_role_the_team_has_already_asked_for(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $team->update(['is_confirmed' => true]);

        $pendingAdviser = $this->makeAdviserExpert();

        $both = $this->makeAdviserExpert();
        $this->expertRoleId($both, 'consultant');

        TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $pendingAdviser->expert_id,
            'expert_role_id' => $this->expertRoleId($pendingAdviser, 'adviser'),
            'status' => 'pending',
        ]);

        $html = $this->actingAs($student, 'web')
            ->get('/student/experts')->assertOk()->getContent();

        // The dual-role expert is still applied to, as a consultant only.
        // Js::from writes the payload with \u0022 for its quotes.
        $label = fn ($role) => '\u0022label\u0022:\u0022'.$role.'\u0022';

        $this->assertStringContainsString($label('Consultant'), $html);
        $this->assertStringNotContainsString($label('Adviser'), $html);
    }

    /** An approved pre-proposal for a team, the way the CaPSA leaves one. */
    private function approvedPreProposal(Team $team, Student $leader): CapstonePreProposal
    {
        $sy = StudentSchoolYear::firstOrCreate([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        $check = DuplicationCheck::create([
            'student_s_y_id' => $sy->s_y_id,
            'document_path' => 'duplication-checks/approved.pdf',
            'concepts' => ['title' => 'Approved Title'],
            'top_score' => 20,
            'verdict' => 'Passed',
            'matches' => [],
            'instructors_suggested' => [],
        ]);

        $proposal = CapstonePreProposal::create([
            'team_id' => $team->team_id,
            'duplication_check_id' => $check->id,
            'slot' => 1,
            'title' => 'Approved Title',
            'sdg_goals' => 'SDG 4 - Quality Education',
            'research_agenda' => ['Applications and Systems Development'],
            'problem_statement' => 'A problem.',
            'proposed_solution' => 'A solution.',
            'scope' => 'A scope.',
            'limitations' => 'A limitation.',
            'sdm' => 'Agile Development Methodology',
            'approved_status' => true,
            'submitted_at' => now(),
        ]);

        $team->update(['selected_pre_proposal_id' => $proposal->id, 'project_title' => $proposal->title]);

        return $proposal;
    }

    /**
     * A team whose pre-proposal the CaPSA approved should not have to find
     * and re-upload the same PDF - and re-uploading invited a different,
     * unapproved file to be sent instead.
     */
    public function test_a_team_with_an_approved_pre_proposal_applies_without_attaching_a_file(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam(recommended: false);
        $proposal = $this->approvedPreProposal($team, $student);
        $expert = $this->makeAdviserExpert();

        $this->actingAs($student, 'web');

        $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'adviser'),
            'message' => 'Please be our adviser.',
        ])->assertOk();

        $assignment = TeamExpertAssignment::where('team_id', $team->team_id)->first();

        $this->assertSame($proposal->id, $assignment->pre_proposal_id, 'The approved pre-proposal was not carried across.');
        $this->assertNull($assignment->proposal_file);
    }

    /** No recommended pre-proposal, no application - an uploaded PDF does not stand in for one. */
    public function test_a_team_without_a_recommended_pre_proposal_cannot_apply(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam(recommended: false);
        $expert = $this->makeAdviserExpert();

        $this->actingAs($student, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'adviser'),
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'message' => 'Please be our adviser.',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('recommended by your CaPSA', $response->json('message'));
        $this->assertSame(0, TeamExpertAssignment::where('team_id', $team->team_id)->count());
    }

    /** The page says why, and offers no Apply button. */
    public function test_the_page_tells_a_team_without_a_recommended_pre_proposal_it_cannot_apply_yet(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam(recommended: false);
        $team->update(['is_confirmed' => true]);
        $this->makeAdviserExpert();

        $this->actingAs($student, 'web')->get('/student/experts?tab=find')->assertOk()
            ->assertSee('Your pre-proposal has to be recommended by your CaPSA before you can apply for experts.')
            ->assertDontSee('"apply":true', false);
    }

    /**
     * An expert cannot drop a team they approved. The team planned around
     * that yes; a change of expert goes through their CaPSA, the same way the
     * students' own side asks for one.
     */
    public function test_an_expert_cannot_drop_a_team_they_approved(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();

        $assignment = TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'adviser'),
            'status' => 'approved',
        ]);

        $this->actingAs($expert, 'expert');

        $this->deleteJson(route('instructor.assignments.remove', $assignment->assignment_id), [])
            ->assertStatus(422);

        $this->deleteJson(route('instructor.assignments.remove', $assignment->assignment_id), [
            'reason' => 'Schedule clash this semester.',
            'confirm' => 'remove',
        ])->assertStatus(422);

        $this->assertNotNull($assignment->fresh(), 'The approved team was dropped anyway.');
        $this->assertSame(0, Notification::where('student_id', $student->student_id)->where('type', 'expert_removed')->count());
    }

    /** A declined request can be cleared off the list; the team is not told again. */
    public function test_a_declined_request_can_be_cleared_from_the_list_without_telling_the_team_again(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();

        $assignment = TeamExpertAssignment::create([
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'adviser'),
            'status' => 'rejected',
            'rejection_reason' => 'Tighten the problem statement, then apply again.',
        ]);

        $this->actingAs($expert, 'expert');

        $this->deleteJson(route('instructor.assignments.remove', $assignment->assignment_id), [])
            ->assertOk();

        $this->assertNull($assignment->fresh());
        $this->assertSame(0, Notification::where('student_id', $student->student_id)->where('type', 'expert_removed')->count());
    }
}
