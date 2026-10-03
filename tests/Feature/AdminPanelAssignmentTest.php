<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\ExpensePost;
use App\Models\ExpertRole;
use App\Models\FinancialRecord;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Services\GoogleCalendarService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $admin;

    private ?ExpensePost $titleDefensePost = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeCapsa(CapstoneExpert::factory()->create([
            'is_admin' => true,
            'approved_status' => true,
        ]));
    }

    // Payment gating (see paidTeamIds()) means every team used by the
    // scheduling-logic tests in this file must be fully paid for "Title
    // Defense" by default - that's the type every test in this file uses.
    // Pass paid: false only for the tests that specifically exercise the
    // payment gate itself.
    private function makeTeam(array $overrides = [], bool $paid = true): Team
    {
        $team = Team::create(array_merge([
            'team_name' => 'Team Alpha',
            'project_title' => 'A Capstone Project',
            'tags' => [],
            'team_status' => 'Active',
            'is_confirmed' => true,
            'member_count' => 1,
        ], $overrides));

        if ($paid) {
            $this->payTeamForTitleDefense($team);
        }

        $this->staffTeam($team);

        return $team;
    }

    /**
     * An approved adviser, consultant and grammarian - a team without all
     * three cannot be given a panel, and these tests are about other rules.
     */
    private function staffTeam(Team $team): void
    {
        foreach (Team::REQUIRED_EXPERT_ROLES as $roleName) {
            $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
            $role = Role::firstOrCreate(['role_name' => $roleName]);
            $expert->roles()->attach($role->role_id);

            TeamExpertAssignment::create([
                'team_id' => $team->team_id,
                'expert_id' => $expert->expert_id,
                'status' => 'approved',
                'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)
                    ->where('role_id', $role->role_id)->value('expert_role_id'),
            ]);
        }
    }

    private function titleDefenseExpensePost(): ExpensePost
    {
        if (! $this->titleDefensePost) {
            $this->titleDefensePost = ExpensePost::create([
                'admin_id' => $this->admin->expert_id,
                'title' => 'Title Defense Fee',
                'course' => 'BSIT',
                'year_level' => '4th',
                'defense_type' => 'Title Defense',
                'amount' => 500,
            ]);
        }

        return $this->titleDefensePost;
    }

    private function payTeamForTitleDefense(Team $team): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create([
            'student_id' => $student->student_id,
            'team_id' => $team->team_id,
        ]);

        $post = $this->titleDefenseExpensePost();

        FinancialRecord::create([
            'student_id' => $student->student_id,
            'expense_post_id' => $post->id,
            'receipt_image' => 'receipts/or-'.$team->team_id.'.jpg',
            'receipt_number' => 'OR-'.$team->team_id,
            'f_record_status' => 'verified',
            'payment_for' => $post->title,
            'amount' => $post->amount,
            'uploaded_at' => now(),
            'verified_at' => now(),
            'verified_by' => $this->admin->name,
        ]);
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

        // Panel is now assigned to all stages at once, so create it for Pre-proposal Defense
        $schedule = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Pre-proposal Defense',
        ]);

        DefensePanel::create([
            'defense_id' => $schedule->defense_id,
            'expert_id' => $chair->expert_id,
            'panel_role' => 'Panel Chair',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'expert')
            ->get(route('capsa.panel_assignment'));

        // The Defenses page draws itself from this state.
        $response->assertStatus(200)
            ->assertSee('Ada Lovelace')
            ->assertViewHas('state', fn ($state) => $state['step'] === 'panel'
                && collect($state['teams'])->firstWhere('id', $team->team_id)['panel']['chair'] === $chair->expert_id);
    }

    public function test_index_shows_assign_label_for_teams_without_a_panel_yet(): void
    {
        $team = $this->makeTeam();

        $response = $this->actingAs($this->admin, 'expert')
            ->get(route('capsa.panel_assignment'));

        $response->assertStatus(200)
            ->assertViewHas('state', fn ($state) => collect($state['teams'])->firstWhere('id', $team->team_id)['panel'] === null);
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

        // Now creates 4 schedule rows (one per stage) with the new panel
        $this->assertSame(4, DefenseSchedule::where('team_id', $team->team_id)->count());

        // Each schedule should have the new panel
        foreach (['Pre-proposal Defense', 'Title Defense', 'Pre Oral Defense', 'Final Oral Defense'] as $stage) {
            $schedule = DefenseSchedule::where('team_id', $team->team_id)
                ->where('defense_type', $stage)
                ->first();
            $this->assertNotNull($schedule);
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
    }

    public function test_store_allows_removing_panel_from_team_with_no_existing_panel(): void
    {
        // Removing a panel from a team that has no panel is a no-op (not an error).
        // This allows idempotent "clear panel" operations.
        $team = $this->makeTeam();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        // No schedules should be created
        $this->assertSame(0, DefenseSchedule::where('team_id', $team->team_id)->count());
    }

    public function test_store_with_no_chair_removes_an_existing_panel(): void
    {
        $chair = $this->makeExpert();
        $team = $this->makeTeam();

        $this->assignPanel($team, 'Title Defense', $chair);
        $schedule = DefenseSchedule::where('team_id', $team->team_id)->where('defense_type', 'Title Defense')->first();
        $this->assertNotNull($schedule);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame([], $response->json('data.panels'));
        $this->assertNull($response->json('data.schedule_date'));

        $this->assertDatabaseMissing('defense_schedule', ['defense_id' => $schedule->defense_id]);
        $this->assertDatabaseMissing('defense_panels', ['defense_id' => $schedule->defense_id]);
    }

    // ── Pre-proposal Defense panel propagation ─────────────────────────────

    public function test_assigning_via_pre_proposal_defense_propagates_the_panel_to_all_other_stages(): void
    {
        $chair = $this->makeExpert();
        $member1 = $this->makeExpert();
        $team = $this->makeTeam();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Pre-proposal Defense',
                'chair_expert_id' => $chair->expert_id,
                'member1_expert_id' => $member1->expert_id,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(3, $response->json('propagated'));

        foreach (['Title Defense', 'Pre Oral Defense', 'Final Oral Defense'] as $stage) {
            $schedule = DefenseSchedule::where('team_id', $team->team_id)->where('defense_type', $stage)->first();
            $this->assertNotNull($schedule, "Expected a {$stage} schedule to be created.");
            $this->assertDatabaseHas('defense_panels', [
                'defense_id' => $schedule->defense_id,
                'expert_id' => $chair->expert_id,
                'panel_role' => 'Panel Chair',
            ]);
        }
    }

    public function test_reassigning_the_pre_proposal_panel_overwrites_a_previously_overridden_stage(): void
    {
        $originalChair = $this->makeExpert();
        $overrideChair = $this->makeExpert();
        $newChair = $this->makeExpert();
        $team = $this->makeTeam();

        $this->assignPanel($team, 'Pre-proposal Defense', $originalChair);

        // A targeted, independent override for just Title Defense.
        $this->assignPanel($team, 'Title Defense', $overrideChair);
        $titleSchedule = DefenseSchedule::where('team_id', $team->team_id)->where('defense_type', 'Title Defense')->first();
        $this->assertDatabaseHas('defense_panels', ['defense_id' => $titleSchedule->defense_id, 'expert_id' => $overrideChair->expert_id]);

        // Re-assigning Pre-proposal Defense's panel propagates again,
        // overwriting the override.
        $this->assignPanel($team, 'Pre-proposal Defense', $newChair);

        $titleSchedule->refresh();
        $this->assertDatabaseHas('defense_panels', ['defense_id' => $titleSchedule->defense_id, 'expert_id' => $newChair->expert_id]);
        $this->assertDatabaseMissing('defense_panels', ['defense_id' => $titleSchedule->defense_id, 'expert_id' => $overrideChair->expert_id]);
    }

    public function test_removing_the_pre_proposal_panel_removes_it_from_all_other_stages_too(): void
    {
        $chair = $this->makeExpert();
        $team = $this->makeTeam();

        $allStages = ['Pre-proposal Defense', 'Title Defense', 'Pre Oral Defense', 'Final Oral Defense'];

        $this->assignPanel($team, 'Pre-proposal Defense', $chair);
        foreach ($allStages as $stage) {
            $this->assertDatabaseHas('defense_schedule', ['team_id' => $team->team_id, 'defense_type' => $stage]);
        }

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Pre-proposal Defense',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        foreach ($allStages as $stage) {
            $this->assertDatabaseMissing('defense_schedule', ['team_id' => $team->team_id, 'defense_type' => $stage]);
        }
    }

    public function test_bulk_store_with_no_chair_removes_panels_from_selected_teams_and_skips_teams_with_none(): void
    {
        $chair = $this->makeExpert();
        $teamWithPanel = $this->makeTeam(['team_name' => 'Has Panel']);
        $teamWithoutPanel = $this->makeTeam(['team_name' => 'No Panel']);

        // Assign panel - this now creates schedules for all 4 stages
        $this->assignPanel($teamWithPanel, 'Title Defense', $chair);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store.bulk'), [
                'team_ids' => [$teamWithPanel->team_id, $teamWithoutPanel->team_id],
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $assigned = collect($response->json('data.assigned'));
        $skipped = collect($response->json('data.skipped'));

        // Both teams are "assigned" (success) - removing from a team with no panel is a no-op, not an error
        $this->assertCount(2, $assigned);
        $this->assertCount(0, $skipped);

        // Verify panel was removed from all stages for teamWithPanel
        foreach (['Pre-proposal Defense', 'Title Defense', 'Pre Oral Defense', 'Final Oral Defense'] as $stage) {
            $this->assertDatabaseMissing('defense_schedule', [
                'team_id' => $teamWithPanel->team_id,
                'defense_type' => $stage,
            ]);
        }
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

        $this->expectException(QueryException::class);
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

        $this->expectException(QueryException::class);
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

    public function test_store_keeps_existing_schedule_when_panel_is_unchanged(): void
    {
        $chair = $this->makeExpert();
        $team = $this->makeTeam();

        $this->assignPanel($team, 'Title Defense', $chair);
        DefenseSchedule::where('team_id', $team->team_id)->update(['schedule_date' => '2026-09-01 08:00:00', 'venue' => 'Mac Lab']);

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
        DefenseSchedule::where('team_id', $team->team_id)->update(['schedule_date' => '2026-09-01 08:00:00', 'venue' => 'Mac Lab']);
        $this->assertNotNull(DefenseSchedule::where('team_id', $team->team_id)->first()->schedule_date);

        $this->assignPanel($team, 'Title Defense', $newChair);

        $this->assertNull(DefenseSchedule::where('team_id', $team->team_id)->first()->schedule_date);
    }

    public function test_store_rejects_the_teams_own_adviser_as_a_panel_member(): void
    {
        $team = $this->makeTeam();
        $adviser = $this->makeExpert();

        $assignment = new TeamExpertAssignment;
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
        $this->assertEquals('12:00', $schedule->end_date->format('H:i'));
        $this->assertEquals('2027-03-15', $schedule->end_date->toDateString());
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

    public function test_override_allows_a_shared_panelist_on_a_different_panel_at_a_different_time(): void
    {
        // A panelist may sit on two DIFFERENT panels on the same day as long
        // as the blocks don't overlap - different chairs, so genuinely two
        // panels sharing one member. A manual change must not be refused.
        $sharedExpert = $this->makeExpert();
        $chairA = $this->makeExpert();
        $chairB = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'DebugTeam']);
        $teamB = $this->makeTeam(['team_name' => 'TinkerTeam']);

        // Team A's panel: chairA + sharedExpert, in Open Lab at 8am.
        $scheduleA = DefenseSchedule::create([
            'team_id' => $teamA->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => '2027-03-15 08:00:00',
            'venue' => 'Open Lab',
        ]);
        DefensePanel::create(['defense_id' => $scheduleA->defense_id, 'expert_id' => $chairA->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $scheduleA->defense_id, 'expert_id' => $sharedExpert->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        // Team B's panel has its own chair - the same day at 10am is a later
        // block, so the shared member is free by then, and stays in Open Lab.
        $scheduleB = DefenseSchedule::create([
            'team_id' => $teamB->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => '2027-03-10 08:00:00',
            'venue' => 'Mac Lab',
        ]);
        DefensePanel::create(['defense_id' => $scheduleB->defense_id, 'expert_id' => $chairB->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $scheduleB->defense_id, 'expert_id' => $sharedExpert->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleB->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Open Lab']
        );

        $response->assertOk()->assertJson(['success' => true]);

        $scheduleB->refresh();
        $this->assertEquals('2027-03-15', $scheduleB->schedule_date->toDateString());
        $this->assertEquals('10:00', $scheduleB->schedule_date->format('H:i'));
        $this->assertEquals('Open Lab', $scheduleB->venue);
    }

    public function test_override_rejects_a_shared_panelist_on_a_different_panel_at_the_same_time(): void
    {
        // Different chairs, so these really are two panels - and the member who
        // sits on both cannot be in two rooms in the same block.
        $shared = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'DebugTeam']);
        $teamB = $this->makeTeam(['team_name' => 'TinkerTeam']);

        $scheduleA = DefenseSchedule::create([
            'team_id' => $teamA->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => '2027-03-15 08:00:00',
            'venue' => 'Open Lab',
        ]);
        DefensePanel::create(['defense_id' => $scheduleA->defense_id, 'expert_id' => $this->makeExpert()->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $scheduleA->defense_id, 'expert_id' => $shared->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        $scheduleB = DefenseSchedule::create([
            'team_id' => $teamB->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => '2027-03-10 08:00:00',
            'venue' => 'Mac Lab',
        ]);
        DefensePanel::create(['defense_id' => $scheduleB->defense_id, 'expert_id' => $this->makeExpert()->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $scheduleB->defense_id, 'expert_id' => $shared->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleB->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '08:00', 'venue' => 'Mac Lab']
        );

        $response->assertStatus(409);
        $response->assertJson(['success' => false, 'requires_confirmation' => true]);
        $conflicts = $response->json('conflicts');
        $this->assertNotEmpty($conflicts);
        $this->assertStringContainsString($shared->name." is already on DebugTeam (Title Defense)'s panel at this time.", $conflicts[0]);
    }

    public function test_override_rejects_the_same_chair_in_two_rooms_on_the_same_day(): void
    {
        // The chair leads the panel, so the same chair on two defenses is ONE
        // panel: it keeps the one room it has that day, even though the seat
        // beside the chair changes from team to team.
        $chair = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'DebugTeam']);
        $teamB = $this->makeTeam(['team_name' => 'TinkerTeam']);

        $scheduleA = DefenseSchedule::create([
            'team_id' => $teamA->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => '2027-03-15 08:00:00',
            'venue' => 'Mac Lab',
        ]);
        DefensePanel::create(['defense_id' => $scheduleA->defense_id, 'expert_id' => $chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $scheduleA->defense_id, 'expert_id' => $this->makeExpert()->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        // A different line-up for team B - the shared chair is what makes it one panel.
        $scheduleB = DefenseSchedule::create([
            'team_id' => $teamB->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => '2027-03-10 08:00:00',
            'venue' => 'Mac Lab',
        ]);
        DefensePanel::create(['defense_id' => $scheduleB->defense_id, 'expert_id' => $chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $scheduleB->defense_id, 'expert_id' => $this->makeExpert()->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleB->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Open Lab']
        );

        $response->assertStatus(409);
        $this->assertStringContainsString(
            $chair->name.' is in Mac Lab that day for DebugTeam (Title Defense), so this panel has to use Mac Lab too.',
            $response->json('conflicts.0')
        );

        $scheduleB->refresh();
        $this->assertEquals('2027-03-10', $scheduleB->schedule_date->toDateString());
    }

    public function test_override_keeps_a_chairs_panel_in_one_room_whatever_the_lineup(): void
    {
        // The same one panel, only this time the second team stays in the room
        // the panel already has - back-to-back, which is what a defense day is.
        $chair = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'DebugTeam']);
        $teamB = $this->makeTeam(['team_name' => 'TinkerTeam']);

        $scheduleA = DefenseSchedule::create([
            'team_id' => $teamA->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => '2027-03-15 08:00:00',
            'venue' => 'Mac Lab',
        ]);
        DefensePanel::create(['defense_id' => $scheduleA->defense_id, 'expert_id' => $chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $scheduleA->defense_id, 'expert_id' => $this->makeExpert()->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        $scheduleB = DefenseSchedule::create([
            'team_id' => $teamB->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => '2027-03-10 08:00:00',
            'venue' => 'Mac Lab',
        ]);
        DefensePanel::create(['defense_id' => $scheduleB->defense_id, 'expert_id' => $chair->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $scheduleB->defense_id, 'expert_id' => $this->makeExpert()->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleB->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Mac Lab']
        );

        $response->assertOk()->assertJson(['success' => true]);

        $scheduleB->refresh();
        $this->assertEquals('10:00', $scheduleB->schedule_date->format('H:i'));
        $this->assertEquals('Mac Lab', $scheduleB->venue);
    }

    public function test_override_keeps_a_panel_in_the_room_it_already_has_that_day(): void
    {
        // One panel, two teams on the same day: the panel sits through both
        // back-to-back in one room rather than moving between blocks.
        $chair = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'Team A']);
        $teamB = $this->makeTeam(['team_name' => 'Team B']);

        $this->scheduleWithPanel($teamB, 'Title Defense', $chair, '2027-03-15', 'Mac Lab', '08:00');
        $scheduleA = $this->scheduleWithPanel($teamA, 'Title Defense', $chair, '2027-03-10', 'Mac Lab');

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleA->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Mac Lab']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('10:00', $scheduleA->fresh()->schedule_date->format('H:i'));
    }

    public function test_override_rejects_moving_a_panel_to_another_room_on_its_defense_day(): void
    {
        // The panel defends out of Mac Lab on 2027-03-15, so its later block
        // cannot be in the Open Lab - the panel would have to move rooms.
        $chair = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'Team A']);
        $teamB = $this->makeTeam(['team_name' => 'Team B']);

        $this->scheduleWithPanel($teamB, 'Title Defense', $chair, '2027-03-15', 'Mac Lab', '08:00');
        $scheduleA = $this->scheduleWithPanel($teamA, 'Title Defense', $chair, '2027-03-10', 'Mac Lab');

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleA->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Open Lab']
        );

        $response->assertStatus(409);
        $response->assertJson(['success' => false, 'requires_confirmation' => true]);
        $this->assertStringContainsString(
            $chair->name.' is in Mac Lab that day for Team B (Title Defense), so this panel has to use Mac Lab too.',
            $response->json('conflicts.0')
        );

        $scheduleA->refresh();
        $this->assertEquals('2027-03-10', $scheduleA->schedule_date->toDateString());
        $this->assertEquals('Mac Lab', $scheduleA->venue);
    }

    public function test_a_different_panel_may_use_another_room_on_the_same_day(): void
    {
        // Only the panel's own lineup is held to one room - another panel in
        // the other room at another block is fine.
        $chairA = $this->makeExpert();
        $chairB = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'Team A']);
        $teamB = $this->makeTeam(['team_name' => 'Team B']);

        $this->scheduleWithPanel($teamB, 'Title Defense', $chairB, '2027-03-15', 'Mac Lab', '08:00');
        $scheduleA = $this->scheduleWithPanel($teamA, 'Title Defense', $chairA, '2027-03-10', 'Mac Lab');

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleA->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Open Lab']
        );

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('Open Lab', $scheduleA->fresh()->venue);
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
        $this->assertStringContainsString($sharedExpert->name." is already on Team B (Title Defense)'s panel at this time.", $conflicts[0]);
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

    // ── Payment gating ──────────────────────────────────────────────────────
    // Payment never blocks panel ASSIGNMENT (store()/storeMultiple()) - it
    // only gates the automatic day/venue SCHEDULING step, and only for the
    // three stages that actually have a fee (see the "Scheduling" tests
    // below for that behavior).

    public function test_store_allows_assigning_a_panel_to_an_unpaid_team(): void
    {
        $team = $this->makeTeam([], paid: false);
        $chair = $this->makeExpert();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $chair->expert_id,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseHas('defense_schedule', ['team_id' => $team->team_id, 'defense_type' => 'Title Defense']);
    }

    public function test_store_allows_a_fully_paid_team(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $chair->expert_id,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_team_row_shows_panel_assigned_to_all_stages(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert(['first_name' => 'PanelChair']);

        // Assign panel - this creates schedules for all 4 stages with the same panel
        $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'chair_expert_id' => $chair->expert_id,
            ])->assertStatus(200);

        // Verify panel was created for all 4 stages
        foreach (['Pre-proposal Defense', 'Title Defense', 'Pre Oral Defense', 'Final Oral Defense'] as $stage) {
            $schedule = DefenseSchedule::where('team_id', $team->team_id)
                ->where('defense_type', $stage)
                ->first();
            $this->assertNotNull($schedule, "Expected a {$stage} schedule to be created.");
            $this->assertDatabaseHas('defense_panels', [
                'defense_id' => $schedule->defense_id,
                'expert_id' => $chair->expert_id,
                'panel_role' => 'Panel Chair',
            ]);
        }

        // The panel assignment page shows Pre-proposal Defense stage data
        $response = $this->actingAs($this->admin, 'expert')
            ->get(route('capsa.panel_assignment'));
        $response->assertOk()->assertSee($chair->name);
    }

    // ── Scheduling payment gate (generateScheduleForWindow) ───────────────

    // ── Bulk multi-team assignment ────────────────────────────────────────

    public function test_bulk_store_assigns_panel_to_multiple_paid_teams(): void
    {
        $teamA = $this->makeTeam(['team_name' => 'Team A']);
        $teamB = $this->makeTeam(['team_name' => 'Team B']);
        $chair = $this->makeExpert();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store.bulk'), [
                'team_ids' => [$teamA->team_id, $teamB->team_id],
                'chair_expert_id' => $chair->expert_id,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(2, $response->json('data.assigned'));
        $this->assertCount(0, $response->json('data.skipped'));

        foreach ([$teamA, $teamB] as $team) {
            // Panel is now assigned to all 4 stages
            foreach (['Pre-proposal Defense', 'Title Defense', 'Pre Oral Defense', 'Final Oral Defense'] as $stage) {
                $schedule = DefenseSchedule::where('team_id', $team->team_id)
                    ->where('defense_type', $stage)
                    ->first();
                $this->assertNotNull($schedule, "Expected a {$stage} schedule for {$team->team_name}.");
                $this->assertDatabaseHas('defense_panels', [
                    'defense_id' => $schedule->defense_id,
                    'expert_id' => $chair->expert_id,
                    'panel_role' => 'Panel Chair',
                ]);
            }
        }
    }

    public function test_bulk_store_assigns_to_paid_and_unpaid_teams_but_skips_the_conflicted_one(): void
    {
        // Payment never blocks assignment (only scheduling does) - an
        // unpaid team in the batch must still get assigned, same as a paid
        // one. Only a genuine adviser/consultant conflict is skipped.
        $paidTeam = $this->makeTeam(['team_name' => 'Paid Team']);
        $unpaidTeam = $this->makeTeam(['team_name' => 'Unpaid Team'], paid: false);
        $conflictedTeam = $this->makeTeam(['team_name' => 'Conflicted Team']);
        $chair = $this->makeExpert();

        $assignment = new TeamExpertAssignment;
        $assignment->team_id = $conflictedTeam->team_id;
        $assignment->expert_id = $chair->expert_id;
        $assignment->status = 'approved';
        $assignment->save();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store.bulk'), [
                'team_ids' => [$paidTeam->team_id, $unpaidTeam->team_id, $conflictedTeam->team_id],
                'chair_expert_id' => $chair->expert_id,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $assigned = collect($response->json('data.assigned'));
        $skipped = collect($response->json('data.skipped'));

        $this->assertCount(2, $assigned);
        $this->assertTrue($assigned->pluck('team_id')->contains($paidTeam->team_id));
        $this->assertTrue($assigned->pluck('team_id')->contains($unpaidTeam->team_id));

        $this->assertCount(1, $skipped);
        $this->assertSame('Conflicted Team', $skipped->first()['team_name']);
    }

    // ── Suggest Teams for a Panel (reverse direction) ─────────────────────

    // ── Google Calendar sync ─────────────────────────────────────────────

    public function test_manual_override_recreates_the_calendar_event_for_an_already_synced_panelist(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert(['google_token' => json_encode(['access_token' => 'fake'])]);
        $schedule = $this->scheduleWithPanel($team, 'Title Defense', $chair, '2027-03-10', 'Mac Lab');

        DefensePanel::where('defense_id', $schedule->defense_id)
            ->where('expert_id', $chair->expert_id)
            ->update(['google_event_id' => 'old-event-id']);

        $this->mock(GoogleCalendarService::class, function ($mock) use ($chair) {
            $mock->shouldReceive('cancelBookingEvent')
                ->once()
                ->with($chair->expert_id, 'old-event-id');
            $mock->shouldReceive('createDefensePanelEvent')
                ->once()
                ->andReturn('new-event-id');
        });

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $schedule->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Open Lab']
        );

        $response->assertOk()->assertJson(['success' => true]);

        $panel = DefensePanel::where('defense_id', $schedule->defense_id)->where('expert_id', $chair->expert_id)->first();
        $this->assertSame('new-event-id', $panel->google_event_id);
    }

    public function test_removing_a_panel_cancels_the_connected_panelists_calendar_event(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert(['google_token' => json_encode(['access_token' => 'fake'])]);
        $schedule = $this->scheduleWithPanel($team, 'Title Defense', $chair, '2027-03-10', 'Mac Lab');

        DefensePanel::where('defense_id', $schedule->defense_id)
            ->where('expert_id', $chair->expert_id)
            ->update(['google_event_id' => 'old-event-id']);

        $this->mock(GoogleCalendarService::class, function ($mock) use ($chair) {
            $mock->shouldReceive('cancelBookingEvent')->once()->with($chair->expert_id, 'old-event-id');
            $mock->shouldNotReceive('createDefensePanelEvent');
        });

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseMissing('defense_schedule', ['defense_id' => $schedule->defense_id]);
    }
}
