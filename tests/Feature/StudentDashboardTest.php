<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
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

    // The forced onboarding redirect is intentionally disabled - see
    // PersonalInfoOnboardingTest::test_incomplete_profile_can_access_dashboard_directly.
    public function test_student_dashboard_loads_with_incomplete_profile(): void
    {
        $student = Student::factory()->create([
            'email' => 'incomplete@my.cspc.edu.ph',
        ]);

        $response = $this->actingAs($student, 'web')
            ->get(route('student.dashboard'));

        $response->assertOk();
    }

    public function test_a_student_without_a_team_is_pointed_to_forming_one(): void
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
        // The dashboard leads with what to do next; without a team, that is forming one.
        $response->assertViewHas('overallProgress', 0);
        $response->assertSee('Where your team is');
        $response->assertSee('Form or join a team');

        // Verify team name card shows "No Team" since current student doesn't have a team
        $response->assertSee('Team (No Team)');
        $response->assertSee('Join a team to see members here.');
    }

    public function test_student_dashboard_loads_own_team_details(): void
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

        // Team name should appear in the card header
        $response->assertSee('Team (Team Omega)');

        // The student's own name should appear in the team members list
        $response->assertSee($student->f_name);

        // The activity feed left the dashboard: it said what happened, not what to do next.
        $response->assertDontSee('Recent Activity');
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
            'team_status' => 'Active',
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
            'team_status' => 'Active',
        ]);

        $otherTeam = Team::create([
            'team_name' => 'Other Team',
            'team_status' => 'Active',
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

    /**
     * The bug this guards: a team-targeted announcement used to be visible to
     * anyone the fall-through section checks happened to allow, so a classmate
     * in the same section who had nothing to do with the team - or any student
     * at all, when the posting admin covered no sections - read a team's own
     * defense schedule.
     */
    public function test_team_announcement_stays_private_to_that_team_even_in_the_same_section(): void
    {
        $team = Team::create([
            'team_name' => 'Bulbasaur',
            'team_status' => 'Active',
        ]);

        $member = Student::factory()->withCompleteProfile()->create([
            'email' => 'member@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th · A',
        ]);

        $classmate = Student::factory()->withCompleteProfile()->create([
            'email' => 'classmate@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th · A',
        ]);

        $theirTeam = Team::create([
            'team_name' => 'Charmander',
            'team_status' => 'Active',
        ]);

        StudentSchoolYear::create([
            'student_id' => $member->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSIT',
            'student_status' => 'Active',
        ]);

        StudentSchoolYear::create([
            'student_id' => $classmate->student_id,
            'team_id' => $theirTeam->team_id,
            'program' => 'BSIT',
            'student_status' => 'Active',
        ]);

        // No sections on the admin: that is the case the old fall-through
        // waved through for everybody.
        $admin = CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
            'sections' => null,
        ]);

        Announcement::create([
            'admin_id' => $admin->expert_id,
            'title' => 'Bulbasaur Pre-Proposal Defense',
            'body' => 'Bulbasaur has been scheduled for pre-proposal defense.',
            'is_active' => true,
            'target_audience' => ['all'],
            'team_id' => $team->team_id,
        ]);

        $this->actingAs($member, 'web')
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Bulbasaur Pre-Proposal Defense');

        $this->actingAs($classmate, 'web')
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Bulbasaur Pre-Proposal Defense');
    }

    public function test_dashboard_shows_defense_results_only_for_submitted_grades(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'grades.student@my.cspc.edu.ph',
        ]);

        $team = Team::create(['team_name' => 'Team Gradeworthy', 'team_status' => 'Active']);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $schedule = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Pre-proposal Defense',
        ]);

        $chair = CapstoneExpert::create([
            'email' => 'chair@gmail.com',
            'first_name' => 'Chair',
            'last_name' => 'Expert',
            'approved_status' => true,
        ]);
        $member = CapstoneExpert::create([
            'email' => 'member@gmail.com',
            'first_name' => 'Member',
            'last_name' => 'Expert',
            'approved_status' => true,
        ]);

        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $member->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        // Submitted grade - should count.
        PanelGrade::create([
            'defense_id' => $schedule->defense_id,
            'expert_id' => $chair->expert_id,
            'rubric_id' => $this->makeRubricId(),
            'scores' => ['1' => 80],
            'total_score' => 80,
            'verdict' => 'approved',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Draft grade - should NOT count or be visible.
        PanelGrade::create([
            'defense_id' => $schedule->defense_id,
            'expert_id' => $member->expert_id,
            'rubric_id' => $this->makeRubricId(),
            'scores' => ['1' => 60],
            'total_score' => 60,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($student, 'web')->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee('Defense Results');
        $response->assertSee('Pre-proposal Defense');
        $response->assertSee('1 of 2 panelists graded');

        // No score yet. The team's mark is every panelist's total divided by
        // the size of the panel, so with one of two in there is nothing final
        // to show - and showing 80 here would have dropped to 70 the moment
        // the second panelist submitted.
        $response->assertSee('Awaiting the rest of the panel');
        $response->assertDontSee('80 pts');

        $results = $response->viewData('defenseResults');
        $this->assertCount(1, $results);
        $this->assertEquals(1, $results->first()->submitted_count);
        $this->assertEquals(2, $results->first()->assigned_count);
        $this->assertNull($results->first()->average_score);
    }

    // The rule the panel actually uses: add every panelist's total, divide by
    // the panel.
    public function test_the_defense_score_is_the_panel_totals_divided_by_the_panel_size(): void
    {
        [$student, $schedule, $panelists] = $this->makeGradedDefense([90, 81, 75]);

        $response = $this->actingAs($student, 'web')->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee('3 of 3 panelists graded');

        // (90 + 81 + 75) / 3
        $this->assertEquals(82.0, $response->viewData('defenseResults')->first()->average_score);
        $response->assertSee('82 pts');
    }

    // A panelist who never submits must not quietly raise the score by
    // shrinking the divisor.
    public function test_a_missing_panelist_leaves_the_score_unset_rather_than_raising_it(): void
    {
        [$student] = $this->makeGradedDefense([90, 81], panelSize: 3);

        $response = $this->actingAs($student, 'web')->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee('2 of 3 panelists graded');
        $this->assertNull($response->viewData('defenseResults')->first()->average_score);
    }

    /**
     * A team with a panel of $panelSize, of whom the given scores have been
     * submitted (in panel order).
     *
     * @param  array<int, int>  $scores
     * @return array{0: Student, 1: DefenseSchedule, 2: array<int, CapstoneExpert>}
     */
    private function makeGradedDefense(array $scores, ?int $panelSize = null): array
    {
        $panelSize ??= count($scores);

        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'graded.'.$panelSize.count($scores).'@my.cspc.edu.ph',
        ]);
        $team = Team::create(['team_name' => 'Team Scored '.$panelSize.count($scores), 'team_status' => 'Active']);
        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'student_status' => 'Active',
        ]);

        $schedule = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => now()->subDay(),
        ]);

        $roles = ['Panel Chair', 'Panel Member 1', 'Panel Member 2'];
        $panelists = [];

        for ($i = 0; $i < $panelSize; $i++) {
            $expert = CapstoneExpert::factory()->create([
                'approved_status' => true,
                'email' => 'panelist'.$panelSize.count($scores).$i.'@gmail.com',
            ]);
            $panelists[] = $expert;

            DefensePanel::create([
                'defense_id' => $schedule->defense_id,
                'expert_id' => $expert->expert_id,
                'panel_role' => $roles[$i],
                'created_at' => now(),
            ]);

            if (array_key_exists($i, $scores)) {
                PanelGrade::create([
                    'defense_id' => $schedule->defense_id,
                    'expert_id' => $expert->expert_id,
                    'rubric_id' => $this->makeRubricId(),
                    'scores' => ['1' => $scores[$i]],
                    'total_score' => $scores[$i],
                    'verdict' => 'approved',
                    'status' => 'submitted',
                    'submitted_at' => now(),
                ]);
            }
        }

        return [$student, $schedule, $panelists];
    }

    public function test_dashboard_shows_no_defense_results_for_team_with_no_schedule(): void
    {
        $student = Student::factory()->withCompleteProfile()->create([
            'email' => 'nogrades.student@my.cspc.edu.ph',
        ]);

        $team = Team::create(['team_name' => 'Team Fresh', 'team_status' => 'Active']);

        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
            'program' => 'BSCS',
            'student_status' => 'Active',
        ]);

        $response = $this->actingAs($student, 'web')->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee('No defense stages scheduled yet.');
    }

    private function makeRubricId(): int
    {
        return GradingRubric::create([
            'rubric_name' => 'Test Rubric',
            'rubric_type' => 'Pre-Proposal Defense',
            'total_points' => 100,
            'is_active' => true,
        ])->rubric_id;
    }
}
