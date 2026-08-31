<?php

namespace Tests\Feature;

use App\Models\LeaveApproval;
use App\Models\Notification;
use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Carbon\Carbon;
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
            'leader_id' => $student->student_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
        ]);

        Recruitment::create([
            'student_id' => $student->student_id,
            'recruitment_goal' => 'find_a_team',
            'roles' => ['Backend Dev'],
            'looking_for' => 'Backend member',
        ]);

        $this->assertDatabaseHas('team', ['team_id' => $team->team_id]);
        $this->assertDatabaseHas('recruitment', ['student_id' => $student->student_id]);

        $response = $this->delete('/team');

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status', 'You have left the team and the team has been terminated.');

        // Verify team_id is set to null
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $this->assertNull($sy->team_id);

        // Verify recruitment post is deleted
        $this->assertDatabaseMissing('recruitment', ['student_id' => $student->student_id]);

        // Verify team is deleted since it became empty
        $this->assertDatabaseMissing('team', ['team_id' => $team->team_id]);
    }

    public function test_leader_cannot_leave_team_without_transferring(): void
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
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $member->student_id,
            'team_id' => $team->team_id,
        ]);

        // Leader has a build_a_team post
        Recruitment::create([
            'student_id' => $leader->student_id,
            'recruitment_goal' => 'build_a_team',
            'roles' => [],
        ]);

        $this->actingAs($leader, 'web');

        $response = $this->delete('/team');

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('error', 'As the team leader, you cannot leave directly. Transfer leadership to another member first.');

        // Team should still exist
        $this->assertDatabaseHas('team', ['team_id' => $team->team_id]);
    }

    public function test_leader_sees_leave_approval_actions_and_member_is_forced_after_three_declines(): void
    {
        $leader = Student::create([
            'email' => 'leave.leader@my.cspc.edu.ph',
            'f_name' => 'Leah',
            'l_name' => 'Leader',
            'student_number' => '100000001',
            'p_picture' => 'avatar-01',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th · A',
        ]);

        $member = Student::create([
            'email' => 'leave.member@my.cspc.edu.ph',
            'f_name' => 'Milo',
            'l_name' => 'Member',
        ]);

        $team = Team::create([
            'team_name' => 'Team Leave Flow',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $member->student_id,
            'team_id' => $team->team_id,
        ]);

        $this->actingAs($member, 'web');

        $response = $this->post('/team/leave');
        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status');

        $approval = LeaveApproval::where('student_id', $member->student_id)
            ->where('team_id', $team->team_id)
            ->latest('id')
            ->first();

        $this->assertNotNull($approval);
        $this->assertSame('pending', $approval->status);

        $this->actingAs($leader, 'web');
        $teamPage = $this->get(route('student.team'));
        $teamPage->assertOk();
        $teamPage->assertSee('/team/leave/'.$approval->id.'/approve', false);
        $teamPage->assertSee('/team/leave/'.$approval->id.'/decline', false);

        $this->post('/team/leave/'.$approval->id.'/decline');
        $approval->refresh();
        $this->assertSame('declined', $approval->status);

        $this->actingAs($member, 'web');
        $this->post('/team/leave');
        $secondApproval = LeaveApproval::where('student_id', $member->student_id)
            ->where('team_id', $team->team_id)
            ->latest('id')
            ->first();
        $this->assertNotNull($secondApproval);
        $this->assertSame(2, $secondApproval->attempt);

        $this->actingAs($leader, 'web');
        $this->post('/team/leave/'.$secondApproval->id.'/decline');
        $secondApproval->refresh();
        $this->assertSame('declined', $secondApproval->status);

        $this->actingAs($member, 'web');
        $this->post('/team/leave');
        $thirdApproval = LeaveApproval::where('student_id', $member->student_id)
            ->where('team_id', $team->team_id)
            ->latest('id')
            ->first();
        $this->assertNotNull($thirdApproval);
        $this->assertSame(3, $thirdApproval->attempt);

        $this->actingAs($leader, 'web');
        $this->post('/team/leave/'.$thirdApproval->id.'/decline');

        $memberSy = StudentSchoolYear::where('student_id', $member->student_id)->first();
        $this->assertNull($memberSy->team_id);
        $this->assertDatabaseHas('notifications', [
            'student_id' => $member->student_id,
            'type' => 'leave_force',
        ]);
    }

    public function test_leader_can_transfer_then_leave(): void
    {
        $leader = Student::create([
            'email' => 'transfer.leader@my.cspc.edu.ph',
            'f_name' => 'Charlie',
            'l_name' => 'Leader',
        ]);

        $member = Student::create([
            'email' => 'transfer.member@my.cspc.edu.ph',
            'f_name' => 'Diana',
            'l_name' => 'Member',
        ]);

        $team = Team::create([
            'team_name' => 'Team Transfer',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $member->student_id,
            'team_id' => $team->team_id,
        ]);

        // Leader has a build_a_team post
        Recruitment::create([
            'student_id' => $leader->student_id,
            'recruitment_goal' => 'build_a_team',
            'roles' => [],
        ]);

        $this->actingAs($leader, 'web');

        // Send leadership transfer request
        $response = $this->post('/team/transfer-leadership', [
            'new_leader_id' => $member->student_id,
        ]);

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status');

        // Leadership should NOT be transferred yet — pending approval
        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'leader_id' => $leader->student_id,
        ]);

        // A pending request notification was sent to the member
        $requestNotif = Notification::where('student_id', $member->student_id)
            ->where('type', 'leadership_transfer_request')
            ->where('is_read', false)
            ->first();
        $this->assertNotNull($requestNotif);

        // The leader receives a "pending approval" notice — no "accepted" message yet
        $pendingNotif = Notification::where('student_id', $leader->student_id)
            ->where('type', 'leadership_transfer_pending')
            ->where('is_read', false)
            ->first();
        $this->assertNotNull($pendingNotif, 'Leader should see a pending-approval notice.');

        $this->assertNull(
            Notification::where('type', 'leadership_transferred')
                ->where('is_read', false)
                ->first(),
            'No "accepted" notification should exist before the member approves.'
        );

        // Member accepts the request
        $this->actingAs($member, 'web');
        $response = $this->post('/team/leadership/'.$requestNotif->id.'/accept');

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status');

        // Verify the team's leader_id was updated
        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'leader_id' => $member->student_id,
        ]);

        // Now the original leader can leave (they are a regular member now)
        $this->actingAs($leader, 'web');
        $response = $this->delete('/team');
        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status', 'Your leave request has been sent to the team leader. It will auto-approve after '.LeaveApproval::EXPIRY_HOURS.' hours if unanswered.');

        // A leave approval was created for the original leader, pending the new leader's response
        $leaveApproval = LeaveApproval::where('student_id', $leader->student_id)
            ->where('team_id', $team->team_id)
            ->where('status', LeaveApproval::STATUS_PENDING)
            ->latest('id')
            ->first();
        $this->assertNotNull($leaveApproval);

        // New leader approves the leave request
        $this->actingAs($member, 'web');
        $this->post('/team/leave/'.$leaveApproval->id.'/approve');

        // Verify the original leader's team_id is null
        $this->assertNull(StudentSchoolYear::where('student_id', $leader->student_id)->value('team_id'));

        // Team should still exist (new leader is still there)
        $this->assertDatabaseHas('team', ['team_id' => $team->team_id]);
    }

    public function test_leader_transfer_is_not_completed_before_approval(): void
    {
        $leader = Student::create([
            'email' => 'pending.leader@example.com',
            'f_name' => 'Eve',
            'l_name' => 'Leader',
            'student_number' => '2026-0001',
            'p_picture' => 'avatar-01',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th · A',
        ]);

        $member = Student::create([
            'email' => 'pending.member@example.com',
            'f_name' => 'Frank',
            'l_name' => 'Member',
            'student_number' => '2026-0002',
            'p_picture' => 'avatar-02',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th · A',
        ]);

        $team = Team::create([
            'team_name' => 'Team Pending',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id, 'school_year' => 2026]);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id, 'school_year' => 2026]);

        $this->actingAs($leader, 'web');

        $this->post('/team/transfer-leadership', ['new_leader_id' => $member->student_id]);

        // Leadership must NOT be transferred until the member approves
        $this->assertDatabaseHas('team', ['team_id' => $team->team_id, 'leader_id' => $leader->student_id]);

        // The member received a leadership transfer request notification (approve/decline lives there)
        $requestNotif = Notification::where('student_id', $member->student_id)
            ->where('type', 'leadership_transfer_request')
            ->where('is_read', false)
            ->first();
        $this->assertNotNull($requestNotif);
    }

    public function test_api_leadership_transfer_requires_approval(): void
    {
        $leader = Student::create([
            'email' => 'api.leader@example.com',
            'f_name' => 'Ian',
            'l_name' => 'Leader',
        ]);

        $member = Student::create([
            'email' => 'api.member@example.com',
            'f_name' => 'Jolina',
            'l_name' => 'Member',
        ]);

        $team = Team::create([
            'team_name' => 'Team API',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        // Leader requests transfer via API
        $this->actingAs($leader, 'web');
        $response = $this->post('/api/teams/'.$team->team_id.'/transfer-leadership', [
            'new_leader_id' => $member->student_id,
        ]);
        $response->assertOk();

        // Leadership must NOT change yet
        $this->assertDatabaseHas('team', ['team_id' => $team->team_id, 'leader_id' => $leader->student_id]);

        // A request notification was created for the member
        $requestNotif = Notification::where('student_id', $member->student_id)
            ->where('type', 'leadership_transfer_request')
            ->where('is_read', false)
            ->first();
        $this->assertNotNull($requestNotif);

        // Member approves via API
        $this->actingAs($member, 'web');
        $this->post('/api/team/leadership/'.$requestNotif->id.'/accept');

        $this->assertDatabaseHas('team', ['team_id' => $team->team_id, 'leader_id' => $member->student_id]);
    }

    public function test_api_leadership_transfer_can_be_declined(): void
    {
        $leader = Student::create([
            'email' => 'api.decline.leader@example.com',
            'f_name' => 'Kyla',
            'l_name' => 'Leader',
        ]);

        $member = Student::create([
            'email' => 'api.decline.member@example.com',
            'f_name' => 'Leo',
            'l_name' => 'Member',
        ]);

        $team = Team::create([
            'team_name' => 'Team API Decline',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        $this->actingAs($leader, 'web');
        $this->post('/api/teams/'.$team->team_id.'/transfer-leadership', ['new_leader_id' => $member->student_id]);

        $requestNotif = Notification::where('student_id', $member->student_id)
            ->where('type', 'leadership_transfer_request')
            ->where('is_read', false)
            ->first();
        $this->assertNotNull($requestNotif);

        $this->actingAs($member, 'web');
        $this->post('/api/team/leadership/'.$requestNotif->id.'/decline');

        $this->assertDatabaseHas('team', ['team_id' => $team->team_id, 'leader_id' => $leader->student_id]);
        $this->assertDatabaseHas('notifications', [
            'student_id' => $leader->student_id,
            'type' => 'leadership_transfer_declined',
        ]);
    }

    public function test_leader_can_send_new_transfer_even_with_old_pending_request(): void
    {
        $leader = Student::create([
            'email' => 'stuck.leader@example.com',
            'f_name' => 'Stuck',
            'l_name' => 'Leader',
        ]);

        $firstMember = Student::create([
            'email' => 'stuck.member1@example.com',
            'f_name' => 'First',
            'l_name' => 'Member',
        ]);

        $secondMember = Student::create([
            'email' => 'stuck.member2@example.com',
            'f_name' => 'Second',
            'l_name' => 'Member',
        ]);

        $team = Team::create([
            'team_name' => 'Team Stuck',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        StudentSchoolYear::create(['student_id' => $firstMember->student_id, 'team_id' => $team->team_id]);
        StudentSchoolYear::create(['student_id' => $secondMember->student_id, 'team_id' => $team->team_id]);

        $this->actingAs($leader, 'web');

        // First transfer attempt to member 1 (never approved/declined -> stuck)
        $this->post('/team/transfer-leadership', ['new_leader_id' => $firstMember->student_id]);

        $this->assertDatabaseHas('team', ['team_id' => $team->team_id, 'leader_id' => $leader->student_id]);

        // Leader picks another member — must NOT be blocked by the stuck request
        $response = $this->post('/team/transfer-leadership', ['new_leader_id' => $secondMember->student_id]);

        $response->assertRedirect(route('student.team'));
        $response->assertSessionHas('status');

        // Leadership still requires approval — not transferred yet
        $this->assertDatabaseHas('team', ['team_id' => $team->team_id, 'leader_id' => $leader->student_id]);

        // A fresh pending request exists for member 2, and the old one was cancelled
        $this->assertDatabaseHas('notifications', [
            'student_id' => $secondMember->student_id,
            'type' => 'leadership_transfer_request',
            'is_read' => false,
        ]);
    }

    public function test_member_can_decline_leadership_transfer(): void
    {
        $leader = Student::create([
            'email' => 'decline.leader@example.com',
            'f_name' => 'Grace',
            'l_name' => 'Leader',
        ]);

        $member = Student::create([
            'email' => 'decline.member@example.com',
            'f_name' => 'Henry',
            'l_name' => 'Member',
        ]);

        $team = Team::create([
            'team_name' => 'Team Decline',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        $this->actingAs($leader, 'web');
        $this->post('/team/transfer-leadership', ['new_leader_id' => $member->student_id]);

        $requestNotif = Notification::where('student_id', $member->student_id)
            ->where('type', 'leadership_transfer_request')
            ->where('is_read', false)
            ->first();

        $this->actingAs($member, 'web');
        $this->post('/team/leadership/'.$requestNotif->id.'/decline');

        // Leader remains unchanged
        $this->assertDatabaseHas('team', ['team_id' => $team->team_id, 'leader_id' => $leader->student_id]);

        // Old leader was notified about the decline
        $this->assertDatabaseHas('notifications', [
            'student_id' => $leader->student_id,
            'type' => 'leadership_transfer_declined',
        ]);
    }

    public function test_team_name_is_unique_case_insensitively_on_create(): void
    {
        $first = Student::create([
            'email' => 'first@my.cspc.edu.ph',
            'f_name' => 'First',
            'l_name' => 'Leader',
        ]);
        Team::create([
            'team_name' => 'HyperTest',
            'team_status' => 'Active',
            'leader_id' => $first->student_id,
        ]);

        $second = Student::create([
            'email' => 'second@my.cspc.edu.ph',
            'f_name' => 'Second',
            'l_name' => 'Leader',
        ]);
        $this->actingAs($second, 'web');
        StudentSchoolYear::create([
            'student_id' => $second->student_id,
            'team_id' => null,
        ]);

        foreach (['HYPERTEST', 'hypertest'] as $attempt) {
            $response = $this->post('/team', [
                'team_name' => $attempt,
                'team_status' => 'Active',
            ]);
            $response->assertSessionHasErrors('team_name');
        }

        $this->assertDatabaseMissing('team', ['team_name' => 'HYPERTEST']);
        $this->assertDatabaseMissing('team', ['team_name' => 'hypertest']);
    }

    public function test_duplicate_team_name_is_rejected_when_posting_build_a_team(): void
    {
        $first = Student::create([
            'email' => 'first2@my.cspc.edu.ph',
            'f_name' => 'First',
            'l_name' => 'Leader',
        ]);
        Team::create([
            'team_name' => 'Innovators',
            'team_status' => 'Active',
            'leader_id' => $first->student_id,
        ]);

        $second = Student::create([
            'email' => 'second2@my.cspc.edu.ph',
            'f_name' => 'Second',
            'l_name' => 'Leader',
        ]);
        $this->actingAs($second, 'web');
        StudentSchoolYear::create([
            'student_id' => $second->student_id,
            'team_id' => null,
        ]);

        $response = $this->post('/recruitment', [
            'recruitment_goal' => 'build_a_team',
            'team_name' => 'INNOVATORS',
            'looking_for' => 'Need members',
        ]);

        $this->assertDatabaseMissing('team', ['team_name' => 'INNOVATORS']);
    }

    public function test_member_can_remind_me_later_on_leadership_transfer(): void
    {
        $leader = Student::create([
            'email' => 'remind.leader@example.com',
            'f_name' => 'Ivy',
            'l_name' => 'Leader',
        ]);

        $member = Student::create([
            'email' => 'remind.member@example.com',
            'f_name' => 'Jack',
            'l_name' => 'Member',
            'student_number' => '2026-0041',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th · A',
            'p_picture' => 'avatar-01',
        ]);

        $team = Team::create([
            'team_name' => 'Team Remind',
            'team_status' => 'Active',
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        $this->actingAs($leader, 'web');
        $this->post('/team/transfer-leadership', ['new_leader_id' => $member->student_id]);

        $requestNotif = Notification::where('student_id', $member->student_id)
            ->where('type', 'leadership_transfer_request')
            ->where('is_read', false)
            ->first();
        $this->assertNotNull($requestNotif);
        $this->assertArrayNotHasKey('remind_me_at', $requestNotif->data ?? []);

        // The popup appears on the team page before any reminder is set
        $this->actingAs($member, 'web');
        $page = $this->get(route('student.team'));
        $page->assertOk();
        $page->assertSee('Leadership Transfer Request');
        $page->assertSee(route('team.leadership.remind-later', $requestNotif->id));
        $page->assertSee('Remind Me Later');

        // Member chooses "Remind Me Later" — sets a 3-hour reminder, request stays pending
        $response = $this->post('/team/leadership/'.$requestNotif->id.'/remind-later', [], ['Accept' => 'application/json']);
        $response->assertOk();

        $requestNotif->refresh();
        $this->assertNotNull($requestNotif->data['remind_me_at'] ?? null);
        $this->assertTrue(
            now()->diffInHours(Carbon::parse($requestNotif->data['remind_me_at'])) >= 2
        );

        // Leadership unchanged, request still pending
        $this->assertDatabaseHas('team', ['team_id' => $team->team_id, 'leader_id' => $leader->student_id]);
        $this->assertDatabaseHas('notifications', [
            'student_id' => $member->student_id,
            'type' => 'leadership_transfer_request',
            'is_read' => false,
        ]);
    }

    public function test_leader_sees_all_classmates_in_invite_modal_including_those_with_teams(): void
    {
        $course = 'Bachelor of Science in Information Technology';
        $yearSection = '3rd · B';
        $schoolYear = 2026;

        $leader = Student::create([
            'email' => 'leader.invite@example.com',
            'f_name' => 'Leader',
            'l_name' => 'Alpha',
            'student_number' => '2026-1001',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        $team1 = Team::create([
            'team_name' => 'Team One',
            'team_status' => 'on-going',
            'leader_id' => $leader->student_id,
        ]);

        // Student2 — already in a DIFFERENT team
        $studentWithTeam = Student::create([
            'email' => 'withteam.invite@example.com',
            'f_name' => 'Bravo',
            'l_name' => 'Beta',
            'student_number' => '2026-1002',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        $team2 = Team::create([
            'team_name' => 'Team Two',
            'team_status' => 'on-going',
            'leader_id' => $studentWithTeam->student_id,
        ]);

        // Student3 — no team yet
        $studentWithoutTeam = Student::create([
            'email' => 'withoutteam.invite@example.com',
            'f_name' => 'Charlie',
            'l_name' => 'Gamma',
            'student_number' => '2026-1003',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        // Create student_s_y records for all three
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team1->team_id, 'school_year' => $schoolYear]);
        StudentSchoolYear::create(['student_id' => $studentWithTeam->student_id, 'team_id' => $team2->team_id, 'school_year' => $schoolYear]);
        StudentSchoolYear::create(['student_id' => $studentWithoutTeam->student_id, 'team_id' => null, 'school_year' => $schoolYear]);

        $this->actingAs($leader, 'web');

        $response = $this->get('/team/available-classmates');
        $response->assertOk();

        $data = $response->json();
        $this->assertIsArray($data['classmates']);

        $ids = array_column($data['classmates'], 'student_id');

        // Leader should see both classmates (same course + year_section + school_year)
        $this->assertContains($studentWithTeam->student_id, $ids, 'Classmate with existing team should appear in list');
        $this->assertContains($studentWithoutTeam->student_id, $ids, 'Classmate without team should appear in list');
        $this->assertNotContains($leader->student_id, $ids, 'Leader should not appear in their own list');

        // Verify has_team flags
        $withTeam = collect($data['classmates'])->firstWhere('student_id', $studentWithTeam->student_id);
        $withoutTeam = collect($data['classmates'])->firstWhere('student_id', $studentWithoutTeam->student_id);

        $this->assertTrue($withTeam['has_team'], 'Classmate with team should have has_team = true');
        $this->assertFalse($withoutTeam['has_team'], 'Classmate without team should have has_team = false');
    }

    public function test_non_leader_cannot_access_available_classmates(): void
    {
        $course = 'Bachelor of Science in Information Technology';
        $yearSection = '3rd · B';
        $schoolYear = 2026;

        $leader = Student::create([
            'email' => 'leader.noleader@example.com',
            'f_name' => 'Delta',
            'l_name' => 'Leader',
            'student_number' => '2026-2001',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        $member = Student::create([
            'email' => 'member.noleader@example.com',
            'f_name' => 'Echo',
            'l_name' => 'Member',
            'student_number' => '2026-2002',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        $team = Team::create([
            'team_name' => 'Team NoLeader Access',
            'team_status' => 'on-going',
            'leader_id' => $leader->student_id,
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id, 'school_year' => $schoolYear]);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id, 'school_year' => $schoolYear]);

        $this->actingAs($member, 'web');

        $response = $this->get('/team/available-classmates');
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Only the team leader can invite members.']);
    }

    public function test_leader_sees_classmates_even_with_corrupted_school_year(): void
    {
        $course = 'Bachelor of Science in Information Technology';
        $yearSection = '3rd · B';

        $leader = Student::create([
            'email' => 'leader.corrupt@example.com',
            'f_name' => 'Zeta',
            'l_name' => 'Leader',
            'student_number' => '2026-3001',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        $team1 = Team::create([
            'team_name' => 'Team Corrupt',
            'team_status' => 'on-going',
            'leader_id' => $leader->student_id,
        ]);

        // Leader has corrupted school_year "26-1" (legacy data)
        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team1->team_id,
            'school_year' => '26-1',
        ]);

        // Classmate with team — has correct school_year
        $studentWithTeam = Student::create([
            'email' => 'withteam.corrupt@example.com',
            'f_name' => 'Eta',
            'l_name' => 'Teamed',
            'student_number' => '2026-3002',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        $team2 = Team::create([
            'team_name' => 'Team Other',
            'team_status' => 'on-going',
            'leader_id' => $studentWithTeam->student_id,
        ]);

        StudentSchoolYear::create([
            'student_id' => $studentWithTeam->student_id,
            'team_id' => $team2->team_id,
            'school_year' => '2026-2027',
        ]);

        // Classmate without team — has correct school_year
        $studentWithoutTeam = Student::create([
            'email' => 'withoutteam.corrupt@example.com',
            'f_name' => 'Theta',
            'l_name' => 'NoTeam',
            'student_number' => '2026-3003',
            'course' => $course,
            'year_section' => $yearSection,
        ]);

        StudentSchoolYear::create([
            'student_id' => $studentWithoutTeam->student_id,
            'team_id' => null,
            'school_year' => '2026-2027',
        ]);

        $this->actingAs($leader, 'web');

        // The fallback should kick in: leader has "26-1", classmates have "2026-2027"
        $response = $this->get('/team/available-classmates');
        $response->assertOk();

        $data = $response->json();
        $this->assertIsArray($data['classmates']);

        $ids = array_column($data['classmates'], 'student_id');

        // Both classmates should appear despite the school_year mismatch
        $this->assertContains($studentWithTeam->student_id, $ids, 'Classmate with team should appear despite corrupted leader school_year');
        $this->assertContains($studentWithoutTeam->student_id, $ids, 'Classmate without team should appear despite corrupted leader school_year');
        $this->assertNotContains($leader->student_id, $ids, 'Leader should not appear in their own list');
    }

    public function test_team_creation_sets_school_year_on_student_s_y(): void
    {
        $student = Student::create([
            'email' => 'teamstore.sy@example.com',
            'f_name' => 'Iota',
            'l_name' => 'Creator',
            'student_number' => '2026-4001',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th · A',
        ]);

        $this->actingAs($student, 'web');

        // Ensure no student_s_y record exists yet
        $this->assertDatabaseMissing('student_s_y', ['student_id' => $student->student_id]);

        $response = $this->post('/team', [
            'team_name' => 'Auto SY Team',
            'team_status' => 'on-going',
        ]);

        $response->assertRedirect(route('student.team'));

        // The student_s_y record should have school_year set
        $sy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $this->assertNotNull($sy, 'student_s_y record should exist');
        $this->assertNotNull($sy->school_year, 'school_year should be set after team creation');
    }
}
