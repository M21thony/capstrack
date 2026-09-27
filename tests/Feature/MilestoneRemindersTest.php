<?php

namespace Tests\Feature;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/** The reminder a team gets a few days before something on its checklist is due. */
class MilestoneRemindersTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    public function test_a_team_is_reminded_of_a_milestone_due_date_until_the_milestone_is_closed(): void
    {
        $capsa = $this->capsaFor();
        [, $leader] = $this->teamInSection('Reminded Team');
        $checklist = $this->emptyChecklist($capsa);
        $this->addDeliverable($checklist, ['title' => 'Undated form']);
        $milestone = $checklist->milestones()->first();
        $milestone->update(['due_on' => today()->addDays(3)->toDateString()]);

        $this->artisan('milestones:send-due-reminders')->assertSuccessful();

        $this->assertSame(1, Notification::where('student_id', $leader->student_id)->where('type', 'milestone_due')->count());

        Notification::query()->delete();
        $milestone->update(['closed_at' => now()]);

        $this->artisan('milestones:send-due-reminders')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'milestone_due')->count());
    }
}
