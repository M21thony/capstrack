<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_dashboard_requires_auth(): void
    {
        $response = $this->get(route('student.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_student_dashboard_requires_completed_profile(): void
    {
        $student = Student::factory()->create([
            'email' => 'incomplete@my.cspc.edu.ph',
        ]);

        $response = $this->actingAs($student, 'web')
            ->get(route('student.dashboard'));

        $response->assertRedirect(route('student.personal-info'));
    }

    public function test_student_dashboard_loads_with_correct_team_groups_filter(): void
    {
        // Current logged-in student (BSCS 4A)
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'student1@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th Year · BSCS-4A',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => null,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        // Student 2 (BSCS 4A) - in a Team
        $student2 = Student::factory()->withCompleteProfile()->create([
            'email' => 'student2@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th Year · BSCS-4A',
        ]);

        $team1 = Team::create([
            'team_name' => 'Team Alpha',
            'team_status' => 'on-going',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student2->student_id,
            'team_id' => $team1->team_id,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        // Student 3 (BSIT 4A) - in a Team (should not be counted for BSCS 4A student)
        $student3 = Student::factory()->withCompleteProfile()->create([
            'email' => 'student3@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th Year · BSIT-4A',
        ]);

        $team2 = Team::create([
            'team_name' => 'Team Beta',
            'team_status' => 'on-going',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student3->student_id,
            'team_id' => $team2->team_id,
            'program' => 'BSIT',
            'student_status' => 'Active',
        ]);

        $response = $this->actingAs($student, 'web')
            ->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertViewHas('teamGroupsCount', 1);
        $response->assertViewHas('docsSubmitted', 0);
        $response->assertViewHas('pendingReviews', 0);
        $response->assertViewHas('overallProgress', 0);

        // Verify team name card shows "No Team" since current student doesn't have a team
        $response->assertSee('Team (No Team)');
        $response->assertSee('Join a team to see members here.');
    }

    public function test_student_dashboard_loads_own_team_details_and_activities(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'student@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th Year · BSCS-4A',
        ]);

        $team = Team::create([
            'team_name' => 'Team Omega',
            'team_status' => 'on-going',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        // Create an invitation to verify activity feed
        $invitee = Student::factory()->withCompleteProfile()->create([
            'email' => 'invitee@my.cspc.edu.ph',
        ]);

        TeamInvitation::create([
            'sender_id' => $student->student_id,
            'receiver_id' => $invitee->student_id,
            'team_id' => $team->team_id,
            'type' => 'invite',
            'status' => 'pending',
        ]);

        // Create an activity_log entry for team creation
        ActivityLog::create([
            'student_id' => $student->student_id,
            'type' => 'team_created',
            'description' => 'Created team <strong>Team Omega</strong>',
            'metadata' => json_encode(['team_id' => $team->team_id, 'team_name' => 'Team Omega']),
            'created_at' => now()->subMinutes(5),
        ]);

        // Create an activity_log entry for inviting someone
        ActivityLog::create([
            'student_id' => $student->student_id,
            'type' => 'invitation_sent',
            'description' => 'Invited <strong>'.e($invitee->f_name.' '.$invitee->l_name).'</strong> to join the team',
            'metadata' => json_encode(['team_id' => $team->team_id, 'invitee_id' => $invitee->student_id]),
            'created_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($student, 'web')
            ->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertViewHas('myTeam');
        $response->assertViewHas('activities');

        // Team name should appear in the card header
        $response->assertSee('Team (Team Omega)');

        // The student's own name should appear in the team members list
        $response->assertSee($student->f_name);

        // Activities should contain at least the team_created and invitation activities
        $activities = $response->viewData('activities');
        $this->assertNotEmpty($activities);

        // Verify the invitation to invitee appears in the activity feed text
        $inviteActivity = $activities->first(fn ($a) => str_contains($a->text, $invitee->f_name));
        $this->assertNotNull($inviteActivity, 'Invitation activity not found in activity feed');
    }

    public function test_student_sees_announcement_targeting_students_from_admin_with_matching_sections(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'student@my.cspc.edu.ph',
        ]);

        $admin = CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
            'sections' => ['BSCS-4A'],
        ]);

        $announcement = Announcement::create([
            'admin_id' => $admin->expert_id,
            'title' => 'Test Announcement',
            'body' => 'This should be visible.',
            'is_active' => true,
            'target_audience' => ['student'],
        ]);

        $response = $this->actingAs($student, 'web')
            ->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee('Test Announcement');
    }

    public function test_student_not_in_admin_sections_does_not_see_announcement(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'student@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th · A',
        ]);

        $admin = CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
            'sections' => ['BSCS-4A'],
        ]);

        $announcement = Announcement::create([
            'admin_id' => $admin->expert_id,
            'title' => 'BSCS Only Announcement',
            'body' => 'This should be hidden.',
            'is_active' => true,
            'target_audience' => ['student'],
        ]);

        $response = $this->actingAs($student, 'web')
            ->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertDontSee('BSCS Only Announcement');
    }

    public function test_team_member_sees_team_mentioned_announcement_even_when_not_in_admin_sections(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'student@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th · A',
        ]);

        $team = Team::create([
            'team_name' => 'Team Alpha',
            'team_status' => 'on-going',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSIT',
            'student_status' => 'Active',
        ]);

        $admin = CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
            'sections' => ['BSCS-4A'],
        ]);

        $announcement = Announcement::create([
            'admin_id' => $admin->expert_id,
            'title' => 'Team Mention Announcement',
            'body' => 'Great job Team Alpha!',
            'is_active' => true,
            'target_audience' => ['student'],
            'team_id' => $team->team_id,
        ]);

        $response = $this->actingAs($student, 'web')
            ->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee('Team Mention Announcement');
    }

    public function test_non_team_member_not_in_admin_sections_does_not_see_team_mentioned_announcement(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'student@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th · A',
        ]);

        $team = Team::create([
            'team_name' => 'Team Beta',
            'team_status' => 'on-going',
        ]);

        $otherTeam = Team::create([
            'team_name' => 'Other Team',
            'team_status' => 'on-going',
        ]);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $otherTeam->team_id,
            'program' => 'BSIT',
            'student_status' => 'Active',
        ]);

        $admin = CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
            'sections' => ['BSCS-4A'],
        ]);

        $announcement = Announcement::create([
            'admin_id' => $admin->expert_id,
            'title' => 'Beta Team Announcement',
            'body' => 'Good job Team Beta!',
            'is_active' => true,
            'target_audience' => ['student'],
            'team_id' => $team->team_id,
        ]);

        $response = $this->actingAs($student, 'web')
            ->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Beta Team Announcement');
    }
}
