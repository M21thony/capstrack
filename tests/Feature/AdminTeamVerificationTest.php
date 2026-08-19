<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminTeamVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(array $sections = ['BSCS-4A']): CapstoneExpert
    {
        $admin = CapstoneExpert::create([
            'email' => 'admin.verify@test.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_admin' => true,
            'approved_status' => true,
            'sections' => $sections,
        ]);

        $this->actingAs($admin, 'expert');

        return $admin;
    }

    private function createLeader(string $email = 'leader.verify@my.cspc.edu.ph'): Student
    {
        return Student::factory()->withCompleteProfile()->create([
            'email' => $email,
            'f_name' => 'Leader',
            'l_name' => 'One',
        ]);
    }

    private function createMember(): Student
    {
        return Student::create([
            'email' => 'member.verify@my.cspc.edu.ph',
            'f_name' => 'Member',
            'l_name' => 'One',
        ]);
    }

    private const PROJECT_TITLE = 'Intelligent Flood Monitoring and Early Warning System Using IoT Sensors for Rural Communities';

    private function createCompleteTeam(Student $leader, string $teamName = 'Team Verify'): Team
    {
        $team = Team::create([
            'team_name' => $teamName,
            'team_status' => 'complete',
            'is_confirmed' => false,
            'leader_id' => $leader->student_id,
            'project_title' => self::PROJECT_TITLE,
            'tags' => ['IOT', 'Machine Learning'],
        ]);

        StudentSchoolYear::create([
            'student_id' => $leader->student_id,
            'team_id' => $team->team_id,
        ]);

        return $team;
    }

    private function updateTeam(Team $team, string $status): void
    {
        $this->put(route('team.update', $team->team_id), [
            'team_name' => $team->team_name,
            'project_title' => $team->project_title,
            'team_status' => $status,
            'tags' => json_encode(['IOT', 'Machine Learning']),
        ]);
    }

    public function test_admin_can_see_complete_unverified_teams_on_verification_page(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $onGoing = Team::create([
            'team_name' => 'Team Ongoing',
            'team_status' => 'on-going',
            'leader_id' => $leader->student_id,
        ]);

        $response = $this->get(route('admin.verify_teams'));

        $response->assertOk();
        $response->assertSee($team->team_name);
        $response->assertDontSee($onGoing->team_name);
    }

    public function test_admin_can_verify_a_complete_team(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $member = $this->createMember();
        $team = $this->createCompleteTeam($leader);

        StudentSchoolYear::create([
            'student_id' => $member->student_id,
            'team_id' => $team->team_id,
        ]);

        $response = $this->post(route('admin.verify_teams.verify', $team->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'is_confirmed' => true,
        ]);

        $this->assertDatabaseHas('notifications', [
            'student_id' => $leader->student_id,
            'type' => 'team_verified',
        ]);

        $this->assertDatabaseHas('notifications', [
            'student_id' => $member->student_id,
            'type' => 'team_verified',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'type' => 'team_verified',
            'description' => 'Verified team <strong>'.$team->team_name.'</strong>',
        ]);
    }

    public function test_admin_cannot_verify_a_team_not_marked_complete(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = Team::create([
            'team_name' => 'Team Not Complete',
            'team_status' => 'on-going',
            'leader_id' => $leader->student_id,
        ]);

        $response = $this->post(route('admin.verify_teams.verify', $team->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'is_confirmed' => false,
        ]);
    }

    public function test_team_no_longer_ready_to_verify_after_status_changes_away_from_complete(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $this->assertDatabaseHas('team', ['team_id' => $team->team_id, 'team_status' => 'complete']);

        $this->actingAs($leader, 'web');
        $this->updateTeam($team, 'on-going');

        $team->refresh();
        $this->assertSame('on-going', $team->team_status);

        $this->actingAs($admin = CapstoneExpert::find(1), 'expert');
        $response = $this->get(route('admin.verify_teams'));

        $response->assertOk();
        $response->assertSee('No teams awaiting verification');
    }

    public function test_team_becomes_ready_to_verify_again_after_changing_back_to_complete(): void
    {
        $this->createAdmin();
        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $this->actingAs($leader, 'web');
        $this->updateTeam($team, 'on-going');

        $this->updateTeam($team, 'complete');

        $this->actingAs($admin = CapstoneExpert::find(1), 'expert');
        $response = $this->get(route('admin.verify_teams'));

        $response->assertOk();
        $response->assertSee($team->team_name);
    }

    public function test_student_confirm_route_was_removed(): void
    {
        $leader = $this->createLeader();
        $this->actingAs($leader, 'web');

        $team = $this->createCompleteTeam($leader);

        $response = $this->post('/team/confirm');

        $this->assertContains($response->status(), [404, 405], 'Confirm route should no longer exist');
        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'is_confirmed' => false,
        ]);
    }

    public function test_team_leader_sees_pending_verification_when_complete_but_unverified(): void
    {
        $leader = $this->createLeader();
        $this->actingAs($leader, 'web');

        $team = $this->createCompleteTeam($leader);

        $response = $this->get(route('student.team'));

        $response->assertOk();
        $response->assertSee('Pending Verification');
        $response->assertDontSee('Confirm Team');
    }

    public function test_team_leader_sees_verified_when_confirmed(): void
    {
        $leader = $this->createLeader();
        $this->actingAs($leader, 'web');

        $team = $this->createCompleteTeam($leader);
        $team->update(['is_confirmed' => true]);

        $response = $this->get(route('student.team'));

        $response->assertOk();
        $response->assertSee('Verified');
        $response->assertDontSee('Confirm Team');
    }

    public function test_admin_only_sees_teams_in_assigned_sections(): void
    {
        $this->createAdmin(['BSIT-3A']);

        $leaderA = $this->createLeader();
        $teamA = $this->createCompleteTeam($leaderA, 'Team BSCS');

        $leaderB = Student::factory()->withCompleteProfile()->create([
            'email' => 'leader.b@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $teamB = $this->createCompleteTeam($leaderB, 'Team BSIT');

        $response = $this->get(route('admin.verify_teams'));

        $response->assertOk();
        $response->assertDontSee($teamA->team_name);
        $response->assertSee($teamB->team_name);
    }

    public function test_admin_cannot_verify_team_outside_assigned_sections(): void
    {
        $this->createAdmin(['BSIT-3A']);

        $leaderA = $this->createLeader();
        $teamA = $this->createCompleteTeam($leaderA, 'Team BSCS');

        $leaderB = Student::factory()->withCompleteProfile()->create([
            'email' => 'leader.b@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $teamB = $this->createCompleteTeam($leaderB, 'Team BSIT');

        $response = $this->post(route('admin.verify_teams.verify', $teamA->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('team', [
            'team_id' => $teamA->team_id,
            'is_confirmed' => false,
        ]);

        $response = $this->post(route('admin.verify_teams.verify', $teamB->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('team', [
            'team_id' => $teamB->team_id,
            'is_confirmed' => true,
        ]);
    }

    public function test_admin_without_assigned_sections_sees_no_teams(): void
    {
        $this->createAdmin([]);

        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $response = $this->get(route('admin.verify_teams'));

        $response->assertOk();
        $response->assertDontSee($team->team_name);
        $response->assertSee('No teams awaiting verification');
    }

    public function test_admin_without_assigned_sections_cannot_verify_team(): void
    {
        $this->createAdmin([]);

        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $response = $this->post(route('admin.verify_teams.verify', $team->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'is_confirmed' => false,
        ]);
    }

    public function test_admin_can_verify_team_when_logged_in_via_expert_guard(): void
    {
        $admin = CapstoneExpert::create([
            'email' => 'admin.guard@test.com',
            'first_name' => 'Admin',
            'last_name' => 'Guard',
            'is_admin' => true,
            'approved_status' => true,
            'sections' => ['BSCS-4A'],
        ]);

        Auth::guard('expert')->login($admin);

        $leader = $this->createLeader();
        $team = $this->createCompleteTeam($leader);

        $response = $this->post(route('admin.verify_teams.verify', $team->team_id));

        $response->assertRedirect(route('admin.verify_teams'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('team', [
            'team_id' => $team->team_id,
            'is_confirmed' => true,
        ]);
    }

    public function test_verified_tab_shows_confirmed_teams_only(): void
    {
        $this->createAdmin();

        $leader = $this->createLeader();
        $pendingTeam = $this->createCompleteTeam($leader, 'Team Pending');

        $leaderB = $this->createLeader('leader.verified@my.cspc.edu.ph');
        $verifiedTeam = $this->createCompleteTeam($leaderB, 'Team Done');
        $verifiedTeam->update(['is_confirmed' => true]);

        $response = $this->get(route('admin.verify_teams', ['tab' => 'verified']));

        $response->assertOk();
        $response->assertSee($verifiedTeam->team_name);
        $response->assertDontSee($pendingTeam->team_name);
    }

    public function test_section_filter_limits_teams_in_both_tabs(): void
    {
        $this->createAdmin(['BSCS-4A', 'BSIT-3A']);

        $leaderA = $this->createLeader();
        $bscsTeam = $this->createCompleteTeam($leaderA, 'Team BSCS');

        $leaderB = Student::factory()->withCompleteProfile()->create([
            'email' => 'leader.bsit@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $bsitTeam = $this->createCompleteTeam($leaderB, 'Team BSIT');

        $response = $this->get(route('admin.verify_teams', ['section' => 'BSIT-3A']));

        $response->assertOk();
        $response->assertSee($bsitTeam->team_name);
        $response->assertDontSee($bscsTeam->team_name);
    }
}
