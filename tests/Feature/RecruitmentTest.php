<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_publish_build_a_team_post(): void
    {
        $student = Student::create([
            'email' => 'leader@my.cspc.edu.ph',
            'f_name' => 'John',
            'l_name' => 'Doe',
            'student_number' => '2026-0001',
            'p_picture' => 'avatar-01',
        ]);

        $this->actingAs($student);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => null,
            'school_year' => 2026,
            'program' => 'BSIT',
        ]);

        $response = $this->post('/recruitment', [
            'recruitment_goal' => 'build_a_team',
            'team_name' => 'Innovators',
            'looking_for' => 'Need a Frontend Developer',
            'tags' => json_encode(['Web Dev', 'UI Designer']),
        ]);

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status', 'Recruitment post published!');

        // Assert team was created
        $team = Team::where('team_name', 'Innovators')->first();
        $this->assertNotNull($team);

        // Assert student was linked to team
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $this->assertEquals($team->team_id, $sy->team_id);

        // Assert recruitment post was created
        $recruitment = Recruitment::where('student_id', $student->student_id)->first();
        $this->assertNotNull($recruitment);
        $this->assertEquals('build_a_team', $recruitment->recruitment_goal);
        $this->assertEquals('Need a Frontend Developer', $recruitment->looking_for);
        $this->assertEquals(['Web Dev', 'UI Designer'], $recruitment->roles);
    }

    public function test_student_can_publish_find_a_team_post(): void
    {
        $student = Student::create([
            'email' => 'individual@my.cspc.edu.ph',
            'f_name' => 'Jane',
            'l_name' => 'Smith',
            'student_number' => '2026-0002',
            'p_picture' => 'avatar-01',
        ]);

        $this->actingAs($student);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => null,
            'school_year' => 2026,
            'program' => 'BSIT',
        ]);

        $response = $this->post('/recruitment', [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'I am a backend developer',
            'tags' => json_encode(['Backend Dev']),
        ]);

        $response->assertRedirect(route('student.team'));

        // Assert no team was created
        $this->assertEquals(0, Team::count());

        // Assert recruitment post was created
        $recruitment = Recruitment::where('student_id', $student->student_id)->first();
        $this->assertNotNull($recruitment);
        $this->assertEquals('find_a_team', $recruitment->recruitment_goal);
        $this->assertEquals('Jane Smith', $student->f_name.' '.$student->l_name);
        $this->assertEquals(['Backend Dev'], $recruitment->roles);
    }

    public function test_student_in_team_cannot_publish_recruitment_post(): void
    {
        $student = Student::create([
            'email' => 'hasteam@my.cspc.edu.ph',
            'f_name' => 'John',
            'l_name' => 'Doe',
            'student_number' => '2026-0099',
            'p_picture' => 'avatar-01',
        ]);

        $this->actingAs($student);

        $team = Team::create([
            'team_name' => 'Already A Team',
            'team_status' => 'Active',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'school_year' => 2026,
            'program' => 'BSIT',
        ]);

        $response = $this->post('/recruitment', [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'Should fail',
            'tags' => json_encode(['Backend Dev']),
        ]);

        $response->assertSessionHasErrors(['error']);
        $this->assertEquals(0, Recruitment::count());
    }

    public function test_student_can_view_recruitment_feed_and_pagination(): void
    {
        $student = Student::create([
            'email' => 'viewer@my.cspc.edu.ph',
            'f_name' => 'Viewer',
            'l_name' => 'User',
            'student_number' => '2026-0003',
            'p_picture' => 'avatar-01',
        ]);

        $this->actingAs($student);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => null,
            'school_year' => 2026,
            'program' => 'BSIT',
        ]);

        // Create 6 other recruitment posts from other students in same school year
        for ($i = 1; $i <= 6; $i++) {
            $otherStudent = Student::create([
                'email' => "other{$i}@my.cspc.edu.ph",
                'f_name' => "Other{$i}",
                'l_name' => "User{$i}",
                'student_number' => '2026-000'.($i + 3),
                'p_picture' => 'avatar-02',
            ]);

            StudentSchoolYear::create([
                'student_id' => $otherStudent->student_id,
                'team_id' => null,
                'school_year' => 2026,
                'program' => 'BSIT',
            ]);

            Recruitment::create([
                'student_id' => $otherStudent->student_id,
                'recruitment_goal' => 'find_a_team',
                'roles' => ['Web Dev'],
                'introduction' => "Intro {$i}",
            ]);
        }

        $response = $this->get('/student/team');
        $response->assertStatus(200);

        // Verify it returns exactly 5 other posts on first page
        $otherRecruitments = $response->viewData('otherRecruitments');
        $this->assertEquals(5, $otherRecruitments->count());
        $this->assertEquals(6, $otherRecruitments->total());
        $this->assertTrue($otherRecruitments->hasPages());
    }

    public function test_student_only_sees_recruitment_posts_from_same_course_year_section(): void
    {
        // 1. Current student in BSIT, "3rd · A"
        $student = Student::create([
            'email' => 'studentA@my.cspc.edu.ph',
            'f_name' => 'Student',
            'l_name' => 'A',
            'student_number' => '2026-0500',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
            'p_picture' => 'avatar-01',
        ]);
        $this->actingAs($student);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => null,
            'school_year' => 2026,
            'program' => 'BSIT',
        ]);

        // 2. Other student in same course and section: BSIT, "3rd · A"
        $otherSame = Student::create([
            'email' => 'same@my.cspc.edu.ph',
            'f_name' => 'Same',
            'l_name' => 'Section',
            'student_number' => '2026-0501',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
            'p_picture' => 'avatar-01',
        ]);
        StudentSchoolYear::create([
            'student_id' => $otherSame->student_id,
            'team_id' => null,
            'school_year' => 2026,
            'program' => 'BSIT',
        ]);
        Recruitment::create([
            'student_id' => $otherSame->student_id,
            'recruitment_goal' => 'find_a_team',
            'roles' => ['Frontend Dev'],
        ]);

        // 3. Other student in same course but different section: BSIT, "3rd · B"
        $otherDiffSection = Student::create([
            'email' => 'diffsec@my.cspc.edu.ph',
            'f_name' => 'Diff',
            'l_name' => 'Section',
            'student_number' => '2026-0502',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · B',
            'p_picture' => 'avatar-01',
        ]);
        StudentSchoolYear::create([
            'student_id' => $otherDiffSection->student_id,
            'team_id' => null,
            'school_year' => 2026,
            'program' => 'BSIT',
        ]);
        Recruitment::create([
            'student_id' => $otherDiffSection->student_id,
            'recruitment_goal' => 'find_a_team',
            'roles' => ['Backend Dev'],
        ]);

        // 4. Other student in different course but same section: BSCS, "3rd · A"
        $otherDiffCourse = Student::create([
            'email' => 'diffcourse@my.cspc.edu.ph',
            'f_name' => 'Diff',
            'l_name' => 'Course',
            'student_number' => '2026-0503',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '3rd · A',
            'p_picture' => 'avatar-01',
        ]);
        StudentSchoolYear::create([
            'student_id' => $otherDiffCourse->student_id,
            'team_id' => null,
            'school_year' => 2026,
            'program' => 'BSCS',
        ]);
        Recruitment::create([
            'student_id' => $otherDiffCourse->student_id,
            'recruitment_goal' => 'find_a_team',
            'roles' => ['Database Admin'],
        ]);

        $response = $this->get('/student/team');
        $response->assertStatus(200);

        // Verify only same course and section post is returned
        $otherRecruitments = $response->viewData('otherRecruitments');
        $this->assertEquals(1, $otherRecruitments->count());
        $this->assertEquals($otherSame->student_id, $otherRecruitments->first()->student_id);
    }

    public function test_student_can_send_team_invite(): void
    {
        $sender = Student::create([
            'email' => 'sender@my.cspc.edu.ph',
            'f_name' => 'Sender',
            'l_name' => 'User',
            'student_number' => '2026-0010',
            'p_picture' => 'avatar-01',
        ]);

        $receiver = Student::create([
            'email' => 'receiver@my.cspc.edu.ph',
            'f_name' => 'Receiver',
            'l_name' => 'User',
            'student_number' => '2026-0011',
            'p_picture' => 'avatar-02',
        ]);

        $team = Team::create([
            'team_name' => 'Invitation Team',
            'team_status' => 'Active',
        ]);

        $this->actingAs($sender);

        $response = $this->post('/invitations', [
            'receiver_id' => $receiver->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
        ]);

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status', 'Invitation sent!');

        $this->assertDatabaseHas('team_invitations', [
            'sender_id' => $sender->student_id,
            'receiver_id' => $receiver->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
            'status' => 'pending',
        ]);
    }

    public function test_student_can_send_join_request(): void
    {
        $sender = Student::create([
            'email' => 'sender@my.cspc.edu.ph',
            'f_name' => 'Sender',
            'l_name' => 'User',
            'student_number' => '2026-0012',
            'p_picture' => 'avatar-01',
        ]);

        $receiver = Student::create([
            'email' => 'receiver@my.cspc.edu.ph',
            'f_name' => 'Receiver',
            'l_name' => 'User',
            'student_number' => '2026-0013',
            'p_picture' => 'avatar-02',
        ]);

        $team = Team::create([
            'team_name' => 'Request Team',
            'team_status' => 'Active',
        ]);

        $this->actingAs($sender);

        $response = $this->post('/invitations', [
            'receiver_id' => $receiver->student_id,
            'team_id' => $team->team_id,
            'type' => 'request',
        ]);

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status', 'Join request sent!');

        $this->assertDatabaseHas('team_invitations', [
            'sender_id' => $sender->student_id,
            'receiver_id' => $receiver->student_id,
            'team_id' => $team->team_id,
            'type' => 'request',
            'status' => 'pending',
        ]);
    }

    public function test_student_can_accept_team_invite(): void
    {
        $sender = Student::create([
            'email' => 'sender@my.cspc.edu.ph',
            'f_name' => 'Sender',
            'l_name' => 'User',
            'student_number' => '2026-0014',
            'p_picture' => 'avatar-01',
        ]);

        $receiver = Student::create([
            'email' => 'receiver@my.cspc.edu.ph',
            'f_name' => 'Receiver',
            'l_name' => 'User',
            'student_number' => '2026-0015',
            'p_picture' => 'avatar-02',
        ]);

        $team = Team::create([
            'team_name' => 'Accept Invite Team',
            'team_status' => 'Active',
        ]);

        StudentSchoolYear::create([
            'student_id' => $receiver->student_id,
            'team_id' => null,
            'school_year' => 2026,
            'program' => 'BSIT',
        ]);

        $invitation = TeamInvitation::create([
            'sender_id' => $sender->student_id,
            'receiver_id' => $receiver->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
            'status' => 'pending',
        ]);

        $this->actingAs($receiver);

        $response = $this->post("/invitations/{$invitation->id}/accept");

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status', 'Welcome to the team!');

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);

        $sy = StudentSchoolYear::where('student_id', $receiver->student_id)->first();
        $this->assertEquals($team->team_id, $sy->team_id);
    }

    public function test_student_can_decline_team_invite(): void
    {
        $sender = Student::create([
            'email' => 'sender@my.cspc.edu.ph',
            'f_name' => 'Sender',
            'l_name' => 'User',
            'student_number' => '2026-0016',
            'p_picture' => 'avatar-01',
        ]);

        $receiver = Student::create([
            'email' => 'receiver@my.cspc.edu.ph',
            'f_name' => 'Receiver',
            'l_name' => 'User',
            'student_number' => '2026-0017',
            'p_picture' => 'avatar-02',
        ]);

        $team = Team::create([
            'team_name' => 'Decline Invite Team',
            'team_status' => 'Active',
        ]);

        $invitation = TeamInvitation::create([
            'sender_id' => $sender->student_id,
            'receiver_id' => $receiver->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
            'status' => 'pending',
        ]);

        $this->actingAs($receiver);

        $response = $this->post("/invitations/{$invitation->id}/decline");

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status', 'Invitation declined.');

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitation->id,
            'status' => 'declined',
        ]);
    }
}
