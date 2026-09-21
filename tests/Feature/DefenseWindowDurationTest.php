<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * How long a defense runs is a setting of its defense window.
 *
 * Every defense used to be a fixed two-hour block between 8 AM and 4 PM,
 * written into the scheduler. These pin two things: that the defaults are
 * still exactly that, so nothing already saved changes, and that once a stage
 * is set to something else the whole scheduler follows - the times offered,
 * the end time saved, and what counts as a clash.
 */
class DefenseWindowDurationTest extends TestCase
{
    use RefreshDatabase;

    private const STAGE = 'Pre-proposal Defense';

    private const TITLE = 'Title Defense';

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]));
    }

    private function window(string $stage = self::STAGE, array $settings = []): DefenseWindow
    {
        return DefenseWindow::create(['defense_type' => $stage, 'start_date' => '2027-03-15', 'end_date' => '2027-03-16'] + $settings);
    }

    private function save(array $data, string $stage = self::STAGE)
    {
        return $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.windows.update', $stage), ['start_date' => '2027-03-15', 'end_date' => '2027-03-16'] + $data);
    }

    private function team(string $name): Team
    {
        $team = Team::create(['team_name' => $name, 'project_title' => $name.' Title', 'team_status' => 'Active', 'is_confirmed' => true]);
        StudentSchoolYear::create(['student_id' => Student::factory()->withCompleteProfile()->create()->student_id, 'team_id' => $team->team_id]);

        foreach (Team::REQUIRED_EXPERT_ROLES as $roleName) {
            $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
            $role = Role::firstOrCreate(['role_name' => $roleName]);
            $expert->roles()->attach($role->role_id);
            TeamExpertAssignment::create([
                'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
                'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
            ]);
        }

        return $team;
    }

    private function panel(Team $team, CapstoneExpert $chair, string $stage = self::STAGE): DefenseSchedule
    {
        $schedule = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => $stage, 'attempt' => 1]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        return $schedule;
    }

    private function booked(Team $team, string $start, string $end, string $venue = 'Mac Lab', string $stage = self::TITLE): DefenseSchedule
    {
        $schedule = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => $stage, 'attempt' => 1, 'schedule_date' => $start, 'end_date' => $end, 'venue' => $venue]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        return $schedule;
    }

    // -- the slots ----------------------------------------------------------

    /** What every defense was before this could be set. */
    public function test_a_window_with_nothing_set_is_the_old_four_two_hour_blocks(): void
    {
        $window = $this->window();

        $this->assertSame(['08:00', '10:00', '12:00', '14:00'], $window->fresh()->slotTimes());
        $this->assertSame(120, $window->fresh()->durationMinutes());
    }

    public function test_a_stage_with_no_window_still_has_the_default_day(): void
    {
        $this->assertSame(['08:00', '10:00', '12:00', '14:00'], DefenseWindow::forStage(self::TITLE)->slotTimes());
    }

    public function test_the_defense_day_is_cut_into_back_to_back_slots_of_the_chosen_length(): void
    {
        // 8 AM to 5 PM: the last slot may start as late as 4 PM if it finishes by 5.
        $this->assertSame(
            ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'],
            DefenseWindow::slotsFor(60)
        );
        $this->assertSame(['08:00', '09:30', '11:00', '12:30', '14:00', '15:30'], DefenseWindow::slotsFor(90));
        $this->assertSame(['08:00', '08:45', '09:30'], array_slice(DefenseWindow::slotsFor(45), 0, 3));
    }

    public function test_the_last_slot_must_finish_by_five(): void
    {
        // 16:00 + 2 hours would run to 6 PM, so the day still has the old four blocks.
        $this->assertSame(['08:00', '10:00', '12:00', '14:00'], DefenseWindow::slotsFor(120));
        $this->assertSame(['08:00', '12:00'], DefenseWindow::slotsFor(240));
    }

    public function test_every_length_the_page_offers_leaves_at_least_one_slot(): void
    {
        foreach ([15, 20, 30, 45, 60, 75, 90, 105, 120, 150, 180, 240] as $minutes) {
            $this->assertNotEmpty(DefenseWindow::slotsFor($minutes), "{$minutes} minutes leaves no slot");
        }
    }
    // -- saving the settings ------------------------------------------------

    public function test_the_length_is_saved_with_the_dates(): void
    {
        $this->save(['duration_minutes' => 45])
            ->assertOk()
            ->assertJsonPath('data.duration_minutes', 45)
            ->assertJsonPath('data.time_slots.0', '08:00')
            ->assertJsonPath('data.time_slots.1', '08:45');

        $this->assertSame(45, DefenseWindow::where('defense_type', self::STAGE)->first()->durationMinutes());
    }

    /** The page before this field sent only the dates. */
    public function test_saving_only_the_dates_leaves_the_length_alone(): void
    {
        $this->window(settings: ['duration_minutes' => 60]);

        $this->save([])->assertOk()->assertJsonPath('data.duration_minutes', 60);

        $this->assertSame(60, DefenseWindow::first()->durationMinutes());
    }

    public function test_the_length_has_to_be_a_sensible_one(): void
    {
        foreach ([10, 250, 47, 0] as $bad) {
            $this->save(['duration_minutes' => $bad])->assertStatus(422)->assertJsonValidationErrors('duration_minutes');
        }

        $this->assertSame(0, DefenseWindow::count());
    }

    public function test_changing_the_length_does_not_move_what_is_already_scheduled(): void
    {
        $this->window();
        $team = $this->team('Alpha');
        $scheduled = $this->booked($team, '2027-03-15 08:00:00', '2027-03-15 10:00:00', stage: self::STAGE);

        $this->save(['duration_minutes' => 60])
            ->assertOk()
            ->assertJsonPath('data.already_scheduled', 1);

        $scheduled->refresh();
        $this->assertSame('2027-03-15 08:00', $scheduled->schedule_date->format('Y-m-d H:i'));
        $this->assertSame('2027-03-15 10:00', $scheduled->end_date->format('Y-m-d H:i'));
    }

    // -- generating a schedule ----------------------------------------------

    private function draft(string $stage = self::STAGE)
    {
        return $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.schedule-drafts.store'), ['defense_type' => $stage]);
    }

    private function apply(array $items, string $stage = self::STAGE)
    {
        return $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.schedules.update'), ['defense_type' => $stage, 'items' => $items]);
    }

    public function test_the_draft_offers_the_stages_own_times_and_length(): void
    {
        $this->window(settings: ['duration_minutes' => 60]);
        $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $data = $this->draft()->assertOk()->json('data');

        $this->assertSame(60, $data['session_minutes']);
        $this->assertSame(['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'], $data['time_slots']);
    }

    /** Back to back is the point of a shorter defense: one panel, several teams, no gap. */
    public function test_one_panel_fits_more_teams_in_a_day_when_defenses_are_shorter(): void
    {
        $this->window(settings: ['duration_minutes' => 60]);
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);
        foreach (['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'] as $name) {
            $this->panel($this->team($name), $chair);
        }

        $draft = collect($this->draft()->assertOk()->json('data.draft'));

        // All five on the first day: the old two-hour blocks would have spilled onto the second.
        $this->assertCount(5, $draft);
        $this->assertSame(['2027-03-15'], $draft->pluck('date')->unique()->values()->all());
        $this->assertSame(['08:00', '09:00', '10:00', '11:00', '12:00'], $draft->pluck('start_time')->sort()->values()->all());
    }

    public function test_a_saved_defense_ends_when_the_stages_length_says(): void
    {
        $this->window(settings: ['duration_minutes' => 90]);
        $schedule = $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $this->apply([['defense_id' => $schedule->defense_id, 'date' => '2027-03-15', 'start_time' => '09:30', 'venue' => 'Mac Lab']])
            ->assertOk()
            ->assertJsonCount(1, 'data.scheduled');

        $schedule->refresh();
        $this->assertSame('09:30', $schedule->schedule_date->format('H:i'));
        $this->assertSame('11:00', $schedule->end_date->format('H:i'));
    }

    public function test_a_time_that_is_not_one_of_the_stages_slots_is_skipped(): void
    {
        $this->window();
        $schedule = $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $skipped = $this->apply([['defense_id' => $schedule->defense_id, 'date' => '2027-03-15', 'start_time' => '09:00', 'venue' => 'Mac Lab']])
            ->assertOk()
            ->json('data.skipped');

        $this->assertStringContainsString('not one of', $skipped[0]['reason']);
        $this->assertNull($schedule->fresh()->schedule_date);
    }

    // -- clashes ------------------------------------------------------------

    /**
     * Two stages set to different lengths are cut into different slots, so a
     * clash cannot be "the same start time": a room booked 8:00-10:00 is not
     * free at 9:00, whatever slot the other stage calls that.
     */
    public function test_a_shorter_defense_cannot_start_inside_a_longer_one_in_the_same_room(): void
    {
        $this->window(self::STAGE, ['duration_minutes' => 60]);
        $other = $this->team('Other');
        $this->booked($other, '2027-03-15 08:00:00', '2027-03-15 10:00:00', 'Mac Lab');
        $mine = $this->panel($this->team('Mine'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $skipped = $this->apply([['defense_id' => $mine->defense_id, 'date' => '2027-03-15', 'start_time' => '09:00', 'venue' => 'Mac Lab']])
            ->assertOk()
            ->json('data.skipped');

        $this->assertStringContainsString('already booked', $skipped[0]['reason']);
        $this->assertNull($mine->fresh()->schedule_date);
    }

    public function test_the_next_slot_after_a_defense_ends_is_free(): void
    {
        $this->window(self::STAGE, ['duration_minutes' => 60]);
        $this->booked($this->team('Other'), '2027-03-15 08:00:00', '2027-03-15 10:00:00', 'Mac Lab');
        $mine = $this->panel($this->team('Mine'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $this->apply([['defense_id' => $mine->defense_id, 'date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Mac Lab']])
            ->assertOk()
            ->assertJsonCount(1, 'data.scheduled');
    }

    public function test_the_generator_never_places_a_team_inside_a_longer_booking(): void
    {
        $this->window(self::STAGE, ['duration_minutes' => 60]);
        // Both rooms are taken 8:00-10:00 by other defenses.
        $this->booked($this->team('One'), '2027-03-15 08:00:00', '2027-03-15 10:00:00', 'Mac Lab');
        $this->booked($this->team('Two'), '2027-03-15 08:00:00', '2027-03-15 10:00:00', 'Open Lab');
        $this->panel($this->team('Mine'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $row = $this->draft()->assertOk()->json('data.draft.0');

        $this->assertSame('2027-03-15', $row['date']);
        $this->assertSame('10:00', $row['start_time'], 'the first hour after both rooms are free');
    }

    public function test_moving_one_defense_is_checked_against_the_stages_slots(): void
    {
        $this->window(self::STAGE, ['duration_minutes' => 60]);
        $schedule = $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));
        $url = route('api.admin.panel_assignment.schedule.update', $schedule->defense_id);

        // 10:30 is not a slot of a stage that runs in hours.
        $this->actingAs($this->capsa, 'expert')
            ->putJson($url, ['schedule_date' => '2027-03-15', 'start_time' => '10:30', 'venue' => 'Mac Lab'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_time');

        $this->actingAs($this->capsa, 'expert')
            ->putJson($url, ['schedule_date' => '2027-03-15', 'start_time' => '16:00', 'venue' => 'Mac Lab'])
            ->assertOk();

        // The last hour of the 8-to-5 day is a real slot for a one-hour stage.
        $this->assertSame('17:00', $schedule->fresh()->end_date->format('H:i'));
    }

    // -- older defenses -----------------------------------------------------

    public function test_a_defense_with_no_usable_end_time_is_taken_to_be_the_old_two_hours(): void
    {
        $noEnd = new DefenseSchedule(['schedule_date' => '2027-03-15 09:00:00']);
        $zero = new DefenseSchedule(['schedule_date' => '2027-03-15 09:00:00', 'end_date' => '2027-03-15 09:00:00']);
        $real = new DefenseSchedule(['schedule_date' => '2027-03-15 09:00:00', 'end_date' => '2027-03-15 09:45:00']);

        $this->assertSame('11:00', $noEnd->endsAt()->format('H:i'));
        $this->assertSame('11:00', $zero->endsAt()->format('H:i'));
        $this->assertSame('09:45', $real->endsAt()->format('H:i'));
        $this->assertNull((new DefenseSchedule)->endsAt());
    }

    // -- the page -----------------------------------------------------------

    public function test_the_scheduling_page_carries_each_stages_settings(): void
    {
        $this->window(self::STAGE, ['duration_minutes' => 45]);

        $this->actingAs($this->capsa, 'expert')
            ->get(route('capsa.defense_scheduling', ['defense_type' => self::STAGE]))
            ->assertOk()
            ->assertSee('Length of each defense')
            ->assertDontSee('Break between defenses')
            ->assertDontSee('Day starts')
            ->assertViewHas('state', function ($state) {
                return $state['session_minutes'] === 45
                    && array_slice($state['slots'], 0, 3) === ['08:00', '08:45', '09:30']
                    && $state['day'] === ['start' => '08:00', 'end' => '17:00']
                    && $state['windows'][self::STAGE]['duration'] === 45
                    && $state['windows'][self::TITLE]['duration'] === 120;
            });
    }
}
