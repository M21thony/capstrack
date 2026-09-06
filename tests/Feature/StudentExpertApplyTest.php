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

    private function makeLeaderWithTeam(): array
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

        return [$student, $team];
    }

    private function makeAdviserExpert(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'max_teams' => 4]);
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
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
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
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
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
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
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
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
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

        $this->actingAs($studentB, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'consultant'),
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'message' => 'Please be our consultant.',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_apply_rejects_an_expert_with_no_qualifying_role(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $capstoneAdviserRole = Role::firstOrCreate(['role_name' => 'capstone_adviser']);
        $expert->roles()->attach($capstoneAdviserRole->role_id);

        $this->actingAs($student, 'web');

        $capsaExpertRoleId = ExpertRole::where('expert_id', $expert->expert_id)
            ->where('role_id', $capstoneAdviserRole->role_id)
            ->value('expert_role_id');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $capsaExpertRoleId,
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'message' => 'We would like to work with you.',
        ]);

        $response->assertStatus(422);
    }

    public function test_apply_rejects_a_duplicate_pending_application_to_the_same_expert(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();

        $assignment = new TeamExpertAssignment();
        $assignment->team_id = $team->team_id;
        $assignment->expert_id = $expert->expert_id;
        $assignment->status = 'pending';
        $assignment->save();

        $this->actingAs($student, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'adviser'),
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
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

        $assignment = new TeamExpertAssignment();
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
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
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
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'message' => 'Be our consultant.',
        ])->assertOk();
    }

    /**
     * Only an accepted one takes the seat. A team may have applications out
     * to several advisers and take whoever says yes first.
     */
    public function test_a_pending_application_does_not_block_applying_elsewhere(): void
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
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'message' => 'Be our adviser.',
        ])->assertOk();
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
        [$student, $team] = $this->makeLeaderWithTeam();
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

    /** Without one, there is nothing to send, so a file is still required. */
    public function test_a_team_with_no_approved_pre_proposal_must_still_attach_one(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();

        $this->actingAs($student, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'expert_role_id' => $this->expertRoleId($expert, 'adviser'),
            'message' => 'Please be our adviser.',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Attach your proposal', $response->json('message'));
    }

    /**
     * Dropping a team went through on one click with nothing recorded and
     * nobody told - a team simply lost its adviser. It now needs a reason and
     * the word typed, and the team is told why.
     */
    public function test_an_expert_cannot_drop_a_team_without_a_reason(): void
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
            'confirm' => 'nope',
        ])->assertStatus(422);

        $this->assertNotNull($assignment->fresh(), 'The assignment was dropped anyway.');
    }

    /** With both, it goes through and the team is told why. */
    public function test_dropping_a_team_tells_them_why(): void
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

        $this->deleteJson(route('instructor.assignments.remove', $assignment->assignment_id), [
            'reason' => 'Schedule clash this semester.',
            'confirm' => 'remove',
        ])->assertOk();

        $this->assertNull($assignment->fresh());

        $note = Notification::where('student_id', $student->student_id)
            ->where('type', 'expert_removed')
            ->first();

        $this->assertNotNull($note, 'The team was never told.');
        $this->assertStringContainsString('Schedule clash this semester.', $note->message);
    }
}
