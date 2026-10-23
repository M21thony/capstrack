<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JoinRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_request_notification_names_the_requester_not_the_leader(): void
    {
        $leader = Student::create([
            'email' => 'leader1@my.cspc.edu.ph',
            'f_name' => 'Student1',
            'l_name' => 'Leader',
        ]);

        $requester = Student::create([
            'email' => 'requester2@my.cspc.edu.ph',
            'f_name' => 'Student2',
            'l_name' => 'Member',
        ]);

        $team = Team::create([
            'team_name' => 'Team Alpha',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        $this->actingAs($requester, 'web');

        $this->post(route('invitations.store'), [
            'receiver_id' => $leader->student_id,
            'team_id' => $team->team_id,
            'type' => 'request',
        ]);

        $this->assertDatabaseHas('notifications', [
            'student_id' => $leader->student_id,
            'type' => 'request_received',
            'message' => 'Student2 Member wants to join your team.',
        ]);

        $this->assertDatabaseMissing('notifications', [
            'student_id' => $leader->student_id,
            'type' => 'request_received',
            'message' => 'Student1 Leader wants to join your team.',
        ]);
    }

    public function test_join_request_appears_under_join_requests_not_team_invites(): void
    {
        $leader = Student::create([
            'email' => 'leader3@my.cspc.edu.ph',
            'f_name' => 'Student1',
            'l_name' => 'Leader',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th · A',
            'student_number' => '2026-3001',
            'p_picture' => 'avatar-01',
        ]);

        $requester = Student::create([
            'email' => 'requester4@my.cspc.edu.ph',
            'f_name' => 'Student2',
            'l_name' => 'Member',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '3rd · A',
            'student_number' => '2026-3002',
            'p_picture' => 'avatar-02',
        ]);

        $team = Team::create([
            'team_name' => 'Team Alpha',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
            'school_year' => 2026,
            'program' => 'BSCS',
        ]);

        StudentSchoolYear::create([
            'student_id' => $requester->student_id,
            'team_id' => null,
            'school_year' => 2026,
            'program' => 'BSCS',
        ]);

        $this->actingAs($requester, 'web');
        $this->post(route('invitations.store'), [
            'receiver_id' => $leader->student_id,
            'team_id' => $team->team_id,
            'type' => 'request',
        ]);

        $this->actingAs($leader, 'web');
        $response = $this->get(route('student.team'));
        $response->assertOk();

        $this->assertCount(1, $response->viewData('incomingRequests'));
        $this->assertEquals($requester->student_id, $response->viewData('incomingRequests')->first()->sender_id);
        $this->assertCount(0, $response->viewData('invites'));

        $response->assertSee('Join requests');
        $response->assertSee('Student2 Member wants to join');
    }
}
