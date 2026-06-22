<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_creation_sets_team_id_in_student_s_y(): void
    {
        $student = Student::create([
            'email' => 'teamleader@my.cspc.edu.ph',
            'f_name' => 'Team',
            'l_name' => 'Leader',
        ]);

        $this->actingAs($student, 'web');

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => null,
        ]);

        $response = $this->post('/team', [
            'team_name' => 'Bulbasaur',
            'team_status' => 'Active',
        ]);

        $response->assertSessionHas('status');
        $response->assertRedirect(route('student.team'));

        $team = Team::where('team_name', 'Bulbasaur')->first();
        $this->assertNotNull($team, 'Team was not created');

        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $this->assertNotNull($sy, 'student_s_y row missing');
        $this->assertEquals($team->team_id, $sy->team_id, 'team_id not synced to student_s_y');
    }

    public function test_leaving_team_deletes_post_and_cleans_up_empty_team(): void
    {
        $student = Student::create([
            'email' => 'member@my.cspc.edu.ph',
            'f_name' => 'John',
            'l_name' => 'Doe',
        ]);

        $this->actingAs($student, 'web');

        $team = Team::create([
            'team_name' => 'Team Charmander',
            'team_status' => 'Active',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
        ]);

        Recruitment::create([
            'student_id' => $student->student_id,
            'recruitment_goal' => 'build_a_team',
            'roles' => ['Backend Dev'],
            'looking_for' => 'Backend member',
        ]);

        $this->assertDatabaseHas('team', ['team_id' => $team->team_id]);
        $this->assertDatabaseHas('recruitment', ['student_id' => $student->student_id]);

        $response = $this->delete('/team');

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status', 'You left the team. Since you were the leader, the team has been dissolved.');

        // Verify team_id is set to null
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $this->assertNull($sy->team_id);

        // Verify recruitment post is deleted
        $this->assertDatabaseMissing('recruitment', ['student_id' => $student->student_id]);

        // Verify team is deleted since it became empty
        $this->assertDatabaseMissing('team', ['team_id' => $team->team_id]);
    }

    public function test_leader_leaving_with_members_dissolves_team_and_unassigns_all(): void
    {
        $leader = Student::create([
            'email' => 'leader@my.cspc.edu.ph',
            'f_name' => 'Alice',
            'l_name' => 'Leader',
        ]);

        $member = Student::create([
            'email' => 'member2@my.cspc.edu.ph',
            'f_name' => 'Bob',
            'l_name' => 'Member',
        ]);

        $team = Team::create([
            'team_name' => 'Team Squirtle',
            'team_status' => 'Active',
        ]);

        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $member->student_id,
            'team_id' => $team->team_id,
        ]);

        Recruitment::create([
            'student_id' => $leader->student_id,
            'recruitment_goal' => 'build_a_team',
            'roles' => [],
        ]);

        TeamInvitation::create([
            'sender_id' => $leader->student_id,
            'receiver_id' => $member->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
            'status' => 'accepted',
        ]);

        $this->actingAs($leader, 'web');

        $response = $this->delete('/team');

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status', 'You left the team. Since you were the leader, the team has been dissolved.');

        // Both members should have no team
        $this->assertNull(StudentSchoolYear::where('student_id', $leader->student_id)->value('team_id'));
        $this->assertNull(StudentSchoolYear::where('student_id', $member->student_id)->value('team_id'));

        // Team should be deleted
        $this->assertDatabaseMissing('team', ['team_id' => $team->team_id]);

        // Invitations should be deleted
        $this->assertDatabaseMissing('team_invitations', ['team_id' => $team->team_id]);

        // Leader's recruitment post should be deleted
        $this->assertDatabaseMissing('recruitment', ['student_id' => $leader->student_id]);
    }
}
