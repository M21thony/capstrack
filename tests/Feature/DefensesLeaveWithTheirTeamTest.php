<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\PanelGrade;
use App\Models\Team;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * A team's defenses go when the team does.
 *
 * Deleting a team left its defense schedule, panels and grades behind, and
 * the scheduling board showed each as an unknown team.
 */
class DefensesLeaveWithTheirTeamTest extends TestCase
{
    use RefreshDatabase {
        beginDatabaseTransaction as beginRefreshTransaction;
    }

    /**
     * The live tables are MyISAM, whose foreign keys never cascade, so none
     * here either. SQLite only takes the switch outside a transaction, and
     * gets it back once the test's transaction is rolled back.
     */
    public function beginDatabaseTransaction(): void
    {
        $pdo = DB::connection()->getPdo();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $this->beginRefreshTransaction();
        $this->beforeApplicationDestroyed(fn () => $pdo->exec('PRAGMA foreign_keys = ON'));
    }

    private function team(string $name): int
    {
        return DB::table('team')->insertGetId(['team_name' => $name, 'team_status' => 'Active']);
    }

    /** A graded defense with one panelist, whose calendar event is optional. */
    private function defense(int $teamId, string $date, ?string $googleEventId = null, string $stage = 'Title Defense'): int
    {
        $expert = CapstoneExpert::factory()->create();
        $defenseId = DefenseSchedule::create(['team_id' => $teamId, 'defense_type' => $stage, 'schedule_date' => $date])->defense_id;

        DefensePanel::create(['defense_id' => $defenseId, 'expert_id' => $expert->expert_id, 'panel_role' => 'Panel Chair', 'google_event_id' => $googleEventId, 'created_at' => now()]);
        PanelGrade::create(['defense_id' => $defenseId, 'expert_id' => $expert->expert_id, 'rubric_id' => 1, 'scores' => [], 'total_score' => 85, 'status' => 'submitted']);

        return $defenseId;
    }

    private function assertDefenseGone(int $defenseId): void
    {
        $this->assertDatabaseMissing('defense_schedule', ['defense_id' => $defenseId]);
        $this->assertDatabaseMissing('defense_panels', ['defense_id' => $defenseId]);
        $this->assertDatabaseMissing('panel_grades', ['defense_id' => $defenseId]);
    }

    private function assertDefenseKept(int $defenseId): void
    {
        $this->assertDatabaseHas('defense_schedule', ['defense_id' => $defenseId]);
        $this->assertDatabaseHas('defense_panels', ['defense_id' => $defenseId]);
        $this->assertDatabaseHas('panel_grades', ['defense_id' => $defenseId]);
    }

    public function test_deleting_a_team_removes_its_defenses_and_cancels_only_upcoming_calendar_events(): void
    {
        $calendar = Mockery::mock(GoogleCalendarService::class);
        $calendar->shouldReceive('cancelBookingEvent')->once()->with(Mockery::type('int'), 'upcoming-event');
        $this->app->instance(GoogleCalendarService::class, $calendar);

        $gone = $this->team('Gone');
        $stays = $this->team('Stays');
        $past = $this->defense($gone, now()->subWeek()->toDateTimeString(), 'past-event', 'Pre-proposal Defense');
        $upcoming = $this->defense($gone, now()->addWeek()->toDateTimeString(), 'upcoming-event');
        $other = $this->defense($stays, now()->addWeek()->toDateTimeString());

        Team::find($gone)->delete();

        $this->assertDefenseGone($past);
        $this->assertDefenseGone($upcoming);
        $this->assertDefenseKept($other);
    }

    public function test_a_calendar_failure_does_not_stop_the_team_being_removed(): void
    {
        $calendar = Mockery::mock(GoogleCalendarService::class);
        $calendar->shouldReceive('cancelBookingEvent')->andThrow(new \Exception('token expired'));
        $this->app->instance(GoogleCalendarService::class, $calendar);

        $gone = $this->team('Gone');
        $upcoming = $this->defense($gone, now()->addWeek()->toDateTimeString(), 'upcoming-event');

        Team::find($gone)->delete();

        $this->assertDatabaseMissing('team', ['team_id' => $gone]);
        $this->assertDefenseGone($upcoming);
    }

    public function test_the_migration_clears_defenses_left_by_teams_already_deleted(): void
    {
        $stays = $this->team('Stays');
        $kept = $this->defense($stays, now()->addWeek()->toDateTimeString());
        $orphan = $this->defense(9999, now()->subMonth()->toDateTimeString());
        $gradedThenDropped = $this->defense($stays, now()->subMonth()->toDateTimeString(), null, 'Pre-proposal Defense');
        DB::table('defense_schedule')->where('defense_id', $gradedThenDropped)->delete();

        $migration = require base_path('database/migrations/2026_09_27_133517_delete_defenses_of_teams_that_no_longer_exist.php');
        $migration->up();

        $this->assertDefenseGone($orphan);
        $this->assertDefenseGone($gradedThenDropped);
        $this->assertDefenseKept($kept);
    }

    public function test_deleting_a_defense_takes_its_panel_and_grades(): void
    {
        $dropped = $this->defense($this->team('Stays'), now()->addWeek()->toDateTimeString());

        DefenseSchedule::find($dropped)->delete();

        $this->assertDefenseGone($dropped);
    }
}
