<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CapstoneExpert;
use App\Models\ExpertRole;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bell shows one person's business, not everyone's.
 *
 * A CaPSA covers named sections and the rest of their portal is scoped to
 * them; the bell used to list every student's activity in the school, so
 * teams they do not handle appeared in it. An expert's bell shows the teams
 * that applied to that expert and nobody else's.
 */
class NotificationBellScopeTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(array $sections, bool $isAdmin = false): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => $isAdmin,
            'sections' => $sections,
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $expert;
    }

    private function studentIn(string $course, string $yearSection, string $name): Student
    {
        return Student::create([
            'email' => strtolower($name).'@my.cspc.edu.ph',
            'f_name' => $name,
            'l_name' => 'Cruz',
            'course' => $course,
            'year_section' => $yearSection,
        ]);
    }

    private function logFor(Student $student, string $description): ActivityLog
    {
        return ActivityLog::create([
            'student_id' => $student->student_id,
            'type' => 'submitted_document',
            'description' => $description,
            'created_at' => now(),
        ]);
    }

    private function notifyExpert(CapstoneExpert $expert, string $title): Notification
    {
        return Notification::create([
            'expert_id' => $expert->expert_id,
            'type' => 'expert_application',
            'title' => $title,
            'created_at' => now(),
        ]);
    }

    public function test_a_capsa_only_sees_activity_from_their_own_sections(): void
    {
        $capsa = $this->capsa(['BSIT-3A']);

        $mine = $this->studentIn('BSIT', '3 · A', 'Inside');
        $theirs = $this->studentIn('BSIS', '4 · B', 'Outside');

        $this->logFor($mine, 'Inside submitted chapter one');
        $this->logFor($theirs, 'Outside submitted chapter one');

        $response = $this->actingAs($capsa, 'expert')->get('/capsa/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Inside submitted chapter one');
        $response->assertDontSee('Outside submitted chapter one');
    }

    /**
     * The admin and CaPSA bell is the expert bell now, so it carries what is
     * addressed to that person rather than the whole school's activity log.
     * Recent Activity did not disappear - it has a page of its own, which the
     * bell's footer links to.
     */
    public function test_the_admin_bell_carries_their_own_notifications_not_the_activity_log(): void
    {
        $admin = $this->capsa([], true);
        $other = $this->capsa([], true);

        $this->notifyExpert($admin, 'Addressed to the admin');
        $this->notifyExpert($other, 'Addressed to somebody else');

        $this->logFor($this->studentIn('BSIT', '3 · A', 'Alpha'), 'Alpha submitted chapter one');

        $response = $this->actingAs($admin, 'expert')->getJson('/instructor/notifications');

        $response->assertStatus(200);
        $response->assertSee('Addressed to the admin');
        $response->assertDontSee('Addressed to somebody else');
        $response->assertDontSee('Alpha submitted chapter one');
    }

    /**
     * A student with no computable section cannot be matched to a CaPSA, so
     * they stay out of a scoped bell rather than falling into everyone's.
     */
    public function test_a_student_with_no_section_is_not_shown_to_a_scoped_capsa(): void
    {
        $capsa = $this->capsa(['BSIT-3A']);

        $nobody = Student::create([
            'email' => 'nosection@my.cspc.edu.ph', 'f_name' => 'Nosection', 'l_name' => 'Cruz',
        ]);

        $this->logFor($nobody, 'Nosection submitted chapter one');

        $response = $this->actingAs($capsa, 'expert')->get('/capsa/dashboard');

        $response->assertDontSee('Nosection submitted chapter one');
    }

    /** An expert's bell carries the teams that applied to that expert. */
    public function test_an_expert_sees_only_applications_made_to_them(): void
    {
        $mine = CapstoneExpert::factory()->create(['approved_status' => true]);
        $other = CapstoneExpert::factory()->create(['approved_status' => true]);

        foreach ([$mine, $other] as $e) {
            $e->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);
        }

        $leader = $this->studentIn('BSIT', '3 · A', 'Leader');

        $myTeam = Team::create([
            'team_name' => 'Applied To Me', 'team_status' => 'Active',
            'leader_id' => $leader->student_id, 'is_confirmed' => true, 'member_count' => 5,
        ]);
        $otherTeam = Team::create([
            'team_name' => 'Applied Elsewhere', 'team_status' => 'Active',
            'leader_id' => $leader->student_id, 'is_confirmed' => true, 'member_count' => 5,
        ]);

        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $myTeam->team_id]);

        TeamExpertAssignment::create([
            'team_id' => $myTeam->team_id, 'expert_id' => $mine->expert_id,
            'expert_role_id' => ExpertRole::where('expert_id', $mine->expert_id)->value('expert_role_id'),
            'status' => 'pending',
        ]);
        TeamExpertAssignment::create([
            'team_id' => $otherTeam->team_id, 'expert_id' => $other->expert_id,
            'expert_role_id' => ExpertRole::where('expert_id', $other->expert_id)->value('expert_role_id'),
            'status' => 'pending',
        ]);

        // An application is an ordinary notification now, so this is what the
        // apply endpoint writes. That it writes one is ExpertApplicationNotice.
        $this->notifyExpert($mine, $myTeam->team_name.' applied to you');
        $this->notifyExpert($other, $otherTeam->team_name.' applied to you');

        $response = $this->actingAs($mine, 'expert')->getJson('/instructor/notifications');

        $response->assertStatus(200);
        $response->assertSee('Applied To Me');
        $response->assertDontSee('Applied Elsewhere');
    }

    /** And the bell is a control, not a picture: a button with a handler. */
    public function test_the_expert_bell_is_a_real_button(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        $html = $this->actingAs($expert, 'expert')->get('/instructor/dashboard')->getContent();

        $this->assertStringContainsString('id="notif-btn"', $html);
        $this->assertStringContainsString('id="notif-dropdown"', $html);
        $this->assertStringNotContainsString('<div class="notif-btn">', $html);

        // The panel is absolutely positioned, so it needs a positioned wrapper
        // of its own - the host pages do not set position on their toolbar, and
        // without this it measured against <body> and opened most of a page
        // below the bell.
        $this->assertStringContainsString('notif-wrap', $html);
    }

    /** With nothing waiting, the unread dot is not lit. */
    public function test_the_dot_only_shows_when_something_is_waiting(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        $html = $this->actingAs($expert, 'expert')->get('/instructor/dashboard')->getContent();

        // The element, not the class name - the partial ships a .notif-dot rule
        // whether or not a dot is drawn.
        $this->assertStringNotContainsString('<div class="notif-dot">', $html);
    }
}
