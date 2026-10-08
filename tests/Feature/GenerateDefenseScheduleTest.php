<?php

namespace Tests\Feature;

use App\Mail\DefenseScheduledMail;
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
use App\Support\DefenseBoard;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Generating a stage's schedule at once: every ready team is suggested a slot
 * that clashes with nothing, every other team is listed with what it still
 * needs, and the CaPSA saves the reviewed set in one go.
 */
class GenerateDefenseScheduleTest extends TestCase
{
    use RefreshDatabase;

    private const STAGE = 'Pre-proposal Defense';

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true]));
        DefenseWindow::create(['defense_type' => self::STAGE, 'start_date' => '2027-03-15', 'end_date' => '2027-03-16']);
    }

    private function team(string $name, bool $staffed = true): Team
    {
        $team = Team::create(['team_name' => $name, 'project_title' => $name.' Title', 'team_status' => 'Active', 'is_confirmed' => true]);
        $member = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

        return $staffed ? $this->staff($team) : $team;
    }

    /** Gives a team the adviser, consultant and grammarian it needs to be ready. */
    private function staff(Team $team): Team
    {
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

    private function draft(string $stage = self::STAGE)
    {
        return $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.schedule-drafts.store'), ['defense_type' => $stage]);
    }

    private function board(string $stage = self::STAGE)
    {
        return $this->actingAs($this->capsa, 'expert')
            ->getJson(route('api.admin.panel_assignment.board', ['defense_type' => $stage]));
    }

    // ── the suggestion ───────────────────────────────────────────────────

    public function test_every_ready_team_is_suggested_a_slot_and_nothing_is_saved(): void
    {
        $schedule = $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $data = $this->draft()->assertOk()->json('data');

        $this->assertCount(1, $data['draft']);
        $this->assertSame($schedule->defense_id, $data['draft'][0]['defense_id']);
        $this->assertSame('2027-03-15', $data['draft'][0]['date']);
        $this->assertNull($schedule->fresh()->schedule_date, 'a draft saves nothing');
    }

    /** Two teams in one draft never get the same slot, though neither is saved yet. */
    public function test_teams_in_one_draft_never_share_a_slot(): void
    {
        $chairA = CapstoneExpert::factory()->create(['approved_status' => true]);
        $chairB = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->panel($this->team('Alpha'), $chairA);
        $this->panel($this->team('Bravo'), $chairB);
        $this->panel($this->team('Charlie'), $chairA); // same panel as Alpha: back to back, not together

        $draft = collect($this->draft()->assertOk()->json('data.draft'));

        $this->assertCount(3, $draft);
        $slots = $draft->map(fn ($r) => $r['date'].' '.$r['start_time'].' '.$r['venue']);
        $this->assertSame($slots->count(), $slots->unique()->count());

        $alpha = $draft->firstWhere('team_name', 'Alpha');
        $charlie = $draft->firstWhere('team_name', 'Charlie');
        $this->assertNotSame($alpha['date'].$alpha['start_time'], $charlie['date'].$charlie['start_time']);
    }

    public function test_teams_that_are_not_ready_are_listed_with_what_they_need(): void
    {
        $this->team('No Panel');
        $this->panel($this->team('No Experts', staffed: false), CapstoneExpert::factory()->create(['approved_status' => true]));

        $notReady = collect($this->draft()->assertOk()->json('data.not_ready'))->pluck('reason', 'team_name');

        $this->assertSame('No panel assigned yet.', $notReady['No Panel']);
        $this->assertSame('Needs an adviser and a consultant.', $notReady['No Experts']);
    }

    public function test_a_later_stage_needs_the_one_before_it_passed(): void
    {
        DefenseWindow::create(['defense_type' => 'Title Defense', 'start_date' => '2027-04-01', 'end_date' => '2027-04-02']);
        $this->panel($this->team('Early'), CapstoneExpert::factory()->create(['approved_status' => true]), 'Title Defense');

        $notReady = collect($this->draft('Title Defense')->assertOk()->json('data.not_ready'))->pluck('reason', 'team_name');

        $this->assertSame('Has not passed the Pre-proposal Defense yet.', $notReady['Early']);
    }

    public function test_scheduled_and_finished_teams_are_not_suggested_again(): void
    {
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->panel($this->team('Booked'), $chair)->update(['schedule_date' => '2027-03-15 08:00:00', 'venue' => 'Mac Lab']);
        $this->panel($this->team('Passed'), $chair)->update(['outcome' => DefenseOutcome::PASSED]);

        $data = $this->draft()->assertOk()->json('data');

        $this->assertSame([], $data['draft']);
        $this->assertSame(1, $data['already_scheduled']);
        $this->assertSame([], $data['not_ready']);
    }

    public function test_no_window_means_nothing_to_schedule_into(): void
    {
        DefenseWindow::query()->delete();

        $this->draft()->assertStatus(422)->assertJson(fn ($j) => $j->where('message', fn ($m) => str_contains($m, 'window first'))->etc());
    }

    // ── saving the reviewed draft ────────────────────────────────────────

    private function apply(array $items)
    {
        return $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.schedules.update'), ['defense_type' => self::STAGE, 'items' => $items]);
    }

    public function test_the_reviewed_draft_is_saved_and_announced(): void
    {
        $schedule = $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));

        // The CaPSA moved the suggestion to the afternoon in the Open Lab.
        $this->apply([['defense_id' => $schedule->defense_id, 'date' => '2027-03-16', 'start_time' => '15:00', 'venue' => 'Open Lab']])
            ->assertOk()
            ->assertJsonCount(1, 'data.scheduled');

        $schedule->refresh();
        $this->assertSame('2027-03-16 15:00', $schedule->schedule_date->format('Y-m-d H:i'));
        $this->assertSame('Open Lab', $schedule->venue);
        Mail::assertQueued(DefenseScheduledMail::class);
    }

    public function test_a_clashing_row_is_skipped_and_the_rest_are_saved(): void
    {
        $a = $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));
        $b = $this->panel($this->team('Bravo'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $response = $this->apply([
            ['defense_id' => $a->defense_id, 'date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Mac Lab'],
            ['defense_id' => $b->defense_id, 'date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Mac Lab'],
        ])->assertOk();

        $this->assertNotNull($a->fresh()->schedule_date);
        $this->assertNull($b->fresh()->schedule_date);
        $this->assertStringContainsString('Mac Lab is taken at this time', $response->json('data.skipped.0.reason'));
    }

    public function test_teams_led_by_the_same_chair_are_one_panel_in_one_room(): void
    {
        // The seat beside the chair changes from team to team, but the chair
        // leading both means one panel: it judges them back-to-back in the one
        // room it has that day. This is the case that used to be read as two
        // different panels sharing a panelist.
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);
        $alpha = $this->panel($this->team('Alpha'), $chair);
        $bravo = $this->panel($this->team('Bravo'), $chair);

        foreach ([$alpha, $bravo] as $schedule) {
            DefensePanel::create([
                'defense_id' => $schedule->defense_id,
                'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
                'panel_role' => 'Panel Member 1',
                'created_at' => now(),
            ]);
        }

        $draft = collect($this->draft()->assertOk()->json('data.draft'));

        $a = $draft->firstWhere('team_name', 'Alpha');
        $b = $draft->firstWhere('team_name', 'Bravo');

        $this->assertSame($a['venue'], $b['venue'], "the chair's panel keeps one room for the day");
        $this->assertNotSame($a['date'].$a['start_time'], $b['date'].$b['start_time'], 'and never sits twice at once');
        // Back-to-back in its own room, so the second team is not even flagged.
        $this->assertNull($b['skipped']);
    }

    public function test_one_panel_saves_two_teams_back_to_back_in_its_room(): void
    {
        // Saving that reviewed draft must not refuse the second team because
        // the chair's panel is "already on a panel that day".
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);
        $alpha = $this->panel($this->team('Alpha'), $chair);
        $bravo = $this->panel($this->team('Bravo'), $chair);

        foreach ([$alpha, $bravo] as $schedule) {
            DefensePanel::create([
                'defense_id' => $schedule->defense_id,
                'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
                'panel_role' => 'Panel Member 1',
                'created_at' => now(),
            ]);
        }

        $response = $this->apply([
            ['defense_id' => $alpha->defense_id, 'date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Mac Lab'],
            ['defense_id' => $bravo->defense_id, 'date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Mac Lab'],
        ])->assertOk();

        $this->assertSame([], $response->json('data.skipped'));
        $this->assertCount(2, $response->json('data.scheduled'));
        $this->assertSame('2027-03-15 08:00', $alpha->fresh()->schedule_date->format('Y-m-d H:i'));
        $this->assertSame('2027-03-15 10:00', $bravo->fresh()->schedule_date->format('Y-m-d H:i'));
    }

    public function test_a_chairs_panel_is_never_saved_into_a_second_room_that_day(): void
    {
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);
        $alpha = $this->panel($this->team('Alpha'), $chair);
        $bravo = $this->panel($this->team('Bravo'), $chair);

        foreach ([$alpha, $bravo] as $schedule) {
            DefensePanel::create([
                'defense_id' => $schedule->defense_id,
                'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
                'panel_role' => 'Panel Member 1',
                'created_at' => now(),
            ]);
        }

        $response = $this->apply([
            ['defense_id' => $alpha->defense_id, 'date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Mac Lab'],
            ['defense_id' => $bravo->defense_id, 'date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Open Lab'],
        ])->assertOk();

        $skipped = collect($response->json('data.skipped'))->pluck('reason', 'team_name');
        $this->assertNotNull($alpha->fresh()->schedule_date);
        $this->assertNull($bravo->fresh()->schedule_date);
        $this->assertStringContainsString($chair->name.' is in Mac Lab that day for Alpha (Pre-proposal Defense), so this panel has to use Mac Lab too.', $skipped['Bravo']);
    }

    public function test_the_same_panel_cannot_save_two_defenses_in_one_block(): void
    {
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);
        $alpha = $this->panel($this->team('Alpha'), $chair);
        $bravo = $this->panel($this->team('Bravo'), $chair);

        foreach ([$alpha, $bravo] as $schedule) {
            DefensePanel::create([
                'defense_id' => $schedule->defense_id,
                'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id,
                'panel_role' => 'Panel Member 1',
                'created_at' => now(),
            ]);
        }

        $response = $this->apply([
            ['defense_id' => $alpha->defense_id, 'date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Mac Lab'],
            ['defense_id' => $bravo->defense_id, 'date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Open Lab'],
        ])->assertOk();

        $skipped = collect($response->json('data.skipped'))->pluck('reason', 'team_name');
        $this->assertNotNull($alpha->fresh()->schedule_date);
        $this->assertNull($bravo->fresh()->schedule_date);
        $this->assertStringContainsString($chair->name." is already on Alpha (Pre-proposal Defense)'s panel at this time.", $skipped['Bravo']);
    }

    public function test_a_panelist_shared_by_two_different_panels_cannot_save_two_matches_in_one_block(): void
    {
        // Different chairs, so different panels - but one person sits on both,
        // and cannot be in two rooms in the same block.
        $shared = CapstoneExpert::factory()->create(['approved_status' => true]);
        $alpha = $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));
        $bravo = $this->panel($this->team('Bravo'), CapstoneExpert::factory()->create(['approved_status' => true]));

        foreach ([$alpha, $bravo] as $schedule) {
            DefensePanel::create([
                'defense_id' => $schedule->defense_id,
                'expert_id' => $shared->expert_id,
                'panel_role' => 'Panel Member 1',
                'created_at' => now(),
            ]);
        }

        $response = $this->apply([
            ['defense_id' => $alpha->defense_id, 'date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Mac Lab'],
            ['defense_id' => $bravo->defense_id, 'date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Open Lab'],
        ])->assertOk();

        $skipped = collect($response->json('data.skipped'))->pluck('reason', 'team_name');
        $this->assertNotNull($alpha->fresh()->schedule_date);
        $this->assertNull($bravo->fresh()->schedule_date);
        $this->assertStringContainsString($shared->name." is already on Alpha (Pre-proposal Defense)'s panel at this time.", $skipped['Bravo']);
    }

    public function test_a_panel_keeps_its_day_and_room_for_its_next_team(): void
    {
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);
        $alpha = $this->panel($this->team('Alpha'), $chair);
        $bravo = $this->panel($this->team('Bravo'), $chair);

        // The same panel already defends out of Mac Lab on the first day.
        $alpha->update(['schedule_date' => '2027-03-15 08:00:00', 'end_date' => '2027-03-15 10:00:00', 'venue' => 'Mac Lab']);

        $row = collect($this->draft()->assertOk()->json('data.draft'))->firstWhere('team_name', 'Bravo');

        $this->assertSame('2027-03-15', $row['date']);
        $this->assertSame('10:00', $row['start_time']);
        $this->assertSame('Mac Lab', $row['venue']);
    }

    public function test_a_panel_is_never_suggested_a_second_room_on_a_day_it_already_defends(): void
    {
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);

        // The panel defends out of Mac Lab at 8am, and the rest of that room's
        // day is taken by other panels - so its next team has to go to another
        // day rather than move to the Open Lab with the panel.
        $booked = $this->panel($this->team('Booked'), $chair);
        $booked->update(['schedule_date' => '2027-03-15 08:00:00', 'end_date' => '2027-03-15 10:00:00', 'venue' => 'Mac Lab']);

        foreach (['10:00', '13:00', '15:00'] as $time) {
            $other = $this->panel($this->team('Filler '.$time), CapstoneExpert::factory()->create(['approved_status' => true]));
            $other->update(['schedule_date' => '2027-03-15 '.$time.':00', 'end_date' => null, 'venue' => 'Mac Lab']);
        }

        $this->panel($this->team('Extra'), $chair);
        $row = collect($this->draft()->assertOk()->json('data.draft'))->firstWhere('team_name', 'Extra');

        // The next day, in the room the panel defends out of.
        $this->assertSame('2027-03-16', $row['date']);
        $this->assertSame('08:00', $row['start_time']);
        $this->assertSame('Mac Lab', $row['venue']);
    }

    /** Suggests and saves the whole draft, as the CaPSA does after reviewing it. */
    private function generateAndSave(): array
    {
        $draft = $this->draft()->assertOk()->json('data.draft');
        $this->apply(array_map(fn ($r) => ['defense_id' => $r['defense_id'], 'date' => $r['date'], 'start_time' => $r['start_time'], 'venue' => $r['venue']], $draft))->assertOk();

        return collect($draft)->keyBy('team_name')->all();
    }

    public function test_a_waiting_teams_slot_in_its_panels_room_is_never_given_to_another_panel(): void
    {
        // Two teams share a panel; one is not ready yet (no grammarian - the
        // same as an unpaid fee). A busy stage fills every other slot of day
        // one, but the slot after the ready team stays empty for the waiting one.
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->panel($this->team('Ready'), $chair);
        $waiting = $this->team('Waiting', staffed: false);
        $this->panel($waiting, $chair);

        foreach (range(1, 40) as $i) {
            $this->panel($this->team('Other '.$i), CapstoneExpert::factory()->create(['approved_status' => true]));
        }

        $first = $this->generateAndSave();

        $this->assertSame('2027-03-15 08:00 NAS Lab', $first['Ready']['date'].' '.$first['Ready']['start_time'].' '.$first['Ready']['venue']);
        $this->assertFalse(
            DefenseSchedule::where('venue', 'NAS Lab')->where('schedule_date', '2027-03-15 10:00:00')->exists(),
            "the waiting team's slot stays empty"
        );
        $this->assertSame(31, DefenseSchedule::whereDate('schedule_date', '2027-03-15')->count(), 'every other slot that day is used');

        // Once the team is ready it goes straight into the slot kept for it.
        $this->staff($waiting);
        $row = collect($this->draft()->assertOk()->json('data.draft'))->firstWhere('team_name', 'Waiting');

        $this->assertSame('2027-03-15', $row['date']);
        $this->assertSame('10:00', $row['start_time']);
        $this->assertSame('NAS Lab', $row['venue']);
    }

    public function test_the_five_teams_of_one_panel_stay_on_one_day_when_two_are_ready_late(): void
    {
        // The case the CaPSA asked about: five teams, one panel, three ready.
        // Hour-long defenses, so a room holds eight a day and all five fit.
        DefenseWindow::where('defense_type', self::STAGE)->update(['duration_minutes' => 60]);
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);

        foreach (['T1', 'T2', 'T3'] as $name) {
            $this->panel($this->team($name), $chair);
        }
        $late = [$this->team('T4', staffed: false), $this->team('T5', staffed: false)];
        foreach ($late as $team) {
            $this->panel($team, $chair);
        }
        foreach (range(1, 70) as $i) {
            $this->panel($this->team('Other '.$i), CapstoneExpert::factory()->create(['approved_status' => true]));
        }

        $this->generateAndSave();
        array_map(fn ($team) => $this->staff($team), $late);
        $this->generateAndSave();

        $panel = DefenseSchedule::whereHas('team', fn ($q) => $q->whereIn('team_name', ['T1', 'T2', 'T3', 'T4', 'T5']))->get();
        $this->assertCount(1, $panel->map(fn ($s) => $s->schedule_date->toDateString().' '.$s->venue)->unique(), 'one day, one room');
        $this->assertSame(['08:00', '09:00', '10:00', '11:00', '13:00'], $panel->map(fn ($s) => $s->schedule_date->format('H:i'))->sort()->values()->all());
    }

    public function test_a_panel_starts_its_day_where_all_its_teams_fit(): void
    {
        // Other panels already fill day one but for the last slot in two
        // rooms. A panel with two teams (one still waiting) would be split
        // across days there, so it starts on day two instead.
        foreach (range(1, 30) as $i) {
            $this->panel($this->team('Other '.$i), CapstoneExpert::factory()->create(['approved_status' => true]));
        }
        $this->generateAndSave();

        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->panel($this->team('Ready'), $chair);
        $this->panel($this->team('Waiting', staffed: false), $chair);

        $row = collect($this->draft()->assertOk()->json('data.draft'))->firstWhere('team_name', 'Ready');

        $this->assertSame('2027-03-16', $row['date']);
        $this->assertSame('08:00', $row['start_time']);
        $this->assertStringStartsWith('Not earlier: Mar 15', $row['skipped'], 'the CaPSA is told why day one was passed over');
    }

    public function test_a_row_outside_the_window_or_not_ready_is_skipped(): void
    {
        $ready = $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));
        $unready = $this->panel($this->team('Loose', staffed: false), CapstoneExpert::factory()->create(['approved_status' => true]));

        $skipped = collect($this->apply([
            ['defense_id' => $ready->defense_id, 'date' => '2027-05-01', 'start_time' => '08:00', 'venue' => 'Mac Lab'],
            ['defense_id' => $unready->defense_id, 'date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Mac Lab'],
        ])->assertOk()->json('data.skipped'))->pluck('reason', 'team_name');

        $this->assertStringContainsString('outside', $skipped['Alpha']);
        $this->assertStringContainsString('No longer ready', $skipped['Loose']);
        $this->assertSame(0, DefenseSchedule::whereNotNull('schedule_date')->count());
    }

    // ── one room per person per day, weekdays, lengths ──────────────────

    public function test_a_member_shared_by_two_chairs_is_kept_in_one_room_that_day(): void
    {
        $shared = CapstoneExpert::factory()->create(['approved_status' => true]);
        $alpha = $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));
        $bravo = $this->panel($this->team('Bravo'), CapstoneExpert::factory()->create(['approved_status' => true]));

        foreach ([$alpha, $bravo] as $schedule) {
            DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $shared->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);
        }

        $alpha->update(['schedule_date' => '2027-03-15 08:00:00', 'end_date' => '2027-03-15 10:00:00', 'venue' => 'Mac Lab']);

        $row = collect($this->draft()->assertOk()->json('data.draft'))->firstWhere('team_name', 'Bravo');

        $this->assertSame('2027-03-15', $row['date']);
        $this->assertSame('10:00', $row['start_time']);
        $this->assertSame('Mac Lab', $row['venue']);
    }

    public function test_a_chair_who_sits_on_another_panel_cannot_be_put_in_a_second_room_that_day(): void
    {
        $person = CapstoneExpert::factory()->create(['approved_status' => true]);
        $alpha = $this->panel($this->team('Alpha'), $person);
        $bravo = $this->panel($this->team('Bravo'), CapstoneExpert::factory()->create(['approved_status' => true]));
        DefensePanel::create(['defense_id' => $bravo->defense_id, 'expert_id' => $person->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        $skipped = collect($this->apply([
            ['defense_id' => $alpha->defense_id, 'date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Mac Lab'],
            ['defense_id' => $bravo->defense_id, 'date' => '2027-03-15', 'start_time' => '13:00', 'venue' => 'Open Lab'],
        ])->assertOk()->json('data.skipped'))->pluck('reason', 'team_name');

        $this->assertNull($bravo->fresh()->schedule_date);
        $this->assertStringContainsString($person->name.' is in Mac Lab that day', $skipped['Bravo']);
    }

    public function test_weekends_are_never_suggested(): void
    {
        DefenseWindow::where('defense_type', self::STAGE)->update(['start_date' => '2027-03-13', 'end_date' => '2027-03-15']);
        $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $this->assertSame('2027-03-15', $this->draft()->assertOk()->json('data.draft.0.date'));
    }

    public function test_times_that_have_passed_are_never_suggested_or_saved(): void
    {
        $schedule = $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));
        Carbon::setTestNow('2027-03-17 09:00:00');

        try {
            $this->assertSame('Alpha', $this->draft()->assertOk()->json('data.unplaced.0.team_name'));

            $skipped = collect($this->apply([['defense_id' => $schedule->defense_id, 'date' => '2027-03-16', 'start_time' => '08:00', 'venue' => 'Mac Lab']])
                ->assertOk()->json('data.skipped'))->pluck('reason', 'team_name');
            $this->assertStringContainsString('already passed', $skipped['Alpha']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_the_length_sets_the_start_times_and_lunch_stays_free(): void
    {
        $this->assertSame(['08:00', '10:00', '13:00', '15:00'], DefenseBoard::slots(120));
        $this->assertSame(['08:00', '09:30', '13:00', '14:30'], DefenseBoard::slots(90));
        $this->assertSame(['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'], DefenseBoard::slots(60));
    }

    public function test_an_hour_long_stage_books_back_to_back_and_overlaps_clash(): void
    {
        DefenseWindow::where('defense_type', self::STAGE)->update(['duration_minutes' => 60]);
        $chair = CapstoneExpert::factory()->create(['approved_status' => true]);
        $this->panel($this->team('Alpha'), $chair);
        $this->panel($this->team('Bravo'), $chair);

        $draft = collect($this->draft()->assertOk()->json('data.draft'))->keyBy('team_name');
        $this->assertSame(['08:00', '09:00'], [$draft['Alpha']['start_time'], $draft['Bravo']['start_time']]);
        $this->assertSame($draft['Alpha']['venue'], $draft['Bravo']['venue']);

        // A 2-hour defense from 8:00 still holds the room at 9:00.
        $long = $this->panel($this->team('Long'), CapstoneExpert::factory()->create(['approved_status' => true]), 'Title Defense');
        $long->update(['schedule_date' => '2027-03-15 08:00:00', 'end_date' => '2027-03-15 10:00:00', 'venue' => 'NAS Lab']);
        $charlie = $this->panel($this->team('Charlie'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $skipped = collect($this->apply([['defense_id' => $charlie->defense_id, 'date' => '2027-03-15', 'start_time' => '09:00', 'venue' => 'NAS Lab']])
            ->assertOk()->json('data.skipped'))->pluck('reason', 'team_name');
        $this->assertStringContainsString('NAS Lab is taken at this time', $skipped['Charlie']);
    }

    /** Only experts, a panel and the stage before hold a team back; the rest is a warning. */
    public function test_a_team_whose_adviser_sits_on_its_panel_is_still_scheduled_with_a_warning(): void
    {
        $team = $this->team('Alpha');
        $adviser = CapstoneExpert::find(TeamExpertAssignment::where('team_id', $team->team_id)->orderBy('id')->value('expert_id'));
        $this->panel($team, $adviser);

        $data = $this->draft()->assertOk()->json('data');

        $this->assertSame(['Alpha'], array_column($data['draft'], 'team_name'));
        $this->assertSame([], $data['not_ready']);
        $warning = collect($this->board()->json('data.teams.0.blockers'))->firstWhere('key', 'own-panelist');
        $this->assertTrue($warning['warn']);
        $this->assertStringContainsString($adviser->name." is this team's adviser", $warning['text']);
    }

    public function test_a_team_without_a_grammarian_is_still_scheduled_with_a_warning(): void
    {
        $team = $this->team('Alpha');
        TeamExpertAssignment::where('team_id', $team->team_id)->get()->firstWhere('engaged_role_name', 'grammarian')->delete();
        $this->panel($team, CapstoneExpert::factory()->create(['approved_status' => true]));

        $this->assertSame(['Alpha'], array_column($this->draft()->assertOk()->json('data.draft'), 'team_name'));
        $this->assertContains(['key' => 'grammarian', 'text' => 'No grammarian yet', 'warn' => true], $this->board()->json('data.teams.0.blockers'));
    }

    public function test_the_window_saves_its_length(): void
    {
        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.windows.update', self::STAGE), ['start_date' => '2027-03-15', 'end_date' => '2027-03-16', 'duration_minutes' => 90])
            ->assertOk()
            ->assertJsonPath('data.slots', ['08:00', '09:30', '13:00', '14:30']);

        $this->assertSame(90, DefenseWindow::where('defense_type', self::STAGE)->value('duration_minutes'));
    }

    /** A custom length, not one of the buttons, within half an hour to a whole morning. */
    public function test_the_window_takes_a_custom_length(): void
    {
        $put = fn (int $minutes) => $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.windows.update', self::STAGE), ['start_date' => '2027-03-15', 'end_date' => '2027-03-16', 'duration_minutes' => $minutes]);

        $put(80)->assertOk()->assertJsonPath('data.slots', ['08:00', '09:20', '10:40', '13:00', '14:20', '15:40']);
        $this->assertSame(80, DefenseWindow::where('defense_type', self::STAGE)->value('duration_minutes'));

        $put(20)->assertStatus(422);
        $put(300)->assertStatus(422);
        $this->assertSame(80, DefenseWindow::where('defense_type', self::STAGE)->value('duration_minutes'));
    }

    public function test_the_conference_room_can_be_booked(): void
    {
        $schedule = $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $this->apply([['defense_id' => $schedule->defense_id, 'date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Conference Room']])
            ->assertOk()->assertJsonCount(1, 'data.scheduled');

        $this->assertSame('Conference Room', $schedule->fresh()->venue);
    }

    // ── the board endpoint ───────────────────────────────────────────────

    public function test_board_returns_the_full_state_for_a_stage(): void
    {
        $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]));

        $data = $this->actingAs($this->capsa, 'expert')
            ->getJson(route('api.admin.panel_assignment.board'))
            ->assertOk()
            ->json('data');

        $this->assertSame(self::STAGE, $data['stage']);
        $this->assertSame(DefenseBoard::STAGES, $data['stages']);
        $this->assertCount(1, $data['teams']);
        $this->assertArrayHasKey('experts', $data);
        $this->assertArrayHasKey('names', $data);
        $this->assertArrayHasKey('sections', $data);
        $this->assertArrayHasKey('other', $data);
        $this->assertArrayHasKey('windows', $data);
        $this->assertArrayHasKey('has_rubric', $data);
        $this->assertArrayHasKey('slots', $data);
        $this->assertArrayHasKey('venues', $data);
        $this->assertIsArray($data['teams'][0]['blockers']);
        $this->assertArrayHasKey('defense_id', $data['teams'][0]);
    }

    public function test_board_honors_the_defense_type_query_param(): void
    {
        DefenseWindow::create(['defense_type' => 'Title Defense', 'start_date' => '2027-04-01', 'end_date' => '2027-04-02']);
        $this->panel($this->team('Alpha'), CapstoneExpert::factory()->create(['approved_status' => true]), 'Title Defense');

        $data = $this->actingAs($this->capsa, 'expert')
            ->getJson(route('api.admin.panel_assignment.board', ['defense_type' => 'Title Defense']))
            ->assertOk()
            ->json('data');

        $this->assertSame('Title Defense', $data['stage']);
    }

    public function test_board_is_gated_to_capsa(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);

        $this->actingAs($expert, 'expert')
            ->getJson(route('api.admin.panel_assignment.board'))
            ->assertForbidden();
    }
}
