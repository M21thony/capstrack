<?php

namespace Tests\Feature;

use App\Mail\DefenseScheduledMail;
use App\Models\Announcement;
use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Services\DefenseScheduleNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * When a team's defense gets a date - or a new one - the team is told on
 * CapsTrack and by email, and so is every panelist.
 */
class DefenseScheduleNotificationTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]));
    }

    /** @return array{0: DefenseSchedule, 1: Student, 2: CapstoneExpert} */
    private function unscheduledDefense(): array
    {
        $team = Team::create(['team_name' => 'Notified Team', 'project_title' => 'A Title', 'team_status' => 'Active', 'is_confirmed' => true]);
        $member = Student::factory()->withCompleteProfile()->create(['email' => 'member@my.cspc.edu.ph']);
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        $chair = CapstoneExpert::factory()->create(['approved_status' => true, 'email' => 'chair@gmail.com']);
        $schedule = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => 'Title Defense', 'attempt' => 1]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        return [$schedule, $member, $chair];
    }

    private function setDate(DefenseSchedule $schedule, string $date, string $time = '10:00', string $venue = 'Open Lab')
    {
        return $this->actingAs($this->capsa, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $schedule->defense_id),
            ['schedule_date' => $date, 'start_time' => $time, 'venue' => $venue]
        );
    }

    public function test_the_team_and_the_panel_hear_when_a_defense_is_scheduled(): void
    {
        [$schedule, $member, $chair] = $this->unscheduledDefense();

        $this->setDate($schedule, '2027-03-15')->assertOk();

        Mail::assertQueued(DefenseScheduledMail::class, fn ($m) => $m->hasTo($member->email) && ! $m->forPanelist && ! $m->rescheduled);
        Mail::assertQueued(DefenseScheduledMail::class, fn ($m) => $m->hasTo($chair->email) && $m->forPanelist);

        $this->assertTrue(Notification::where('student_id', $member->student_id)->where('type', 'defense_scheduled')->exists());

        $announcement = Announcement::where('team_id', $schedule->team_id)->first();
        $this->assertNotNull($announcement);
        $this->assertStringContainsString('March 15, 2027', $announcement->body);
        $this->assertSame('important', $announcement->priority);
    }

    public function test_a_new_date_is_sent_as_a_reschedule(): void
    {
        [$schedule, $member] = $this->unscheduledDefense();
        $this->setDate($schedule, '2027-03-15')->assertOk();

        $this->setDate($schedule, '2027-03-17', '14:00')->assertOk();

        Mail::assertQueued(DefenseScheduledMail::class, fn ($m) => $m->hasTo($member->email) && $m->rescheduled);
        $this->assertTrue(Announcement::where('title', 'like', 'Rescheduled:%')->exists());
    }

    /** Re-saving the same date is not news - nobody is told twice. */
    public function test_saving_the_same_date_again_tells_nobody(): void
    {
        [$schedule] = $this->unscheduledDefense();
        $this->setDate($schedule, '2027-03-15')->assertOk();
        $this->setDate($schedule, '2027-03-15')->assertOk();

        $this->assertSame(1, Announcement::count());
        Mail::assertQueuedCount(2); // one member + one panelist, once
    }

    public function test_the_announcement_shows_on_the_teams_dashboard(): void
    {
        [$schedule, $member] = $this->unscheduledDefense();
        $member->update(['course' => 'BSIT', 'year_section' => '3rd · A']);
        $this->setDate($schedule, '2027-03-15')->assertOk();

        $this->actingAs($member, 'web')
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Title Defense for Notified Team');
    }

    /** A mail server that is down must not stop the date from being saved. */
    public function test_a_failing_email_does_not_undo_the_schedule(): void
    {
        [$schedule] = $this->unscheduledDefense();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $schedule->update(['schedule_date' => '2027-03-15 10:00:00', 'end_date' => '2027-03-15 12:00:00', 'venue' => 'Mac Lab']);
        app(DefenseScheduleNotifier::class)->scheduled($schedule);

        $this->assertNotNull($schedule->fresh()->schedule_date);
        $this->assertTrue(Announcement::where('team_id', $schedule->team_id)->exists());
    }
}
