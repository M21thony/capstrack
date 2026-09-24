<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * New notifications and announcements pop up in the bottom corner of every
 * portal, and a CaPSA's announcement for experts reaches the experts' bells.
 */
class NotificationPopupsTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-3A']]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    private function announce(CapstoneExpert $capsa, array $audience): void
    {
        $this->actingAs($capsa, 'expert')->postJson(route('api.admin.announcements.store'), [
            'title' => 'Title defense moved to Friday',
            'body' => '<p>The <b>title defense</b> is now on Friday.</p>',
            'priority' => 'normal',
            'target_audience' => $audience,
        ])->assertOk();
    }

    public function test_a_student_page_carries_the_popups_fed_by_the_student_feed(): void
    {
        $team = Team::create(['team_name' => 'Popup Team', 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create(['tutorial_completed_at' => now()]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => 'BSIT']);
        Notification::create(['student_id' => $student->student_id, 'type' => 'announcement', 'title' => 'Chapter 1 is due Friday', 'message' => 'Hand it in.', 'is_read' => false]);

        $this->actingAs($student)->get(route('student.progress'))
            ->assertOk()
            ->assertSee('id="nt-stack"', false)
            ->assertSee('data-feed="'.route('notifications.index').'"', false)
            ->assertSee('data-owner="s'.$student->student_id.'"', false)
            ->assertSee('data-type="announcement"', false);
    }

    public function test_every_expert_portal_carries_the_popups_fed_by_the_expert_feed(): void
    {
        $capsa = $this->capsa();

        foreach (['instructor.dashboard', 'capsa.dashboard', 'capsa.archive'] as $page) {
            $this->actingAs($capsa, 'expert')->get(route($page))
                ->assertOk()
                ->assertSee('id="nt-stack"', false)
                ->assertSee('data-feed="'.route('instructor.notifications.index').'"', false)
                ->assertSee('data-owner="e'.$capsa->expert_id.'"', false);
        }
    }

    public function test_an_announcement_for_experts_reaches_every_other_experts_bell(): void
    {
        $capsa = $this->capsa();
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);
        $pending = CapstoneExpert::factory()->create(['approved_status' => false, 'is_admin' => false]);

        $this->announce($capsa, ['expert']);

        $notification = Notification::where('expert_id', $adviser->expert_id)->sole();
        $this->assertSame('announcement', $notification->type);
        $this->assertSame('Title defense moved to Friday', $notification->title);
        $this->assertSame('The title defense is now on Friday.', $notification->message);
        $this->assertFalse(Notification::where('expert_id', $capsa->expert_id)->exists(), 'The CaPSA who posted it is not told about it.');
        $this->assertFalse(Notification::where('expert_id', $pending->expert_id)->exists(), 'An expert not yet approved is not told.');

        // It is in the feed the pop-ups read.
        $this->actingAs($adviser, 'expert')->getJson(route('instructor.notifications.index'))
            ->assertOk()
            ->assertJsonPath('notifications.0.type', 'announcement')
            ->assertJsonPath('notifications.0.is_read', false);
    }

    public function test_an_announcement_for_everyone_reaches_experts_too(): void
    {
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $this->announce($this->capsa(), ['all']);

        $this->assertTrue(Notification::where('expert_id', $adviser->expert_id)->where('type', 'announcement')->exists());
    }

    public function test_a_student_only_announcement_does_not_reach_experts(): void
    {
        $adviser = CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => false]);

        $this->announce($this->capsa(), ['student']);

        $this->assertFalse(Notification::where('expert_id', $adviser->expert_id)->exists());
    }

    public function test_an_expert_who_turned_announcements_off_is_not_told(): void
    {
        $adviser = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false,
            'notification_prefs' => ['web.announcement' => false],
        ]);

        $this->announce($this->capsa(), ['expert']);

        $this->assertFalse(Notification::where('expert_id', $adviser->expert_id)->exists());
    }
}
