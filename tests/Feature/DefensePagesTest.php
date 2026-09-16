<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\DefenseWindow;
use App\Models\ExpertRole;
use App\Models\GradingRubric;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Services\DefenseOutcomeRecorder;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Panel Assignment and Defense Scheduling as separate pages: a CaPSA works
 * with her own sections, other sections' bookings still count, saving the
 * window schedules nobody, a redefense keeps its panel, and a stage with no
 * rubric is flagged.
 */
class DefensePagesTest extends TestCase
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

    private function staff(Team $team): void
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
    }

    private function scheduled(Team $team, string $at, string $venue = 'Mac Lab', string $stage = 'Title Defense'): DefenseSchedule
    {
        $schedule = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => $stage, 'attempt' => 1, 'schedule_date' => $at, 'end_date' => $at, 'venue' => $venue]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true, 'highest_degree' => 'master'])->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        return $schedule;
    }

    public function test_both_pages_list_only_the_teams_in_her_sections(): void
    {
        [$mine] = $this->teamInSection('Mine');
        $this->teamInSection('Theirs', yearSection: '3rd · B');

        foreach (['capsa.panel_assignment', 'capsa.defense_scheduling'] as $page) {
            $this->actingAs($this->capsa, 'expert')
                ->get(route($page))
                ->assertOk()
                ->assertViewHas('state', fn ($state) => collect($state['teams'])->pluck('name')->all() === [$mine->team_name]);
        }
    }

    public function test_saving_the_window_only_stores_the_dates(): void
    {
        [$team] = $this->teamInSection('Ready');
        $this->staff($team);
        $schedule = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => DefenseOutcome::PRE_PROPOSAL, 'attempt' => 1]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.windows.update', DefenseOutcome::PRE_PROPOSAL), ['start_date' => '2027-03-15', 'end_date' => '2027-03-19'])
            ->assertOk()
            ->assertJsonPath('data.start_date', '2027-03-15');

        $this->assertSame('2027-03-19', DefenseWindow::where('defense_type', DefenseOutcome::PRE_PROPOSAL)->first()->end_date->toDateString());
        $this->assertNull($schedule->fresh()->schedule_date, 'Nobody is scheduled until Generate schedule is saved.');
    }

    public function test_another_sections_booking_cannot_be_overruled(): void
    {
        [$mine] = $this->teamInSection('Mine');
        [$theirs] = $this->teamInSection('Theirs', yearSection: '3rd · B');
        $own = $this->scheduled($mine, '2027-03-15 08:00:00');
        $this->scheduled($theirs, '2027-03-16 10:00:00', 'Open Lab');

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.schedule.update', $own->defense_id), [
                'schedule_date' => '2027-03-16', 'start_time' => '10:00', 'venue' => 'Open Lab', 'confirm' => true,
            ])
            ->assertStatus(409)
            ->assertJson(['other_section' => true, 'requires_confirmation' => false]);

        $this->assertSame('2027-03-15', $own->fresh()->schedule_date->toDateString());
    }

    public function test_a_capsa_cannot_move_a_defense_outside_her_sections(): void
    {
        [$theirs] = $this->teamInSection('Theirs', yearSection: '3rd · B');
        $schedule = $this->scheduled($theirs, '2027-03-15 08:00:00');

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.schedule.update', $schedule->defense_id), [
                'schedule_date' => '2027-03-17', 'start_time' => '08:00', 'venue' => 'Mac Lab',
            ])
            ->assertForbidden();
    }

    public function test_a_redefense_keeps_the_same_panel(): void
    {
        [$team] = $this->teamInSection('Bravo');
        $schedule = $this->scheduled($team, '2027-03-15 08:00:00');
        $member = CapstoneExpert::factory()->create(['approved_status' => true]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => $member->expert_id, 'panel_role' => 'Panel Member 1', 'created_at' => now()]);
        $chair = $schedule->panels()->where('panel_role', 'Panel Chair')->first()->expert;

        $result = app(DefenseOutcomeRecorder::class)->record($schedule, DefenseOutcome::REDEFENSE, $chair);

        $this->assertSame(2, $result['redefense']->attempt);
        $this->assertNull($result['redefense']->schedule_date);
        $this->assertEqualsCanonicalizing(
            $schedule->panels()->get(['expert_id', 'panel_role'])->map->only('expert_id', 'panel_role')->all(),
            $result['redefense']->panels()->get(['expert_id', 'panel_role'])->map->only('expert_id', 'panel_role')->all(),
        );
    }

    public function test_a_stage_without_a_rubric_is_flagged_but_not_blocked(): void
    {
        $this->actingAs($this->capsa, 'expert')
            ->get(route('capsa.defense_scheduling', ['defense_type' => 'Title Defense']))
            ->assertOk()
            ->assertSee('No active grading rubric for Title Defense');

        GradingRubric::create(['rubric_name' => 'Title Defense Rubric', 'rubric_type' => 'Title Defense', 'total_points' => 100, 'is_active' => true, 'is_archived' => false]);

        $this->actingAs($this->capsa, 'expert')
            ->get(route('capsa.defense_scheduling', ['defense_type' => 'Title Defense']))
            ->assertOk()
            ->assertDontSee('No active grading rubric');
    }
}
