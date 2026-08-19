<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\ExpertExpertise;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
        ]);
    }

    private function makeTeam(array $overrides = []): Team
    {
        return Team::create(array_merge([
            'team_name' => 'Team Alpha',
            'project_title' => 'A Capstone Project',
            'tags' => [],
            'team_status' => 'Active',
            'is_confirmed' => true,
            'member_count' => 1,
        ], $overrides));
    }

    private function makeExpert(array $overrides = []): CapstoneExpert
    {
        return CapstoneExpert::factory()->create(array_merge([
            'approved_status' => true,
        ], $overrides));
    }

    public function test_index_shows_real_assignment_data_instead_of_hardcoded_not_assigned(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert(['first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $schedule = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
        ]);

        DefensePanel::create([
            'defense_id' => $schedule->defense_id,
            'expert_id' => $chair->expert_id,
            'panel_role' => 'Panel Chair',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->get(route('admin.panel_assignment'));

        $response->assertStatus(200)
            ->assertSee('Ada Lovelace')
            ->assertSee('Assigned')
            ->assertSee('Update');
    }

    public function test_index_shows_assign_label_for_teams_without_a_panel_yet(): void
    {
        $this->makeTeam();

        $response = $this->actingAs($this->admin, 'expert')
            ->get(route('admin.panel_assignment'));

        $response->assertStatus(200)->assertSee('Assign');
    }

    public function test_store_creates_defense_schedule_and_panels(): void
    {
        // Deliberately no window - assigning a panel must never require
        // one; scheduling only happens later, when the admin saves a
        // window covering everyone already assigned.
        $team = $this->makeTeam();
        $chair = $this->makeExpert();
        $member1 = $this->makeExpert();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $chair->expert_id,
                'member1_expert_id' => $member1->expert_id,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('defense_schedule', [
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => null,
            'venue' => null,
        ]);

        $schedule = DefenseSchedule::where('team_id', $team->team_id)->first();

        $this->assertDatabaseHas('defense_panels', [
            'defense_id' => $schedule->defense_id,
            'expert_id' => $chair->expert_id,
            'panel_role' => 'Panel Chair',
        ]);
        $this->assertDatabaseHas('defense_panels', [
            'defense_id' => $schedule->defense_id,
            'expert_id' => $member1->expert_id,
            'panel_role' => 'Panel Member 1',
        ]);
    }

    public function test_store_replaces_previous_panel_on_resubmit(): void
    {
        $team = $this->makeTeam();
        $originalChair = $this->makeExpert();
        $newChair = $this->makeExpert();

        $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $originalChair->expert_id,
            ])->assertStatus(200);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $newChair->expert_id,
            ]);

        $response->assertStatus(200);

        // Still exactly one schedule row for this team+defense_type (upsert, not a new row).
        $this->assertSame(1, DefenseSchedule::where('team_id', $team->team_id)->count());

        $schedule = DefenseSchedule::where('team_id', $team->team_id)->first();
        $this->assertSame(1, $schedule->panels()->count());
        $this->assertDatabaseHas('defense_panels', [
            'defense_id' => $schedule->defense_id,
            'expert_id' => $newChair->expert_id,
        ]);
        $this->assertDatabaseMissing('defense_panels', [
            'defense_id' => $schedule->defense_id,
            'expert_id' => $originalChair->expert_id,
        ]);
    }

    public function test_store_requires_a_panel_chair(): void
    {
        $team = $this->makeTeam();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['chair_expert_id']);
    }

    public function test_store_rejects_the_same_expert_in_two_roles(): void
    {
        $team = $this->makeTeam();
        $expert = $this->makeExpert();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $expert->expert_id,
                'member1_expert_id' => $expert->expert_id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['member1_expert_id']);
    }

    public function test_defense_schedule_unique_constraint_blocks_duplicate_team_and_type(): void
    {
        $team = $this->makeTeam();

        DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => 'Title Defense']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => 'Title Defense']);
    }

    public function test_defense_panels_unique_constraint_blocks_double_booking_same_expert(): void
    {
        $team = $this->makeTeam();
        $expert = $this->makeExpert();
        $schedule = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => 'Title Defense']);

        DefensePanel::create([
            'defense_id' => $schedule->defense_id,
            'expert_id' => $expert->expert_id,
            'panel_role' => 'Panel Chair',
            'created_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DefensePanel::create([
            'defense_id' => $schedule->defense_id,
            'expert_id' => $expert->expert_id,
            'panel_role' => 'Panel Member 1',
            'created_at' => now(),
        ]);
    }

    private function assignPanel(Team $team, string $defenseType, CapstoneExpert $chair, ?CapstoneExpert $member1 = null): void
    {
        $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), array_filter([
                'defense_type' => $defenseType,
                'chair_expert_id' => $chair->expert_id,
                'member1_expert_id' => $member1?->expert_id,
            ]))->assertStatus(200);
    }

    private function saveWindow(string $defenseType, string $start, string $end)
    {
        return $this->actingAs($this->admin, 'expert')
            ->putJson(route('api.admin.panel_assignment.windows.update', $defenseType), [
                'start_date' => $start,
                'end_date' => $end,
            ]);
    }

    public function test_saving_a_window_schedules_teams_already_assigned_a_panel(): void
    {
        $chair = $this->makeExpert();
        $member1 = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'Team A']);
        $teamB = $this->makeTeam(['team_name' => 'Team B']);

        // Assigned before any window exists - must succeed, stays unscheduled.
        $this->assignPanel($teamA, 'Title Defense', $chair, $member1);
        $this->assignPanel($teamB, 'Title Defense', $chair, $member1);

        $this->assertDatabaseHas('defense_schedule', ['team_id' => $teamA->team_id, 'schedule_date' => null]);
        $this->assertDatabaseHas('defense_schedule', ['team_id' => $teamB->team_id, 'schedule_date' => null]);

        $response = $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05');
        $response->assertStatus(200)->assertJson(['success' => true, 'scheduled_count' => 2]);

        $scheduleA = DefenseSchedule::where('team_id', $teamA->team_id)->first();
        $scheduleB = DefenseSchedule::where('team_id', $teamB->team_id)->first();

        $this->assertNotNull($scheduleA->schedule_date);
        $this->assertNotNull($scheduleB->schedule_date);
        // Same panel -> same day and venue, not two different bookings.
        $this->assertTrue($scheduleA->schedule_date->isSameDay($scheduleB->schedule_date));
        $this->assertSame($scheduleA->venue, $scheduleB->venue);
    }

    public function test_generated_schedule_avoids_double_booking_venue_and_experts_on_the_same_day(): void
    {
        $panelA = [$this->makeExpert(), $this->makeExpert()];
        $panelB = [$this->makeExpert(), $this->makeExpert()];
        $teamA = $this->makeTeam(['team_name' => 'Team A']);
        $teamB = $this->makeTeam(['team_name' => 'Team B']);

        $this->assignPanel($teamA, 'Title Defense', $panelA[0], $panelA[1]);
        $this->assignPanel($teamB, 'Title Defense', $panelB[0], $panelB[1]);

        // Single-day window: both land on it, but two unrelated panels can't share a venue.
        $response = $this->saveWindow('Title Defense', '2026-09-01', '2026-09-01');
        $response->assertStatus(200)->assertJson(['scheduled_count' => 2]);

        $scheduleA = DefenseSchedule::where('team_id', $teamA->team_id)->first();
        $scheduleB = DefenseSchedule::where('team_id', $teamB->team_id)->first();

        $this->assertTrue($scheduleA->schedule_date->isSameDay($scheduleB->schedule_date));
        $this->assertNotSame($scheduleA->venue, $scheduleB->venue);
    }

    public function test_store_succeeds_with_no_window_configured(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $chair->expert_id,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('defense_schedule', ['team_id' => $team->team_id, 'schedule_date' => null]);
    }

    public function test_window_generation_leaves_unschedulable_panels_unscheduled_instead_of_failing(): void
    {
        // 1 day x 4 time slots x 2 venues = 8 slots total for 9 unrelated panels.
        for ($i = 0; $i < 9; $i++) {
            $team = $this->makeTeam(['team_name' => "Team $i"]);
            $this->assignPanel($team, 'Title Defense', $this->makeExpert(), $this->makeExpert());
        }

        $response = $this->saveWindow('Title Defense', '2026-09-01', '2026-09-01');

        $response->assertStatus(200)->assertJson(['success' => true, 'scheduled_count' => 8]);
        $this->assertSame(1, DefenseSchedule::where('defense_type', 'Title Defense')->whereNull('schedule_date')->count());
    }

    public function test_store_keeps_existing_schedule_when_panel_is_unchanged(): void
    {
        $chair = $this->makeExpert();
        $team = $this->makeTeam();

        $this->assignPanel($team, 'Title Defense', $chair);
        $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05');

        $scheduledDate = DefenseSchedule::where('team_id', $team->team_id)->first()->schedule_date;
        $this->assertNotNull($scheduledDate);

        // Resubmitting the exact same panel must not wipe the schedule.
        $this->assignPanel($team, 'Title Defense', $chair);

        $schedule = DefenseSchedule::where('team_id', $team->team_id)->first();
        $this->assertNotNull($schedule->schedule_date);
        $this->assertTrue($schedule->schedule_date->isSameDay($scheduledDate));
    }

    public function test_store_clears_schedule_when_panel_changes_after_being_scheduled(): void
    {
        $originalChair = $this->makeExpert();
        $newChair = $this->makeExpert();
        $team = $this->makeTeam();

        $this->assignPanel($team, 'Title Defense', $originalChair);
        $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05');
        $this->assertNotNull(DefenseSchedule::where('team_id', $team->team_id)->first()->schedule_date);

        $this->assignPanel($team, 'Title Defense', $newChair);

        $this->assertNull(DefenseSchedule::where('team_id', $team->team_id)->first()->schedule_date);
    }

    public function test_store_rejects_the_teams_own_adviser_as_a_panel_member(): void
    {
        $team = $this->makeTeam();
        $adviser = $this->makeExpert();

        $assignment = new TeamExpertAssignment();
        $assignment->team_id = $team->team_id;
        $assignment->expert_id = $adviser->expert_id;
        $assignment->status = 'approved';
        $assignment->save();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $adviser->expert_id,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('adviser or consultant', $response->json('message'));
    }

    public function test_suggest_experts_excludes_the_teams_own_adviser(): void
    {
        $team = $this->makeTeam(['tags' => ['AI']]);
        $adviser = $this->makeExpert();
        ExpertExpertise::create(['expert_id' => $adviser->expert_id, 'expertise_field' => 'Artificial Intelligence']);

        $assignment = new TeamExpertAssignment();
        $assignment->team_id = $team->team_id;
        $assignment->expert_id = $adviser->expert_id;
        $assignment->status = 'approved';
        $assignment->save();

        $response = $this->actingAs($this->admin, 'expert')
            ->getJson(route('api.admin.panel_assignment.suggest', $team->team_id));

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('expert_id');
        $this->assertFalse($ids->contains($adviser->expert_id));
    }

    public function test_suggest_experts_matches_team_tags_against_expertise(): void
    {
        $team = $this->makeTeam(['tags' => ['AI']]);

        $aiExpert = $this->makeExpert(['first_name' => 'Turing']);
        ExpertExpertise::create(['expert_id' => $aiExpert->expert_id, 'expertise_field' => 'Artificial Intelligence']);

        $webExpert = $this->makeExpert(['first_name' => 'Berners-Lee']);
        ExpertExpertise::create(['expert_id' => $webExpert->expert_id, 'expertise_field' => 'Web Development']);

        $response = $this->actingAs($this->admin, 'expert')
            ->getJson(route('api.admin.panel_assignment.suggest', $team->team_id));

        $response->assertStatus(200)->assertJson(['success' => true]);

        $ids = collect($response->json('data'))->pluck('expert_id');
        $this->assertTrue($ids->contains($aiExpert->expert_id));
        $this->assertFalse($ids->contains($webExpert->expert_id));
    }

    public function test_suggest_experts_returns_empty_when_no_tags_match(): void
    {
        $team = $this->makeTeam(['tags' => ['Cybersecurity']]);

        $webExpert = $this->makeExpert();
        ExpertExpertise::create(['expert_id' => $webExpert->expert_id, 'expertise_field' => 'Web Development']);

        $response = $this->actingAs($this->admin, 'expert')
            ->getJson(route('api.admin.panel_assignment.suggest', $team->team_id));

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => []]);
    }

    private function scheduleWithPanel(Team $team, string $defenseType, CapstoneExpert $chair, string $date, string $venue, string $time = '08:00'): DefenseSchedule
    {
        $schedule = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => $defenseType,
            'schedule_date' => "{$date} {$time}:00",
            'venue' => $venue,
        ]);

        DefensePanel::create([
            'defense_id' => $schedule->defense_id,
            'expert_id' => $chair->expert_id,
            'panel_role' => 'Panel Chair',
            'created_at' => now(),
        ]);

        return $schedule;
    }

    public function test_admin_can_manually_override_schedule_without_conflict(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert();
        $schedule = $this->scheduleWithPanel($team, 'Title Defense', $chair, '2027-03-10', 'Mac Lab');

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $schedule->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Open Lab']
        );

        $response->assertOk()->assertJson(['success' => true]);

        $schedule->refresh();
        $this->assertEquals('2027-03-15', $schedule->schedule_date->toDateString());
        $this->assertEquals('10:00', $schedule->schedule_date->format('H:i'));
        $this->assertEquals('Open Lab', $schedule->venue);
    }

    public function test_override_rejects_start_time_outside_the_fixed_slots(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert();
        $schedule = $this->scheduleWithPanel($team, 'Title Defense', $chair, '2027-03-10', 'Mac Lab');

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $schedule->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '09:00', 'venue' => 'Open Lab']
        );

        $response->assertStatus(422);
    }

    public function test_override_with_venue_conflict_requires_confirmation_and_does_not_save(): void
    {
        $chairA = $this->makeExpert();
        $chairB = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'Team A']);
        $teamB = $this->makeTeam(['team_name' => 'Team B']);

        // Team B already booked into Mac Lab on 2027-03-15 at 8am.
        $this->scheduleWithPanel($teamB, 'Title Defense', $chairB, '2027-03-15', 'Mac Lab', '08:00');
        $scheduleA = $this->scheduleWithPanel($teamA, 'Title Defense', $chairA, '2027-03-10', 'Open Lab');

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleA->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Mac Lab']
        );

        $response->assertStatus(409);
        $response->assertJson(['success' => false, 'requires_confirmation' => true]);
        $this->assertNotEmpty($response->json('conflicts'));

        $scheduleA->refresh();
        $this->assertEquals('2027-03-10', $scheduleA->schedule_date->toDateString());
        $this->assertEquals('Open Lab', $scheduleA->venue);
    }

    public function test_override_at_a_different_time_the_same_day_does_not_conflict(): void
    {
        $chairA = $this->makeExpert();
        $chairB = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'Team A']);
        $teamB = $this->makeTeam(['team_name' => 'Team B']);

        // Team B booked into Mac Lab on 2027-03-15 at 8am.
        $this->scheduleWithPanel($teamB, 'Title Defense', $chairB, '2027-03-15', 'Mac Lab', '08:00');
        $scheduleA = $this->scheduleWithPanel($teamA, 'Title Defense', $chairA, '2027-03-10', 'Open Lab');

        // Same day, same venue, but a different 2-hour block - not a conflict.
        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleA->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Mac Lab']
        );

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_override_with_venue_conflict_confirmed_saves_anyway(): void
    {
        $chairA = $this->makeExpert();
        $chairB = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'Team A']);
        $teamB = $this->makeTeam(['team_name' => 'Team B']);

        $this->scheduleWithPanel($teamB, 'Title Defense', $chairB, '2027-03-15', 'Mac Lab', '08:00');
        $scheduleA = $this->scheduleWithPanel($teamA, 'Title Defense', $chairA, '2027-03-10', 'Open Lab');

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleA->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Mac Lab', 'confirm' => true]
        );

        $response->assertOk()->assertJson(['success' => true]);

        $scheduleA->refresh();
        $this->assertEquals('2027-03-15', $scheduleA->schedule_date->toDateString());
        $this->assertEquals('Mac Lab', $scheduleA->venue);
    }

    public function test_override_with_double_booked_panelist_requires_confirmation(): void
    {
        $sharedExpert = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'Team A']);
        $teamB = $this->makeTeam(['team_name' => 'Team B']);

        // Same expert already chairing Team B's panel on 2027-03-15 at 8am.
        $this->scheduleWithPanel($teamB, 'Title Defense', $sharedExpert, '2027-03-15', 'Mac Lab', '08:00');
        $scheduleA = $this->scheduleWithPanel($teamA, 'Title Defense', $sharedExpert, '2027-03-10', 'Open Lab');

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleA->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Open Lab']
        );

        $response->assertStatus(409);
        $response->assertJson(['success' => false, 'requires_confirmation' => true]);
        $conflicts = $response->json('conflicts');
        $this->assertNotEmpty($conflicts);
        $this->assertStringContainsString($sharedExpert->name, $conflicts[0]);
    }

    public function test_override_rejects_invalid_venue(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert();
        $schedule = $this->scheduleWithPanel($team, 'Title Defense', $chair, '2027-03-10', 'Mac Lab');

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $schedule->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Broom Closet']
        );

        $response->assertStatus(422);
    }

    public function test_index_renders_override_edit_button_for_scheduled_team(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert();
        $schedule = $this->scheduleWithPanel($team, 'Title Defense', $chair, '2027-03-10', 'Mac Lab');

        $response = $this->actingAs($this->admin, 'expert')
            ->get(route('admin.panel_assignment'));

        $response->assertOk();
        $response->assertSee("openScheduleModal({$team->team_id}, {$schedule->defense_id}, '2027-03-10', '08:00', 'Mac Lab')", false);
    }

    public function test_index_omits_override_button_for_unscheduled_team(): void
    {
        $team = $this->makeTeam();

        $response = $this->actingAs($this->admin, 'expert')
            ->get(route('admin.panel_assignment'));

        $response->assertOk();
        // "cell-schedule-edit" and "openScheduleModal(" alone both always
        // appear (the client-side re-render function's own JS source
        // contains that exact markup as a template literal using
        // ${teamId}, not a literal number) - the tell that Blade actually
        // rendered a per-row button is the real numeric team_id baked in.
        $response->assertDontSee("openScheduleModal({$team->team_id}, ", false);
    }

    public function test_non_admin_expert_cannot_override_schedule(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert();
        $schedule = $this->scheduleWithPanel($team, 'Title Defense', $chair, '2027-03-10', 'Mac Lab');

        $nonAdmin = $this->makeExpert(['is_admin' => false]);

        $response = $this->actingAs($nonAdmin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $schedule->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Open Lab']
        );

        $response->assertStatus(403);

        $schedule->refresh();
        $this->assertEquals('2027-03-10', $schedule->schedule_date->toDateString());
    }
}
