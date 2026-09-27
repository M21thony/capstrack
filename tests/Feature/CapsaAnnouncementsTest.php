<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\CapstoneExpert;
use App\Models\Milestone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CaPSA Announcements page posts announcements only - the old Milestone
 * tab that made milestones from here is gone. Milestone posts made before
 * still show, with their submissions to review.
 */
class CapsaAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    private function actAsCapsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]);
        $this->makeCapsa($capsa);
        $this->actingAs($capsa, 'expert');

        return $capsa;
    }

    public function test_the_page_has_no_milestone_maker(): void
    {
        $this->actAsCapsa();

        $this->get(route('capsa.announcements'))
            ->assertOk()
            ->assertSee('Post Announcement')
            ->assertDontSee('tab-milestone', false)
            ->assertDontSee('-- Select Milestone --')
            ->assertDontSee('Post Milestone');
    }

    public function test_posting_no_longer_makes_a_milestone(): void
    {
        $this->actAsCapsa();

        $this->postJson(route('api.admin.announcements.store'), [
            'title' => 'Title Defense schedule',
            'body' => 'See the schedule.',
            'priority' => 'normal',
            'target_audience' => ['expert'],
            'milestone_title' => 'Title Defense',
            'milestone_due_date' => '2026-10-10',
        ])->assertOk();

        $this->assertSame(0, Milestone::count());
        $this->assertNull(Announcement::sole()->milestone_id);
    }

    public function test_editing_an_old_milestone_post_keeps_its_milestone(): void
    {
        $capsa = $this->actAsCapsa();
        $milestone = Milestone::create(['title' => 'Pre-Proposal', 'description' => 'Old', 'status' => 'todo']);
        $post = Announcement::create([
            'admin_id' => $capsa->expert_id,
            'title' => 'Milestone Reminder: Pre-Proposal',
            'body' => 'Old',
            'priority' => 'normal',
            'target_audience' => ['student'],
            'milestone_id' => $milestone->id,
            'is_active' => true,
        ]);

        $this->get(route('capsa.announcements'))->assertOk()->assertSee('Milestone Reminder: Pre-Proposal');

        $this->putJson(route('api.admin.announcements.update', $post), [
            'title' => 'Milestone Reminder: Pre-Proposal',
            'body' => 'Edited',
            'priority' => 'normal',
            'target_audience' => ['student'],
        ])->assertOk();

        $this->assertSame($milestone->id, $post->fresh()->milestone_id);
        $this->assertSame('Old', $milestone->fresh()->description);
    }
}
