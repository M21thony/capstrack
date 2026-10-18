<?php

namespace Tests\Feature;

use App\Http\Controllers\Capsa\TeamManagementController;
use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page behind the staff sidebar still opens, for the account that sees
 * the most of it - Admin, CaPSA and adviser at once - and the sidebar on it is
 * in topic order. The sidebar is on all of these pages, so one broken line in
 * it breaks every one of them at once.
 */
class EveryStaffPageStillOpensTest extends TestCase
{
    use RefreshDatabase;

    private function everyHat(): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true, 'sections' => ['BSIT-3A'],
        ]);

        foreach ([CapstoneExpert::CAPSA_ROLE, 'adviser'] as $role) {
            $expert->roles()->attach(Role::firstOrCreate(['role_name' => $role])->role_id);
        }

        return $expert;
    }

    public function test_every_capsa_and_admin_page_opens(): void
    {
        $expert = $this->everyHat();

        $routes = [
            'capsa.dashboard', 'capsa.experts', 'capsa.sections', 'capsa.panel_assignment',
            'capsa.team_management', 'capsa.rubric_management', 'capsa.financial', 'capsa.archive',
            'capsa.resources', 'capsa.announcements', 'capsa.reports', 'capsa.analytics', 'capsa.activitylog',
            'admin.dashboard', 'admin.students', 'admin.settings',
        ];

        foreach ($routes as $route) {
            $this->actingAs($expert, 'expert')->get(route($route))
                ->assertOk()
                ->assertSee('Sign Out');
        }
    }

    public function test_every_team_management_tab_opens(): void
    {
        $expert = $this->everyHat();

        foreach (TeamManagementController::TABS as $tab) {
            // The tabs that became pages of their own send their old URL on
            // to the list, so follow the hop instead of failing on it.
            $this->actingAs($expert, 'expert')
                ->followingRedirects()
                ->get(route('capsa.team_management', ['tab' => $tab]))
                ->assertOk();
        }
    }

    public function test_every_expert_duty_page_opens_from_the_same_account(): void
    {
        $expert = $this->everyHat();

        foreach (['instructor.grading', 'instructor.schedule', 'instructor.docreview'] as $route) {
            $this->actingAs($expert, 'expert')->get(route($route))->assertOk();
        }

        // Team Assignments is the Team Applications tab of Teams now.
        $this->actingAs($expert, 'expert')->get(route('instructor.assignments'))
            ->assertRedirect(route('instructor.teams', ['tab' => 'applications']));
        $this->actingAs($expert, 'expert')->get(route('instructor.teams', ['tab' => 'applications']))
            ->assertRedirect(route('capsa.team_management', ['tab' => 'applications']));
        $this->actingAs($expert, 'expert')->get(route('capsa.team_management', ['tab' => 'applications']))->assertOk();

        // A CaPSA's expert teams are on Team Management: one Teams page, not two.
        $this->actingAs($expert, 'expert')->get(route('instructor.teams'))
            ->assertRedirect(route('capsa.team_management', ['tab' => 'teams']));
    }

    public function test_the_sidebar_is_in_topic_order(): void
    {
        $this->actingAs($this->everyHat(), 'expert')
            ->get(route('capsa.dashboard'))
            ->assertOk()
            ->assertSeeInOrder([
                'Overview', 'Dashboard', 'Announcements',
                'People', 'Expert Management', 'Student Section List', 'Student Accounts',
                'Teams', 'Team Management',
                'Defenses', '/capsa/defenses"', 'Team Grading', 'Grading Rubrics',
                'Advising', 'My Schedule', 'My Evaluations',
                'Records', 'Financial Records', 'Archived Capstones', 'Templates &amp; Lessons',
                'Reports', 'Analytics', 'Activity Log',
                'Account', 'System Settings', 'Sign Out',
            ], false);
    }

    /** A plain CaPSA sees no adviser or admin pages, and no empty headings for them. */
    public function test_a_capsa_only_account_sees_only_its_own_pages(): void
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.dashboard'))
            ->assertOk()
            ->assertSee('Team Management')
            ->assertDontSee('My Schedule')
            ->assertDontSee('Advising')
            ->assertDontSee('System Settings');
    }

    /** The student Team page with no team, an open team, and a locked one. */
    public function test_the_student_team_page_opens_in_every_state(): void
    {
        $make = function (string $email): Student {
            $s = Student::factory()->withCompleteProfile()->create(['email' => $email, 'year_section' => '3rd · A']);
            StudentSchoolYear::create(['student_id' => $s->student_id]);

            return $s;
        };

        $alone = $make('alone@my.cspc.edu.ph');
        $this->actingAs($alone, 'web')->get(route('student.team'))->assertOk();

        $leader = $make('lead@my.cspc.edu.ph');
        $member = $make('mem@my.cspc.edu.ph');
        $team = Team::create(['team_name' => 'Open Team', 'leader_id' => $leader->student_id, 'team_status' => 'Active']);
        StudentSchoolYear::whereIn('student_id', [$leader->student_id, $member->student_id])->update(['team_id' => $team->team_id]);

        $this->actingAs($leader, 'web')->get(route('student.team'))->assertOk()->assertDontSee('Members locked');
        $this->actingAs($member, 'web')->get(route('student.team'))->assertOk()->assertSee('Leave Team');

        DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => 1, 'outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now()]);

        $this->actingAs($leader, 'web')->get(route('student.team'))->assertOk()->assertSee('Members locked');
        $this->actingAs($member, 'web')->get(route('student.team'))->assertOk()->assertSee('Ask CaPSA to Leave');
    }
}
