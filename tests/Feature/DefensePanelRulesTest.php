<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\ExpertAwayDate;
use App\Models\ExpertRole;
use App\Models\Notification;
use App\Models\PanelReplacement;
use App\Models\Role;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\DefenseBoard;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * The Defenses page's panel rules: a panel is a draft until the CaPSA/TSA
 * finalizes it, a final panel only changes one panelist at a time, a
 * panelist away keeps the seat, and every CaPSA/TSA shares the window.
 */
class DefensePanelRulesTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->capsa = $this->capsaFor(['BSIT-3A']);
    }

    /** @return array<string, CapstoneExpert> The team's adviser, consultant and grammarian. */
    private function staff(Team $team): array
    {
        $out = [];

        foreach (Team::REQUIRED_EXPERT_ROLES as $roleName) {
            $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'first_login_at' => now()]);
            $role = Role::firstOrCreate(['role_name' => $roleName]);
            $expert->roles()->attach($role->role_id);
            TeamExpertAssignment::create([
                'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
                'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
            ]);
            $out[$roleName] = $expert;
        }

        return $out;
    }

    private function panelist(string $degree = 'master'): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true, 'first_login_at' => now(), 'highest_degree' => $degree]);
        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'adviser'])->role_id);

        return $expert;
    }

    private function staffedTeam(string $name = 'Alpha'): Team
    {
        [$team] = $this->teamInSection($name);
        $this->staff($team);

        return $team;
    }

    private function assign(Team $team, CapstoneExpert $chair, ?CapstoneExpert $m1 = null)
    {
        return $this->actingAs($this->capsa, 'expert')->postJson(route('api.admin.panel_assignment.store', $team), [
            'chair_expert_id' => $chair->expert_id,
            'member1_expert_id' => $m1?->expert_id,
        ]);
    }

    private function row(Team $team, string $stage = DefenseOutcome::PRE_PROPOSAL): array
    {
        return collect($this->actingAs($this->capsa, 'expert')
            ->getJson(route('api.admin.panel_assignment.board', ['defense_type' => $stage]))
            ->assertOk()->json('data.teams'))->firstWhere('id', $team->team_id);
    }

    public function test_a_new_panel_is_a_draft_and_cannot_be_scheduled(): void
    {
        $team = $this->staffedTeam();
        $this->assign($team, $this->panelist())->assertOk();

        $this->assertSame(Team::PANEL_DRAFT, $team->fresh()->panel_status);
        $row = $this->row($team);
        $this->assertSame('draft', $row['panel_status']);
        $this->assertContains('draft', array_column($row['blockers'], 'key'));

        DefenseWindow::create(['defense_type' => DefenseOutcome::PRE_PROPOSAL, 'start_date' => now()->addWeek()->startOfWeek(), 'end_date' => now()->addWeek()->startOfWeek()->addDays(4)]);
        $notReady = $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.schedule-drafts.store'), ['defense_type' => DefenseOutcome::PRE_PROPOSAL])
            ->assertOk()->json('data.not_ready');
        $this->assertSame('Panel is still a draft.', collect($notReady)->firstWhere('team_id', $team->team_id)['reason']);
    }

    /** Only a CaPA or a consultant sits on a panel; a grammarian never does. */
    public function test_someone_who_is_only_a_grammarian_cannot_sit_on_a_panel(): void
    {
        $grammarian = CapstoneExpert::factory()->create(['approved_status' => true, 'first_login_at' => now()]);
        $grammarian->roles()->attach(Role::firstOrCreate(['role_name' => 'grammarian'])->role_id);

        $this->assign($this->staffedTeam(), $grammarian)->assertStatus(422)->assertJsonValidationErrors('chair_expert_id');
        $this->assertSame(0, \App\Models\DefensePanel::count());
    }

    public function test_a_draft_panel_can_still_change_and_a_final_one_cannot(): void
    {
        $team = $this->staffedTeam();
        $this->assign($team, $this->panelist())->assertOk();
        $this->assign($team, $this->panelist())->assertOk();

        $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.finalize'), ['team_ids' => [$team->team_id]])
            ->assertOk()
            ->assertJsonPath('data.finalized', [$team->team_id]);

        $team->refresh();
        $this->assertTrue($team->panelIsFinal());
        $this->assertSame($this->capsa->expert_id, (int) $team->panel_finalized_by);
        $this->assertNotContains('draft', array_column($this->row($team)['blockers'], 'key'));

        $this->assign($team, $this->panelist())->assertStatus(422)->assertJsonFragment(['message' => 'Alpha\'s panel is final. Replace one panelist instead.']);
        $this->actingAs($this->capsa, 'expert')->postJson(route('api.admin.panel_assignment.store', $team), ['chair_expert_id' => null])->assertStatus(422);
    }

    public function test_finalizing_skips_a_team_with_no_panel_or_outside_her_sections(): void
    {
        $bare = $this->staffedTeam('Bare');
        [$theirs] = $this->teamInSection('Theirs', yearSection: '3rd · B');

        $response = $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.finalize'), ['team_ids' => [$bare->team_id, $theirs->team_id]])
            ->assertOk();

        $this->assertSame([], $response->json('data.finalized'));
        $this->assertEqualsCanonicalizing(['Has no panel yet.', 'Not in your sections.'], array_column($response->json('data.skipped'), 'reason'));
    }

    public function test_replacing_a_panelist_keeps_held_defenses_and_changes_the_rest(): void
    {
        $team = $this->staffedTeam();
        $chair = $this->panelist();
        $member = $this->panelist('bachelor');
        $this->assign($team, $chair, $member)->assertOk();
        $team->update(['panel_status' => Team::PANEL_FINAL]);

        $pre = DefenseSchedule::where('team_id', $team->team_id)->where('defense_type', DefenseOutcome::PRE_PROPOSAL)->first();
        $pre->update(['schedule_date' => now()->subWeek(), 'end_date' => now()->subWeek()->addHours(2), 'venue' => 'Mac Lab', 'outcome' => DefenseOutcome::PASSED]);

        $new = $this->panelist('bachelor');

        $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.replacements.store', $team), ['seat' => 'm1', 'expert_id' => $new->expert_id, 'reason' => 'Resigned from the college'])
            ->assertOk();

        $this->assertSame($member->expert_id, (int) $pre->panels()->where('panel_role', 'Panel Member 1')->value('expert_id'), 'The held Pre-proposal keeps who sat it.');

        foreach (['Title Defense', 'Pre Oral Defense', 'Final Oral Defense'] as $stage) {
            $schedule = DefenseSchedule::where('team_id', $team->team_id)->where('defense_type', $stage)->first();
            $this->assertSame($new->expert_id, (int) $schedule->panels()->where('panel_role', 'Panel Member 1')->value('expert_id'), $stage);
        }

        $this->assertDatabaseHas('panel_replacements', ['team_id' => $team->team_id, 'old_expert_id' => $member->expert_id, 'new_expert_id' => $new->expert_id, 'reason' => 'Resigned from the college']);
        $this->assertTrue(Notification::where('expert_id', $new->expert_id)->exists());
    }

    public function test_a_replacement_follows_the_panel_rules(): void
    {
        $team = $this->staffedTeam();
        $staff = TeamExpertAssignment::where('team_id', $team->team_id)->pluck('expert_id');
        $this->assign($team, $this->panelist())->assertOk();
        $replace = fn (array $body) => $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.replacements.store', $team), $body + ['reason' => 'On long leave']);

        // A draft is changed, not replaced.
        $replace(['seat' => 'chair', 'expert_id' => $this->panelist()->expert_id])->assertStatus(422);

        $team->update(['panel_status' => Team::PANEL_FINAL]);
        $replace(['seat' => 'chair', 'expert_id' => $this->panelist('bachelor')->expert_id])->assertStatus(422)->assertJsonFragment(['message' => 'A Panel Chair needs a Master\'s degree or higher.']);
        $replace(['seat' => 'chair', 'expert_id' => $staff->first()])->assertStatus(422);
        $replace(['seat' => 'chair', 'expert_id' => $this->panelist()->expert_id, 'reason' => 'no'])->assertStatus(422);

        $this->assertSame(0, PanelReplacement::count());
    }

    public function test_a_panelist_away_keeps_the_seat_and_is_not_scheduled_on_those_days(): void
    {
        $team = $this->staffedTeam();
        $chair = $this->panelist();
        $this->assign($team, $chair)->assertOk();
        $team->update(['panel_status' => Team::PANEL_FINAL]);

        $monday = now()->addWeeks(2)->startOfWeek();
        DefenseWindow::create(['defense_type' => DefenseOutcome::PRE_PROPOSAL, 'start_date' => $monday, 'end_date' => $monday->copy()->addDay()]);

        $this->actingAs($this->capsa, 'expert')->postJson(route('api.admin.panel_assignment.away.store'), [
            'expert_id' => $chair->expert_id, 'starts_on' => $monday->toDateString(), 'ends_on' => $monday->toDateString(), 'reason' => 'Travel',
        ])->assertCreated();

        $draft = $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.schedule-drafts.store'), ['defense_type' => DefenseOutcome::PRE_PROPOSAL])
            ->assertOk()->json('data.draft');
        $this->assertSame($monday->copy()->addDay()->toDateString(), $draft[0]['date'], 'Generate skips the day the chair is away.');

        // Placed by hand on the day away: asked to confirm, and kept on a second press.
        $defense = DefenseSchedule::where('team_id', $team->team_id)->where('defense_type', DefenseOutcome::PRE_PROPOSAL)->first();
        $body = ['schedule_date' => $monday->toDateString(), 'start_time' => '08:00', 'venue' => 'Mac Lab'];
        $this->actingAs($this->capsa, 'expert')->putJson(route('api.admin.panel_assignment.schedule.update', $defense), $body)
            ->assertStatus(409)->assertJson(['requires_confirmation' => true, 'other_section' => false]);
        $this->actingAs($this->capsa, 'expert')->putJson(route('api.admin.panel_assignment.schedule.update', $defense), $body + ['confirm' => true])->assertOk();

        $this->assertSame([$chair->expert_id], array_column($this->actingAs($this->capsa, 'expert')->getJson(route('api.admin.panel_assignment.board'))->json('data.away'), 'expert_id'));
        $this->assertSame((string) $chair->expert_id, (string) DefensePanel::where('defense_id', $defense->defense_id)->where('panel_role', 'Panel Chair')->value('expert_id'));
    }

    public function test_away_dates_can_be_removed(): void
    {
        $away = ExpertAwayDate::factory()->create();

        $this->actingAs($this->capsa, 'expert')->deleteJson(route('api.admin.panel_assignment.away.destroy', $away))->assertOk();
        $this->assertModelMissing($away);
    }

    public function test_any_capsa_sets_the_shared_window_and_the_others_hear_of_it(): void
    {
        $other = $this->capsaFor(['BSIT-3B']);

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.windows.update', DefenseOutcome::TITLE_DEFENSE), ['start_date' => '2027-04-05', 'end_date' => '2027-04-09'])
            ->assertOk()
            ->assertJsonPath('data.set_by', $this->capsa->name);

        $this->actingAs($other, 'expert')
            ->putJson(route('api.admin.panel_assignment.windows.update', DefenseOutcome::TITLE_DEFENSE), ['start_date' => '2027-04-12', 'end_date' => '2027-04-16'])
            ->assertOk();

        $this->assertSame($other->expert_id, (int) DefenseWindow::where('defense_type', DefenseOutcome::TITLE_DEFENSE)->value('updated_by'));
        $this->assertTrue(Notification::where('expert_id', $other->expert_id)->where('type', 'defense_window')->exists());
        $this->assertTrue(Notification::where('expert_id', $this->capsa->expert_id)->where('type', 'defense_window')->exists());
    }

    public function test_a_capsa_marks_a_returnee_in_her_sections_only(): void
    {
        [$team, $leader] = $this->teamInSection('Alpha');
        [, $outsider] = $this->teamInSection('Theirs', yearSection: '3rd · B');

        $this->actingAs($this->capsa, 'expert')
            ->patchJson(route('api.admin.panel_assignment.returnee.update', $leader), ['is_returnee' => true])
            ->assertOk();
        $this->assertTrue($leader->fresh()->is_returnee);
        $this->assertTrue($this->row($team)['member_list'][0]['returnee']);

        $this->actingAs($this->capsa, 'expert')
            ->patchJson(route('api.admin.panel_assignment.returnee.update', $outsider), ['is_returnee' => true])
            ->assertForbidden();
        $this->assertFalse((bool) Student::find($outsider->student_id)->is_returnee);
    }

    public function test_a_save_waits_while_another_capsa_is_saving(): void
    {
        $team = $this->staffedTeam();
        $this->assign($team, $this->panelist())->assertOk();
        $defense = DefenseSchedule::where('team_id', $team->team_id)->first();
        $lock = Cache::lock('defense-schedule-save', 10);
        $lock->get();

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.schedule.update', $defense), ['schedule_date' => now()->addWeeks(2)->startOfWeek()->toDateString(), 'start_time' => '08:00', 'venue' => 'Mac Lab'])
            ->assertStatus(423);

        $lock->release();
    }

    public function test_an_expert_full_on_panels_cannot_sit_on_another_team(): void
    {
        $chair = $this->panelist();
        $chair->update(['max_panels' => 1]);
        $alpha = $this->staffedTeam('Alpha');
        $beta = $this->staffedTeam('Beta');

        $this->assign($alpha, $chair)->assertOk();
        $this->assign($beta, $chair)->assertStatus(422)
            ->assertJsonFragment(['message' => $chair->name.' can sit on 1 team\'s panel and has no seat left.']);
        $this->assign($alpha, $chair, $this->panelist('bachelor'))->assertOk();

        $seat = app(DefenseBoard::class)->panelists()->firstWhere('id', $chair->expert_id);
        $this->assertSame([1, 1], [$seat['panels'], $seat['max_panels']]);

        // A team done defending frees the seat.
        DefenseSchedule::where('team_id', $alpha->team_id)->update(['outcome' => DefenseOutcome::PASSED]);
        $this->assign($beta, $chair)->assertOk();
    }

    public function test_no_limit_takes_any_number_and_a_bulk_assign_stops_at_the_limit(): void
    {
        $open = $this->panelist();
        foreach (['A', 'B', 'C'] as $name) {
            $this->assign($this->staffedTeam($name), $open)->assertOk();
        }

        $chair = $this->panelist();
        $chair->update(['max_panels' => 2]);
        $ids = collect(['D', 'E', 'F'])->map(fn ($name) => $this->staffedTeam($name)->team_id)->all();

        $data = $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.store.bulk'), ['team_ids' => $ids, 'chair_expert_id' => $chair->expert_id])
            ->assertOk()->json('data');

        $this->assertCount(2, $data['assigned']);
        $this->assertSame([$chair->name.' can sit on 2 teams\' panels and has no seat left.'], array_column($data['skipped'], 'reason'));
    }

    public function test_a_replacement_cannot_be_someone_with_no_panel_seat_left(): void
    {
        $team = $this->staffedTeam();
        $this->assign($team, $this->panelist(), $this->panelist('bachelor'))->assertOk();
        $team->update(['panel_status' => Team::PANEL_FINAL]);

        $full = $this->panelist('bachelor');
        $full->update(['max_panels' => 1]);
        $this->assign($this->staffedTeam('Beta'), $this->panelist(), $full)->assertOk();

        $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.replacements.store', $team), ['seat' => 'm1', 'expert_id' => $full->expert_id, 'reason' => 'Resigned from the college'])
            ->assertStatus(422)->assertJsonFragment(['message' => $full->name.' can sit on 1 team\'s panel and has no seat left.']);
        $this->assertSame(0, PanelReplacement::count());
    }
}
