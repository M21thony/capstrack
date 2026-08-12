<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
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
            'team_status' => 'on-going',
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

    // Regression test: the "apply for expert" form used to send no role_id
    // (the frontend never collected one), but the backend still required
    // it, so every submission failed with "The role id field is required."
    // before a request was even processed.
    public function test_apply_succeeds_without_a_role_id(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = $this->makeAdviserExpert();
        $this->actingAs($student, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'message' => 'We would like to work with you.',
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('capstone_expert_application', [
            'team_id' => $team->team_id,
            'expert_id' => $expert->expert_id,
            'status' => 'pending',
        ]);
    }

    public function test_apply_rejects_an_expert_with_no_qualifying_role(): void
    {
        [$student, $team] = $this->makeLeaderWithTeam();
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $capstoneAdviserRole = Role::firstOrCreate(['role_name' => 'capstone_adviser']);
        $expert->roles()->attach($capstoneAdviserRole->role_id);

        $this->actingAs($student, 'web');

        $response = $this->postJson('/api/assignments', [
            'expert_id' => $expert->expert_id,
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
            'proposal_file' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
            'message' => 'Trying again.',
        ]);

        $response->assertStatus(409);
    }

    // Regression test: destroy() used to build its activity-log description
    // via $assignment->role->first()?->role_name, a relationship built on
    // the now-dropped role_id column - confirm removing an assignment
    // still works end-to-end.
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
}
