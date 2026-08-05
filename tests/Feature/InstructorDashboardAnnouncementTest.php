<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\CapstoneExpert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorDashboardAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function makeInstructor(): CapstoneExpert
    {
        return CapstoneExpert::factory()->create([
            'is_admin' => false,
            'approved_status' => true,
        ]);
    }

    private function makeAnnouncement(CapstoneExpert $admin, array $audience, string $title): void
    {
        Announcement::create([
            'admin_id' => $admin->expert_id,
            'title' => $title,
            'body' => 'Body for '.$title,
            'priority' => 'normal',
            'target_audience' => $audience,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_instructor_does_not_see_student_only_announcement(): void
    {
        $instructor = $this->makeInstructor();
        $admin = CapstoneExpert::factory()->create(['is_admin' => true, 'approved_status' => true]);

        $this->makeAnnouncement($admin, ['student'], 'Student Only');
        $this->makeAnnouncement($admin, ['expert'], 'Expert Only');

        $response = $this->actingAs($instructor, 'expert')
            ->get(route('instructor.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Student Only');
        $response->assertSee('Expert Only');
    }

    /** @test */
    public function test_instructor_sees_expert_panel_and_all_announcements(): void
    {
        $instructor = $this->makeInstructor();
        $admin = CapstoneExpert::factory()->create(['is_admin' => true, 'approved_status' => true]);

        $this->makeAnnouncement($admin, ['all'], 'Broadcast All');
        $this->makeAnnouncement($admin, ['panel'], 'Panel Announcement');
        $this->makeAnnouncement($admin, ['student', 'expert'], 'Mixed Audience');

        $response = $this->actingAs($instructor, 'expert')
            ->get(route('instructor.dashboard'));

        $response->assertOk();
        $response->assertSee('Broadcast All');
        $response->assertSee('Panel Announcement');
        $response->assertSee('Mixed Audience');
    }
}
