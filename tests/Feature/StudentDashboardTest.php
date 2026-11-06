<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\CapstoneExpert;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSchedule;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\Notification;
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
        $response->assertSee('Your next step');
        $response->assertSee('Create or join a team');
        $response->assertDontSee('id="now-card"', false);

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

        $response->assertSee('Team Omega');

        // The student's own name should appear in the team members list
        $response->assertSee($student->f_name);
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

        // No score yet. The team's mark is every panelist's total divided by
        // the size of the panel, so with one of two in there is nothing final
        // to show - and showing 80 here would have dropped to 70 the moment
        // the second panelist submitted.
        $schedule = $schedule->fresh();
        $this->assertEquals(1, $schedule->gradesInCount());
        $this->assertEquals(2, $schedule->panelSize());
        $this->assertNull($schedule->panelAverage());
    }

    // The rule the panel actually uses: add every panelist's total, divide by
    // the panel.
    public function test_the_defense_score_is_the_panel_totals_divided_by_the_panel_size(): void
    {
        [, $schedule] = $this->makeGradedDefense([90, 81, 75]);

        // (90 + 81 + 75) / 3
        $this->assertEquals(82.0, $schedule->fresh()->panelAverage());
    }

    // A panelist who never submits must not quietly raise the score by
    // shrinking the divisor.
    public function test_a_missing_panelist_leaves_the_score_unset_rather_than_raising_it(): void
    {
        [, $schedule] = $this->makeGradedDefense([90, 81], panelSize: 3);

        $this->assertEquals(2, $schedule->fresh()->gradesInCount());
        $this->assertNull($schedule->fresh()->panelAverage());
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

    public function test_upcoming_consultations_are_the_teams_own_next_three_soonest_first(): void
    {
        [$student, $team] = $this->studentOnTeam();
        $other = Team::create(['team_name' => 'Other Team', 'team_status' => 'Active']);
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $slot = ConsultationSchedule::create(['expert_id' => $expert->expert_id, 'available_date' => 'Monday', 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'consultation_limit' => 9, 'is_available' => true]);

        $book = fn ($teamId, $days, $status = 'scheduled') => ConsultationBooking::create([
            'student_id' => $student->student_id,
            'team_id' => $teamId,
            'schedule_id' => $slot->schedule_id,
            'session_date' => today()->addDays($days),
            'booking_status' => $status,
        ]);
        $book($team->team_id, 9);
        $book($team->team_id, 2);
        $book($team->team_id, 0);
        $book($team->team_id, 5);
        $book($team->team_id, -1);
        $book($team->team_id, 1, 'cancelled');
        $book($other->team_id, 3);

        $days = $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Today')
            ->viewData('upcomingConsultations')->pluck('days')->all();

        $this->assertSame([0, 2, 5], $days);
    }

    public function test_recent_activity_is_the_students_own_read_ones_included(): void
    {
        [$student] = $this->studentOnTeam();
        $classmate = Student::factory()->withCompleteProfile()->create();

        Notification::create(['student_id' => $student->student_id, 'type' => 'team_verified', 'title' => 'Mine and read', 'message' => 'x', 'is_read' => true, 'created_at' => now()]);
        Notification::create(['student_id' => $classmate->student_id, 'type' => 'team_verified', 'title' => 'Not mine', 'message' => 'x', 'created_at' => now()]);

        $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Mine and read')
            ->assertDontSee('Not mine');
    }

    public function test_the_dashboard_links_to_pages_that_open(): void
    {
        [$student] = $this->studentOnTeam();

        $dashboard = $this->actingAs($student, 'web')->get(route('student.dashboard'));

        foreach (['student.duplication', 'student.documents', 'student.consultation', 'student.progress', 'student.team', 'student.resources'] as $route) {
            $dashboard->assertSee('href="'.route($route).'"', false);
            $this->actingAs($student, 'web')->get(route($route))->assertOk();
        }
    }

    /** @return array{0: Student, 1: Team} */
    private function studentOnTeam(): array
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $team = Team::create(['team_name' => 'Team Dash', 'team_status' => 'Active']);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT', 'student_status' => 'Active']);

        return [$student, $team];
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
