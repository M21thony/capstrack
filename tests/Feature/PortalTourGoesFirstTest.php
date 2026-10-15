<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefenseSchedule;
use App\Models\EvaluationTemplate;
use App\Models\ExpertRole;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Models\TeamInvitation;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * A new account's first page can have the portal tour, a classmate's team
 * invitation and unread notifications all at once. Each opened its own
 * pop-up straight away, and on top of the tour they covered its outline and
 * note. The tour goes first now; the rest wait for it to close.
 */
class PortalTourGoesFirstTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private function newStudentWithAnInvitation(): Student
    {
        $new = Student::factory()->withCompleteProfile()->create(['tutorial_completed_at' => null]);
        StudentSchoolYear::create(['student_id' => $new->student_id]);

        $classmate = Student::factory()->withCompleteProfile()->withTourCompleted()->create();
        $team = Team::create(['team_name' => 'Aptenodytes Fosteri', 'leader_id' => $classmate->student_id, 'team_status' => 'Active', 'member_count' => 1]);
        StudentSchoolYear::create(['student_id' => $classmate->student_id, 'team_id' => $team->team_id]);
        TeamInvitation::create(['sender_id' => $classmate->student_id, 'receiver_id' => $new->student_id, 'team_id' => $team->team_id, 'type' => 'invite', 'status' => 'pending']);
        Notification::create(['student_id' => $new->student_id, 'type' => 'invitation_received', 'title' => 'Team invitation', 'message' => 'You were invited.', 'link' => '/']);

        return $new;
    }

    public function test_the_tour_holds_the_other_pop_ups_until_it_closes(): void
    {
        $page = $this->actingAs($this->newStudentWithAnInvitation(), 'web')->get(route('student.dashboard'))->assertOk();

        $page->assertSee('id="portal-tour"', false)
            ->assertSee('data-auto-open="1"', false)
            // The tour keeps a queue and empties it when it closes.
            ->assertSee('window.afterPortalTour = function (fn, isOpen) {', false)
            ->assertSee('release();', false)
            // The invitation pop-up and the notification pop-ups queue behind it.
            // ...the invitation one covering the page, so it says when it is closed.
            ->assertSee("(window.afterPortalTour || function (fn) { fn(); })(function () { jrpOpen(items); }, function () { return overlay.classList.contains('active'); });", false)
            ->assertSee('if (!q.isOpen()) { clearInterval(watch); nextCovering(); }', false)
            ->assertSee('window.afterPortalTour(function () { popAll(list); });', false);
    }

    /** The invitation's answer can come back before the tour is even read; it waits for the page first. */
    public function test_the_invitation_pop_up_waits_for_the_page_before_deciding(): void
    {
        $popup = File::get(resource_path('views/student/partials/join-request-popup.blade.php'));

        $this->assertStringContainsString("if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go, { once: true });", $popup);
    }

    public function test_every_pop_up_that_opens_by_itself_waits_for_the_tour(): void
    {
        foreach ([
            'views/student/partials/join-request-popup.blade.php',
            'views/partials/notification-toasts.blade.php',
            'views/student/partials/evaluation-popup.blade.php',
            'views/capsa/partials/evaluation-required-popup.blade.php',
            'views/student/team.blade.php',
        ] as $view) {
            $this->assertStringContainsString('afterPortalTour', File::get(resource_path($view)), $view.' waits for the tour');
        }
    }

    /** On a staff account the approval welcome is said first, then the tour. */
    public function test_the_staff_welcome_comes_before_the_tour(): void
    {
        $tour = File::get(resource_path('views/partials/portal-tour.blade.php'));

        $this->assertStringContainsString("var welcome = document.getElementById('welcome-modal');", $tour);
        $this->assertStringContainsString("dismiss.addEventListener('click', function () { setTimeout(open, 250); }, { once: true });", $tour);
    }

    /**
     * The leader's evaluation pop-up opens from the page's first paint - for
     * a new account it does not, not even for a frame, and waits for the tour.
     */
    public function test_a_new_leaders_evaluation_pop_up_is_not_painted_before_the_tour(): void
    {
        $capsa = $this->capsaFor();
        [$team, $leader] = $this->teamInSection('Aptenodytes Fosteri');
        $leader->forceFill(['tutorial_completed_at' => null])->save();
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true]);
        $role = Role::firstOrCreate(['role_name' => 'adviser']);
        $adviser->roles()->attach($role->role_id);
        TeamExpertAssignment::create(['team_id' => $team->team_id, 'expert_id' => $adviser->expert_id, 'status' => 'approved', 'expert_role_id' => ExpertRole::where('expert_id', $adviser->expert_id)->value('expert_role_id')]);
        EvaluationTemplate::factory()->withQuestions()->create(['capsa_id' => $capsa->expert_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'created_at' => now()->subDays(2)]);
        DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'attempt' => 1, 'schedule_date' => now()->subDay(), 'outcome' => DefenseOutcome::PASSED, 'outcome_recorded_at' => now()->subDay()]);

        $this->actingAs($leader, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('id="evp-overlay"', false)
            ->assertSee('if (true) {', false);

        $leader->forceFill(['tutorial_completed_at' => now()])->save();

        $this->actingAs($leader, 'web')->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('if (false) {', false);
    }

    public function test_an_account_that_has_seen_the_tour_gets_its_pop_ups_at_once(): void
    {
        $student = $this->newStudentWithAnInvitation();
        $student->forceFill(['tutorial_completed_at' => now()])->save();

        $this->actingAs($student, 'web')->get(route('student.dashboard'))
            ->assertOk()
            // No tour to wait for, so afterPortalTour runs each one straight away.
            ->assertDontSee('data-auto-open="1"', false);
    }
}
