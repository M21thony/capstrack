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
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        foreach ($staffed ? Team::REQUIRED_EXPERT_ROLES : [] as $roleName) {
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
        $this->panel($this->team('No Grammarian', staffed: false), CapstoneExpert::factory()->create(['approved_status' => true]));

        $notReady = collect($this->draft()->assertOk()->json('data.not_ready'))->pluck('reason', 'team_name');

        $this->assertSame('No panel assigned yet.', $notReady['No Panel']);
        $this->assertStringContainsString('Needs an adviser, a consultant and a grammarian', $notReady['No Grammarian']);
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
        $this->apply([['defense_id' => $schedule->defense_id, 'date' => '2027-03-16', 'start_time' => '14:00', 'venue' => 'Open Lab']])
            ->assertOk()
            ->assertJsonCount(1, 'data.scheduled');

        $schedule->refresh();
        $this->assertSame('2027-03-16 14:00', $schedule->schedule_date->format('Y-m-d H:i'));
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
        $this->assertStringContainsString('already booked', $response->json('data.skipped.0.reason'));
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
}
