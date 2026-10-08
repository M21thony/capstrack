<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefenseCancellation;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\Deliverable;
use App\Models\DeliverableCheck;
use App\Models\Notification;
use App\Models\Student;
use App\Models\Team;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * On the defense day, a team whose requirements above that defense are still
 * open is marked Cancelled. The defense keeps its date, venue and panel, the
 * way the department's own sheet keeps the row, and the team's next try
 * waits under Ready to schedule for the CaPSA's Assign date.
 */
class CancelUnreadyDefensesTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private Team $team;

    private Student $leader;

    private Deliverable $hearingNotice;

    private CapstoneExpert $chair;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $checklist = $this->emptyChecklist($this->capsaFor(['BSIT-3A']));
        $this->hearingNotice = $this->addDeliverable($checklist, ['title' => 'Signed Hearing Notice']);
        $this->addDeliverable($checklist, ['title' => 'Title Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => DefenseOutcome::TITLE_DEFENSE]);
        [$this->team, $this->leader] = $this->teamInSection('Alpha');
        $this->chair = CapstoneExpert::factory()->create(['approved_status' => true]);
    }

    private function defenseOn(Carbon $at, ?string $outcome = null): DefenseSchedule
    {
        $schedule = DefenseSchedule::create([
            'team_id' => $this->team->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => 1,
            'schedule_date' => $at, 'end_date' => $at->copy()->addHours(2), 'venue' => 'Mac Lab', 'outcome' => $outcome,
        ]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $this->chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        return $schedule;
    }

    public function test_a_defense_today_with_open_requirements_is_cancelled(): void
    {
        $defense = $this->defenseOn(today()->setTime(13, 0));

        $this->artisan('defenses:cancel-unready')->assertSuccessful();

        $defense->refresh();
        $this->assertSame(DefenseOutcome::CANCELLED, $defense->outcome);
        $this->assertSame('Signed Hearing Notice', $defense->outcome_remarks);
        $this->assertSame(today()->setTime(13, 0)->toDateTimeString(), $defense->schedule_date->toDateTimeString());
        $this->assertSame('Mac Lab', $defense->venue);
        $this->assertSame(0, DefenseCancellation::count());

        $next = DefenseSchedule::where('team_id', $this->team->team_id)->where('attempt', 2)->sole();
        $this->assertNull($next->schedule_date);
        $this->assertNull($next->outcome);
        $this->assertTrue(DefensePanel::where('defense_id', $next->defense_id)->where('expert_id', $this->chair->expert_id)->exists());

        $this->assertTrue(Notification::where('student_id', $this->leader->student_id)->where('message', 'like', '%requirements were not complete%')->exists());
        $this->assertTrue(Notification::where('expert_id', $this->chair->expert_id)->where('title', 'like', '%cancelled%')->exists());
    }

    public function test_a_defense_whose_requirements_are_done_goes_ahead(): void
    {
        $defense = $this->defenseOn(today()->setTime(13, 0));
        DeliverableCheck::create(['deliverable_id' => $this->hearingNotice->id, 'team_id' => $this->team->team_id, 'status' => DeliverableCheck::COMPLETE, 'checked_at' => now()]);

        $this->artisan('defenses:cancel-unready')->assertSuccessful();

        $this->assertNull($defense->fresh()->outcome);
        $this->assertSame(1, DefenseSchedule::count());
    }

    public function test_only_todays_undecided_defenses_are_looked_at(): void
    {
        $tomorrow = $this->defenseOn(today()->addDay()->setTime(13, 0));
        $this->artisan('defenses:cancel-unready')->assertSuccessful();
        $this->assertNull($tomorrow->fresh()->outcome);

        $tomorrow->delete();
        $decided = $this->defenseOn(today()->setTime(8, 0), 'Passed');
        $this->artisan('defenses:cancel-unready')->assertSuccessful();
        $this->assertSame('Passed', $decided->fresh()->outcome);

        $this->assertSame(1, DefenseSchedule::count());
    }
}
