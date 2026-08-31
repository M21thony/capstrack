<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\ExpensePost;
use App\Models\ExpertExpertise;
use App\Models\FinancialRecord;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
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

        return $team;
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

    public function test_store_requires_a_panel_chair_for_a_team_with_no_existing_panel(): void
    {
        // chair_expert_id is nullable at the validation layer (an empty
        // chair on an EXISTING panel means "remove it" - see
        // removePanelFromTeam()) - but a team with nothing to remove still
        // can't be "saved" empty, so this is checked in assignPanelToTeam()
        // itself rather than by a validation rule.
        $team = $this->makeTeam();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store', $team->team_id), [
                'defense_type' => 'Title Defense',
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Panel Chair is required', $response->json('message'));
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

    public function test_updating_a_single_stage_directly_does_not_propagate_anywhere(): void
    {
        $chair = $this->makeExpert();
        $team = $this->makeTeam();

        $this->assignPanel($team, 'Title Defense', $chair);

        foreach (['Pre-proposal Defense', 'Pre Oral Defense', 'Final Oral Defense'] as $stage) {
            $this->assertDatabaseMissing('defense_schedule', ['team_id' => $team->team_id, 'defense_type' => $stage]);
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

        $this->assignPanel($teamWithPanel, 'Title Defense', $chair);

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store.bulk'), [
                'team_ids' => [$teamWithPanel->team_id, $teamWithoutPanel->team_id],
                'defense_type' => 'Title Defense',
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $assigned = collect($response->json('data.assigned'));
        $skipped = collect($response->json('data.skipped'));

        $this->assertCount(1, $assigned);
        $this->assertSame($teamWithPanel->team_id, $assigned->first()['team_id']);

        $this->assertCount(1, $skipped);
        $this->assertSame('No Panel', $skipped->first()['team_name']);

        $this->assertDatabaseMissing('defense_schedule', [
            'team_id' => $teamWithPanel->team_id,
            'defense_type' => 'Title Defense',
        ]);
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
        // Same panel -> same day and venue, but NOT the same block - the
        // panel can't be in two rooms at once, and it can't judge two teams
        // in the exact same 2-hour slot either. They must land back-to-back.
        $this->assertTrue($scheduleA->schedule_date->isSameDay($scheduleB->schedule_date));
        $this->assertSame($scheduleA->venue, $scheduleB->venue);
        $this->assertFalse(
            $scheduleA->schedule_date->equalTo($scheduleB->schedule_date),
            'Two teams sharing a panel must not be scheduled into the identical time slot.'
        );

        // Auto-scheduling persists a real end_date, 2 hours after each
        // team's own start - team B's block starts exactly when team A's ends.
        $this->assertNotNull($scheduleA->end_date);
        $this->assertNotNull($scheduleB->end_date);
        $this->assertEquals($scheduleA->schedule_date->copy()->addHours(2), $scheduleA->end_date);
        $this->assertEquals($scheduleB->schedule_date->copy()->addHours(2), $scheduleB->end_date);
        $this->assertTrue($scheduleA->end_date->equalTo($scheduleB->schedule_date));
    }

    public function test_three_teams_sharing_a_panel_get_three_different_time_blocks_same_day_same_venue(): void
    {
        $chair = $this->makeExpert();
        $member1 = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'Team Alpha']);
        $teamB = $this->makeTeam(['team_name' => 'Team Beta']);
        $teamC = $this->makeTeam(['team_name' => 'Team Gamma']);

        $this->assignPanel($teamA, 'Title Defense', $chair, $member1);
        $this->assignPanel($teamB, 'Title Defense', $chair, $member1);
        $this->assignPanel($teamC, 'Title Defense', $chair, $member1);

        $response = $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05');
        $response->assertStatus(200)->assertJson(['success' => true, 'scheduled_count' => 3]);

        $schedules = DefenseSchedule::whereIn('team_id', [$teamA->team_id, $teamB->team_id, $teamC->team_id])
            ->get()
            ->sortBy('schedule_date')
            ->values();

        $this->assertCount(3, $schedules);

        // Same day, same venue, for all three - this is the panel's one
        // room for the whole day.
        $day = $schedules[0]->schedule_date->toDateString();
        $venue = $schedules[0]->venue;
        foreach ($schedules as $schedule) {
            $this->assertSame($day, $schedule->schedule_date->toDateString());
            $this->assertSame($venue, $schedule->venue);
        }

        // Three genuinely different, back-to-back blocks - not the panel
        // somehow judging all three teams at once.
        $this->assertNotSame($schedules[0]->schedule_date->format('H:i'), $schedules[1]->schedule_date->format('H:i'));
        $this->assertNotSame($schedules[1]->schedule_date->format('H:i'), $schedules[2]->schedule_date->format('H:i'));
        $this->assertTrue($schedules[0]->end_date->equalTo($schedules[1]->schedule_date));
        $this->assertTrue($schedules[1]->end_date->equalTo($schedules[2]->schedule_date));
    }

    public function test_resaving_the_window_fixes_teams_already_double_booked_into_the_identical_slot(): void
    {
        // Simulates real rows left over from before findSlotForPanel() was
        // fixed (or any other way three teams ended up sharing one panel's
        // exact block) - re-saving the window must be usable as a "fix
        // conflicts" action without the admin having to manually touch the
        // database.
        $chair = $this->makeExpert();
        $member1 = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'Team Alpha']);
        $teamB = $this->makeTeam(['team_name' => 'Team Beta']);
        $teamC = $this->makeTeam(['team_name' => 'Team Gamma']);

        foreach ([$teamA, $teamB, $teamC] as $team) {
            $this->assignPanel($team, 'Title Defense', $chair, $member1);
        }

        DefenseSchedule::whereIn('team_id', [$teamA->team_id, $teamB->team_id, $teamC->team_id])
            ->update([
                'schedule_date' => '2026-09-01 08:00:00',
                'end_date' => '2026-09-01 10:00:00',
                'venue' => 'Mac Lab',
            ]);

        $response = $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05');
        $response->assertStatus(200)->assertJson(['success' => true, 'scheduled_count' => 0, 'resolved_count' => 2]);

        $schedules = DefenseSchedule::whereIn('team_id', [$teamA->team_id, $teamB->team_id, $teamC->team_id])
            ->get()
            ->sortBy('schedule_date')
            ->values();

        $day = $schedules[0]->schedule_date->toDateString();
        $venue = $schedules[0]->venue;
        $times = $schedules->pluck('schedule_date')->map(fn ($d) => $d->format('H:i'))->values();

        foreach ($schedules as $schedule) {
            $this->assertSame($day, $schedule->schedule_date->toDateString());
            $this->assertSame($venue, $schedule->venue);
        }
        $this->assertSame($times->unique()->count(), 3, 'All three teams must end up in genuinely different blocks.');
    }

    public function test_resaving_the_window_closes_a_gap_left_when_a_teammate_panel_leaves(): void
    {
        // Reproduces the reported bug exactly: three teams share a panel
        // across 8-10/10-12/12-2. The middle team's panel is then changed
        // to someone else entirely (which only clears that ONE team's own
        // schedule - see store()), leaving the original panel with a hole
        // at 10-12 that its two remaining teams should close up, not leave
        // sitting at 8-10 and 12-2 with a gap between them.
        $chair = $this->makeExpert();
        $member1 = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'Bulbasaurs']);
        $teamB = $this->makeTeam(['team_name' => 'DebugTeam']);
        $teamC = $this->makeTeam(['team_name' => 'TinkerTeam']);

        foreach ([$teamA, $teamB, $teamC] as $team) {
            $this->assignPanel($team, 'Title Defense', $chair, $member1);
        }

        $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05')
            ->assertJson(['success' => true, 'scheduled_count' => 3]);

        $scheduleA = DefenseSchedule::where('team_id', $teamA->team_id)->first();
        $scheduleB = DefenseSchedule::where('team_id', $teamB->team_id)->first();
        $scheduleC = DefenseSchedule::where('team_id', $teamC->team_id)->first();

        $this->assertSame('08:00', $scheduleA->schedule_date->format('H:i'));
        $this->assertSame('10:00', $scheduleB->schedule_date->format('H:i'));
        $this->assertSame('12:00', $scheduleC->schedule_date->format('H:i'));

        // DebugTeam's panel changes to two completely different experts -
        // its own schedule clears; Bulbasaurs and TinkerTeam are untouched.
        $newChair = $this->makeExpert();
        $newMember = $this->makeExpert();
        $this->assignPanel($teamB, 'Title Defense', $newChair, $newMember);

        $this->assertDatabaseHas('defense_schedule', ['team_id' => $teamB->team_id, 'schedule_date' => null]);
        $scheduleC->refresh();
        $this->assertSame('12:00', $scheduleC->schedule_date->format('H:i'), 'TinkerTeam must not move on its own.');

        $response = $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05');
        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertGreaterThan(0, $response->json('compacted_count'));

        $scheduleA->refresh();
        $scheduleC->refresh();

        // Bulbasaurs stays put; TinkerTeam pulls forward into the block
        // DebugTeam vacated, still on the same day and venue as Bulbasaurs.
        $this->assertSame('08:00', $scheduleA->schedule_date->format('H:i'));
        $this->assertSame('10:00', $scheduleC->schedule_date->format('H:i'));
        $this->assertTrue($scheduleA->schedule_date->isSameDay($scheduleC->schedule_date));
        $this->assertSame($scheduleA->venue, $scheduleC->venue);
    }

    public function test_auto_scheduling_pushes_a_shared_panelist_between_different_panels_to_a_different_day(): void
    {
        // Reported bug, reproduced through the automatic scheduler
        // specifically (not just the manual override): a shared expert
        // between two DIFFERENT panels landed on the same day in different
        // venues/blocks with no conflict detected at all.
        $bautista = $this->makeExpert();
        $delaCruz = $this->makeExpert();
        $mendoza = $this->makeExpert();
        $anaReyes = $this->makeExpert();
        $admin = $this->makeExpert();
        $grom = $this->makeExpert();

        $debugTeam = $this->makeTeam(['team_name' => 'DebugTeam']);
        $tinkerTeam = $this->makeTeam(['team_name' => 'TinkerTeam']);

        // DebugTeam's panel: Ana Reyes / Admin / Grom.
        $this->assignPanel($debugTeam, 'Title Defense', $anaReyes, $admin);
        DefensePanel::create(['defense_id' => DefenseSchedule::where('team_id', $debugTeam->team_id)->value('defense_id'), 'expert_id' => $grom->expert_id, 'panel_role' => 'Panel Member 2', 'created_at' => now()]);

        // TinkerTeam's panel: Bautista / Grom / Mendoza - a DIFFERENT
        // lineup that still shares Grom with DebugTeam's panel above.
        $this->assignPanel($tinkerTeam, 'Title Defense', $bautista, $grom);
        DefensePanel::create(['defense_id' => DefenseSchedule::where('team_id', $tinkerTeam->team_id)->value('defense_id'), 'expert_id' => $mendoza->expert_id, 'panel_role' => 'Panel Member 2', 'created_at' => now()]);

        // Window is wide enough to have somewhere else to go, but narrow
        // enough that "same day" really would be the only option if the
        // conflict weren't caught.
        $response = $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05');
        $response->assertStatus(200)->assertJson(['success' => true, 'scheduled_count' => 2]);

        $debugSchedule = DefenseSchedule::where('team_id', $debugTeam->team_id)->first();
        $tinkerSchedule = DefenseSchedule::where('team_id', $tinkerTeam->team_id)->first();

        $this->assertNotNull($debugSchedule->schedule_date);
        $this->assertNotNull($tinkerSchedule->schedule_date);
        $this->assertFalse(
            $debugSchedule->schedule_date->isSameDay($tinkerSchedule->schedule_date),
            'Two different panels sharing Grom must never land on the same day, even in different blocks/venues.'
        );
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

    public function test_saving_a_new_window_range_reschedules_a_team_whose_existing_date_falls_outside_it(): void
    {
        $chair = $this->makeExpert();
        $team = $this->makeTeam();

        $this->assignPanel($team, 'Title Defense', $chair);
        $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05')
            ->assertJson(['success' => true, 'scheduled_count' => 1]);

        $firstDate = DefenseSchedule::where('team_id', $team->team_id)->first()->schedule_date;
        $this->assertTrue($firstDate->isBetween('2026-09-01', '2026-09-05 23:59:59'));

        // A completely different, non-overlapping window - the team's
        // existing date now falls outside it and must be moved in, not
        // left stranded at the old (now out-of-range) date.
        $response = $this->saveWindow('Title Defense', '2026-10-01', '2026-10-05');
        $response->assertStatus(200)->assertJson(['success' => true, 'scheduled_count' => 1]);

        $newDate = DefenseSchedule::where('team_id', $team->team_id)->first()->schedule_date;
        $this->assertTrue($newDate->isBetween('2026-10-01', '2026-10-05 23:59:59'));
    }

    public function test_saving_a_wider_window_leaves_an_already_in_range_schedule_untouched(): void
    {
        $chair = $this->makeExpert();
        $team = $this->makeTeam();

        $this->assignPanel($team, 'Title Defense', $chair);
        $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05');

        $firstDate = DefenseSchedule::where('team_id', $team->team_id)->first()->schedule_date;

        // Re-saving a WIDER window that still contains the existing date
        // must not needlessly reshuffle a team that's still validly placed.
        $response = $this->saveWindow('Title Defense', '2026-09-01', '2026-09-10');
        $response->assertStatus(200)->assertJson(['success' => true, 'scheduled_count' => 0]);

        $secondDate = DefenseSchedule::where('team_id', $team->team_id)->first()->schedule_date;
        $this->assertTrue($firstDate->equalTo($secondDate));
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

    public function test_suggest_experts_excludes_the_teams_own_adviser(): void
    {
        $team = $this->makeTeam(['tags' => ['AI']]);
        $adviser = $this->makeExpert();
        ExpertExpertise::create(['expert_id' => $adviser->expert_id, 'expertise_field' => 'Artificial Intelligence']);

        $assignment = new TeamExpertAssignment;
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

    public function test_override_rejects_a_shared_panelist_on_a_different_panel_even_at_a_different_time_and_venue(): void
    {
        // Reported bug: an expert sitting on two DIFFERENT panels (only
        // partially overlapping membership) on the same day, in different
        // venues and different blocks, was allowed through with no
        // conflict at all. Two different panels can never share a day once
        // they share even one expert - unlike two teams on the exact SAME
        // panel, which correctly CAN share a day across different blocks.
        $sharedExpert = $this->makeExpert();
        $memberX = $this->makeExpert();
        $memberY = $this->makeExpert();
        $teamA = $this->makeTeam(['team_name' => 'DebugTeam']);
        $teamB = $this->makeTeam(['team_name' => 'TinkerTeam']);

        // Team A's panel: sharedExpert + memberX, in Open Lab at 8am.
        $scheduleA = DefenseSchedule::create([
            'team_id' => $teamA->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => '2027-03-15 08:00:00',
            'venue' => 'Open Lab',
        ]);
        DefensePanel::create(['defense_id' => $scheduleA->defense_id, 'expert_id' => $sharedExpert->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $scheduleA->defense_id, 'expert_id' => $memberX->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        // Team B's panel is a DIFFERENT lineup (sharedExpert + memberY, not
        // memberX) - trying to place them at 10am in Mac Lab, a completely
        // different time and room, must still be flagged.
        $scheduleB = DefenseSchedule::create([
            'team_id' => $teamB->team_id,
            'defense_type' => 'Title Defense',
            'schedule_date' => '2027-03-10 08:00:00',
            'venue' => 'Mac Lab',
        ]);
        DefensePanel::create(['defense_id' => $scheduleB->defense_id, 'expert_id' => $sharedExpert->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);
        DefensePanel::create(['defense_id' => $scheduleB->defense_id, 'expert_id' => $memberY->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);

        $response = $this->actingAs($this->admin, 'expert')->putJson(
            route('api.admin.panel_assignment.schedule.update', $scheduleB->defense_id),
            ['schedule_date' => '2027-03-15', 'start_time' => '10:00', 'venue' => 'Mac Lab']
        );

        $response->assertStatus(409);
        $response->assertJson(['success' => false, 'requires_confirmation' => true]);
        $conflicts = $response->json('conflicts');
        $this->assertNotEmpty($conflicts);
        $this->assertStringContainsString($sharedExpert->name, $conflicts[0]);
        $this->assertStringContainsString('different panel', $conflicts[0]);
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

    public function test_index_splits_teams_into_paid_and_unpaid_sections(): void
    {
        $this->makeTeam(['team_name' => 'Paid Squad']);
        $this->makeTeam(['team_name' => 'Unpaid Squad'], paid: false);

        $response = $this->actingAs($this->admin, 'expert')
            ->get(route('admin.panel_assignment', ['defense_type' => 'Title Defense']));

        $response->assertOk();
        $content = $response->getContent();

        $paidPos = strpos($content, 'Paid Squad');
        $unpaidPanelPos = strpos($content, 'id="unpaid-panel"');
        $unpaidPos = strpos($content, 'Unpaid Squad');

        $this->assertNotFalse($paidPos, 'Paid team should be rendered.');
        $this->assertNotFalse($unpaidPos, 'Unpaid team should be rendered.');
        $this->assertLessThan($unpaidPanelPos, $paidPos, 'Paid team must render inside the Paid tab panel, before the Unpaid panel starts.');
        $this->assertGreaterThan($unpaidPanelPos, $unpaidPos, 'Unpaid team must render inside the Unpaid tab panel.');
    }

    public function test_pre_proposal_defense_shows_every_confirmed_team_with_no_paid_unpaid_split(): void
    {
        // Pre-proposal Defense has no fee - it's a valid, selectable stage
        // (unlike an outright-invalid string, which still falls back), but
        // shows one unified list instead of the Paid/Unpaid tabs the three
        // fee-bearing stages use.
        $this->makeTeam(['team_name' => 'Any Team'], paid: false);

        $response = $this->actingAs($this->admin, 'expert')
            ->get(route('admin.panel_assignment', ['defense_type' => 'Pre-proposal Defense']));

        $response->assertOk();
        $response->assertSee('Any Team');
        $response->assertSee('has no fee');
        // "pa-tab-btn-paid" also appears inside showPaymentTab()'s always-
        // present JS body, so check the conditionally-rendered container
        // element instead of the tab button id.
        $response->assertDontSee('id="paid-panel"', false);
        $response->assertSee('id="all-panel"', false);
    }

    public function test_an_invalid_defense_type_falls_back_to_the_default(): void
    {
        $response = $this->actingAs($this->admin, 'expert')
            ->get(route('admin.panel_assignment', ['defense_type' => 'Not A Real Stage']));

        $response->assertOk();
        $response->assertSee('No teams have paid for Title Defense yet.');
    }

    public function test_team_row_shows_the_currently_selected_stage_not_the_latest_created_schedule(): void
    {
        $team = $this->makeTeam(); // paid for Title Defense by default
        $preProposalChair = $this->makeExpert(['first_name' => 'PreProposal']);
        $titleChair = $this->makeExpert(['first_name' => 'TitleStage']);

        // Pre-proposal Defense already scheduled (real date)...
        $preSchedule = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Pre-proposal Defense',
            'schedule_date' => '2027-05-11 08:00:00',
            'venue' => 'Mac Lab',
        ]);
        DefensePanel::create([
            'defense_id' => $preSchedule->defense_id,
            'expert_id' => $preProposalChair->expert_id,
            'panel_role' => 'Panel Chair',
            'created_at' => now(),
        ]);

        // ...then a Title Defense panel is assigned AFTER (higher defense_id), still unscheduled.
        $this->assignPanel($team, 'Title Defense', $titleChair);

        $preProposalView = $this->actingAs($this->admin, 'expert')
            ->get(route('admin.panel_assignment', ['defense_type' => 'Pre-proposal Defense']));
        $preProposalView->assertOk()->assertSee($preProposalChair->name);

        $titleView = $this->actingAs($this->admin, 'expert')
            ->get(route('admin.panel_assignment', ['defense_type' => 'Title Defense']));
        $titleView->assertOk()->assertSee($titleChair->name)->assertDontSee($preProposalChair->name);
    }

    // ── Scheduling payment gate (generateScheduleForWindow) ───────────────

    public function test_saving_a_window_only_schedules_paid_teams_for_a_fee_bearing_stage(): void
    {
        $paidTeam = $this->makeTeam(['team_name' => 'Paid Team']);
        $unpaidTeam = $this->makeTeam(['team_name' => 'Unpaid Team'], paid: false);
        $chairA = $this->makeExpert();
        $chairB = $this->makeExpert();

        $this->assignPanel($paidTeam, 'Title Defense', $chairA);
        $this->assignPanel($unpaidTeam, 'Title Defense', $chairB);

        $response = $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05');
        $response->assertStatus(200)->assertJson(['success' => true, 'scheduled_count' => 1]);

        $this->assertNotNull(DefenseSchedule::where('team_id', $paidTeam->team_id)->first()->schedule_date);
        $this->assertNull(DefenseSchedule::where('team_id', $unpaidTeam->team_id)->first()->schedule_date);
    }

    public function test_saving_a_window_schedules_everyone_for_pre_proposal_defense_regardless_of_payment(): void
    {
        $paidTeam = $this->makeTeam(['team_name' => 'Paid Team']);
        $unpaidTeam = $this->makeTeam(['team_name' => 'Unpaid Team'], paid: false);
        $chairA = $this->makeExpert();
        $chairB = $this->makeExpert();

        $this->assignPanel($paidTeam, 'Pre-proposal Defense', $chairA);
        $this->assignPanel($unpaidTeam, 'Pre-proposal Defense', $chairB);

        $response = $this->saveWindow('Pre-proposal Defense', '2026-09-01', '2026-09-05');
        $response->assertStatus(200)->assertJson(['success' => true, 'scheduled_count' => 2]);

        // Assigning via Pre-proposal Defense propagates the panel to the
        // other 3 stages too, so each team now has 4 schedule rows - scope
        // explicitly to the stage this test actually cares about.
        $this->assertNotNull(DefenseSchedule::where('team_id', $paidTeam->team_id)->where('defense_type', 'Pre-proposal Defense')->first()->schedule_date);
        $this->assertNotNull(DefenseSchedule::where('team_id', $unpaidTeam->team_id)->where('defense_type', 'Pre-proposal Defense')->first()->schedule_date);
    }

    // ── Bulk multi-team assignment ────────────────────────────────────────

    public function test_bulk_store_assigns_panel_to_multiple_paid_teams(): void
    {
        $teamA = $this->makeTeam(['team_name' => 'Team A']);
        $teamB = $this->makeTeam(['team_name' => 'Team B']);
        $chair = $this->makeExpert();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store.bulk'), [
                'team_ids' => [$teamA->team_id, $teamB->team_id],
                'defense_type' => 'Title Defense',
                'chair_expert_id' => $chair->expert_id,
            ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertCount(2, $response->json('data.assigned'));
        $this->assertCount(0, $response->json('data.skipped'));

        foreach ([$teamA, $teamB] as $team) {
            $schedule = DefenseSchedule::where('team_id', $team->team_id)->first();
            $this->assertNotNull($schedule);
            $this->assertDatabaseHas('defense_panels', [
                'defense_id' => $schedule->defense_id,
                'expert_id' => $chair->expert_id,
                'panel_role' => 'Panel Chair',
            ]);
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

        $assignment = new TeamExpertAssignment();
        $assignment->team_id = $conflictedTeam->team_id;
        $assignment->expert_id = $chair->expert_id;
        $assignment->status = 'approved';
        $assignment->save();

        $response = $this->actingAs($this->admin, 'expert')
            ->postJson(route('api.admin.panel_assignment.store.bulk'), [
                'team_ids' => [$paidTeam->team_id, $unpaidTeam->team_id, $conflictedTeam->team_id],
                'defense_type' => 'Title Defense',
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

    public function test_suggest_multi_matches_against_the_union_of_selected_teams_tags(): void
    {
        $teamA = $this->makeTeam(['team_name' => 'Team A', 'tags' => ['AI']]);
        $teamB = $this->makeTeam(['team_name' => 'Team B', 'tags' => ['Web Development']]);

        $aiExpert = $this->makeExpert(['first_name' => 'Turing']);
        ExpertExpertise::create(['expert_id' => $aiExpert->expert_id, 'expertise_field' => 'Artificial Intelligence']);

        $webExpert = $this->makeExpert(['first_name' => 'Berners-Lee']);
        ExpertExpertise::create(['expert_id' => $webExpert->expert_id, 'expertise_field' => 'Web Development']);

        $unrelatedExpert = $this->makeExpert(['first_name' => 'Nobody']);
        ExpertExpertise::create(['expert_id' => $unrelatedExpert->expert_id, 'expertise_field' => 'Cybersecurity']);

        $response = $this->actingAs($this->admin, 'expert')
            ->getJson(route('api.admin.panel_assignment.suggest-multi', ['team_ids' => [$teamA->team_id, $teamB->team_id]]));

        $response->assertStatus(200)->assertJson(['success' => true]);

        $ids = collect($response->json('data'))->pluck('expert_id');
        $this->assertTrue($ids->contains($aiExpert->expert_id));
        $this->assertTrue($ids->contains($webExpert->expert_id));
        $this->assertFalse($ids->contains($unrelatedExpert->expert_id));
    }

    public function test_suggest_multi_excludes_an_adviser_of_any_selected_team(): void
    {
        $teamA = $this->makeTeam(['team_name' => 'Team A', 'tags' => ['AI']]);
        $teamB = $this->makeTeam(['team_name' => 'Team B', 'tags' => ['AI']]);
        $adviser = $this->makeExpert();
        ExpertExpertise::create(['expert_id' => $adviser->expert_id, 'expertise_field' => 'Artificial Intelligence']);

        $assignment = new TeamExpertAssignment();
        $assignment->team_id = $teamB->team_id;
        $assignment->expert_id = $adviser->expert_id;
        $assignment->status = 'approved';
        $assignment->save();

        $response = $this->actingAs($this->admin, 'expert')
            ->getJson(route('api.admin.panel_assignment.suggest-multi', ['team_ids' => [$teamA->team_id, $teamB->team_id]]));

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('expert_id');
        $this->assertFalse($ids->contains($adviser->expert_id));
    }

    // ── Suggest Teams for a Panel (reverse direction) ─────────────────────

    public function test_suggest_teams_ranks_teams_by_how_many_tags_match_the_panels_combined_expertise(): void
    {
        $aiExpert = $this->makeExpert(['first_name' => 'Turing']);
        ExpertExpertise::create(['expert_id' => $aiExpert->expert_id, 'expertise_field' => 'Artificial Intelligence']);

        $webExpert = $this->makeExpert(['first_name' => 'Berners-Lee']);
        ExpertExpertise::create(['expert_id' => $webExpert->expert_id, 'expertise_field' => 'Web Development']);

        $bothMatch = $this->makeTeam(['team_name' => 'Both Match', 'tags' => ['AI', 'Web Development']]);
        $oneMatch = $this->makeTeam(['team_name' => 'One Match', 'tags' => ['AI']]);
        $noMatch = $this->makeTeam(['team_name' => 'No Match', 'tags' => ['Cybersecurity']]);

        $response = $this->actingAs($this->admin, 'expert')
            ->getJson(route('api.admin.panel_assignment.suggest-teams', [
                'expert_ids' => [$aiExpert->expert_id, $webExpert->expert_id],
                'defense_type' => 'Title Defense',
            ]));

        $response->assertStatus(200)->assertJson(['success' => true]);

        $data = collect($response->json('data'));
        $ids = $data->pluck('team_id');

        $this->assertTrue($ids->contains($bothMatch->team_id));
        $this->assertTrue($ids->contains($oneMatch->team_id));
        $this->assertFalse($ids->contains($noMatch->team_id));

        // Higher-scoring match (2 tags) ranks before the 1-tag match.
        $this->assertSame($bothMatch->team_id, $data->first()['team_id']);
    }

    public function test_suggest_teams_excludes_a_team_where_a_picked_expert_is_its_adviser_or_consultant(): void
    {
        $aiExpert = $this->makeExpert();
        ExpertExpertise::create(['expert_id' => $aiExpert->expert_id, 'expertise_field' => 'Artificial Intelligence']);

        $team = $this->makeTeam(['team_name' => 'Conflicted Team', 'tags' => ['AI']]);

        $assignment = new TeamExpertAssignment();
        $assignment->team_id = $team->team_id;
        $assignment->expert_id = $aiExpert->expert_id;
        $assignment->status = 'approved';
        $assignment->save();

        $response = $this->actingAs($this->admin, 'expert')
            ->getJson(route('api.admin.panel_assignment.suggest-teams', [
                'expert_ids' => [$aiExpert->expert_id],
                'defense_type' => 'Title Defense',
            ]));

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('team_id');
        $this->assertFalse($ids->contains($team->team_id));
    }

    public function test_suggest_teams_ignores_payment_status_entirely(): void
    {
        // A panel assigned to a team is that team's panel for every defense
        // stage, not scoped to one stage's payment - suggestions must
        // include an unpaid team exactly the same as a paid one, and never
        // report payment status at all.
        $aiExpert = $this->makeExpert();
        ExpertExpertise::create(['expert_id' => $aiExpert->expert_id, 'expertise_field' => 'Artificial Intelligence']);

        $paid = $this->makeTeam(['team_name' => 'Paid AI Team', 'tags' => ['AI']]);
        $unpaid = $this->makeTeam(['team_name' => 'Unpaid AI Team', 'tags' => ['AI']], paid: false);

        $response = $this->actingAs($this->admin, 'expert')
            ->getJson(route('api.admin.panel_assignment.suggest-teams', [
                'expert_ids' => [$aiExpert->expert_id],
            ]));

        $response->assertStatus(200);
        $data = collect($response->json('data'));
        $ids = $data->pluck('team_id');

        $this->assertTrue($ids->contains($paid->team_id));
        $this->assertTrue($ids->contains($unpaid->team_id));
        $this->assertArrayNotHasKey('is_paid', $data->first());
    }

    // ── Google Calendar sync ─────────────────────────────────────────────

    public function test_window_generation_never_calls_google_calendar_when_no_panelist_is_connected(): void
    {
        $this->mock(\App\Services\GoogleCalendarService::class, function ($mock) {
            $mock->shouldNotReceive('createDefensePanelEvent');
            $mock->shouldNotReceive('cancelBookingEvent');
        });

        $team = $this->makeTeam();
        $chair = $this->makeExpert(); // no google_token - unconnected
        $this->assignPanel($team, 'Title Defense', $chair);

        $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05')
            ->assertJson(['success' => true]);

        $schedule = DefenseSchedule::where('team_id', $team->team_id)->first();
        $this->assertNotNull($schedule->schedule_date);

        $panel = DefensePanel::where('defense_id', $schedule->defense_id)->first();
        $this->assertNull($panel->google_event_id);
    }

    public function test_window_generation_creates_a_calendar_event_for_a_connected_panelist(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert(['google_token' => json_encode(['access_token' => 'fake'])]);

        $this->mock(\App\Services\GoogleCalendarService::class, function ($mock) use ($chair) {
            $mock->shouldReceive('createDefensePanelEvent')
                ->once()
                ->withArgs(fn ($organizerExpertId, $date, $startTime, $endTime, $defenseType, $teamName, $venue, $attendeeEmails) => $organizerExpertId === $chair->expert_id
                    && $defenseType === 'Title Defense'
                    && $venue !== null
                )
                ->andReturn('fake-google-event-id');
        });

        $this->assignPanel($team, 'Title Defense', $chair);

        $this->saveWindow('Title Defense', '2026-09-01', '2026-09-05')
            ->assertJson(['success' => true]);

        $schedule = DefenseSchedule::where('team_id', $team->team_id)->first();
        $panel = DefensePanel::where('defense_id', $schedule->defense_id)->where('expert_id', $chair->expert_id)->first();

        $this->assertSame('fake-google-event-id', $panel->google_event_id);
    }

    public function test_manual_override_recreates_the_calendar_event_for_an_already_synced_panelist(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makeExpert(['google_token' => json_encode(['access_token' => 'fake'])]);
        $schedule = $this->scheduleWithPanel($team, 'Title Defense', $chair, '2027-03-10', 'Mac Lab');

        DefensePanel::where('defense_id', $schedule->defense_id)
            ->where('expert_id', $chair->expert_id)
            ->update(['google_event_id' => 'old-event-id']);

        $this->mock(\App\Services\GoogleCalendarService::class, function ($mock) use ($chair) {
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

        $this->mock(\App\Services\GoogleCalendarService::class, function ($mock) use ($chair) {
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
