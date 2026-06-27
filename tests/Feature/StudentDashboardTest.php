<?php

namespace Tests\Feature;

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
            'team_status' => 'Active',
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
            'team_status' => 'Active',
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
        $response->assertSee('No Team Joined');
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
            'team_status' => 'Active',
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

        $response = $this->actingAs($student, 'web')
            ->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertViewHas('myTeam');
        $response->assertViewHas('activities');

        // Team name should appear in the card header
        $response->assertSee('Team (Team Omega)');

        // The student's own name should appear in the team members list
        $response->assertSee($student->f_name);

        // Activities should contain at least the invitation and profile events
        $activities = $response->viewData('activities');
        $this->assertNotEmpty($activities);

        // Verify the invitation to invitee appears in the activity feed text
        $inviteActivity = $activities->first(fn ($a) => str_contains($a->text, $invitee->f_name));
        $this->assertNotNull($inviteActivity, 'Invitation activity not found in activity feed');
    }
}
