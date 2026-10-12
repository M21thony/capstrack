<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefenseCancellation;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\ExpertRole;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Services\CapaGo;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Title, Pre Oral and Final Oral Defense go ahead on the CaPA's word. "Not
 * ready" cancels the date, keeps it on record, and puts the team back in
 * line for its CaPSA to give it a new one.
 */
class CapaGoTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private CapstoneExpert $adviser;

    private Team $team;

    private Student $leader;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->capsa = $this->capsaFor(['BSIT-3A']);
        [$this->team, $this->leader] = $this->teamInSection('Alpha');

        foreach (Team::REQUIRED_EXPERT_ROLES as $roleName) {
            $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
            $role = Role::firstOrCreate(['role_name' => $roleName]);
            $expert->roles()->attach($role->role_id);
            TeamExpertAssignment::create([
                'team_id' => $this->team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
                'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
            ]);

            if ($roleName === 'adviser') {
                $this->adviser = $expert;
            }
        }
    }

    private function defense(string $stage = DefenseOutcome::TITLE_DEFENSE): DefenseSchedule
    {
        $at = now()->addWeek()->startOfWeek()->setTime(8, 0);
        $schedule = DefenseSchedule::create(['team_id' => $this->team->team_id, 'defense_type' => $stage, 'attempt' => 1, 'schedule_date' => $at, 'end_date' => $at->copy()->addHours(2), 'venue' => 'Mac Lab']);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true, 'highest_degree' => 'master'])->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        return $schedule;
    }

    private function row(string $stage = DefenseOutcome::TITLE_DEFENSE): array
    {
        return collect($this->actingAs($this->capsa, 'expert')
            ->getJson(route('api.admin.panel_assignment.board', ['defense_type' => $stage]))
            ->json('data.teams'))->firstWhere('id', $this->team->team_id);
    }

    public function test_the_adviser_sees_the_defense_and_gives_the_go(): void
    {
        $defense = $this->defense();

        // In the adviser's To do, decided from the To do's own dialog.
        $this->actingAs($this->adviser, 'expert')->get(route('instructor.teams', ['tab' => 'todo']))
            ->assertOk()->assertSee('Defenses waiting for your go')->assertSee('Alpha')
            ->assertSee('data-url="'.route('instructor.defense-go.update', $defense).'"', false)
            ->assertSee('data-note-optional="1"', false);

        $this->actingAs($this->adviser, 'expert')
            ->patchJson(route('instructor.defense-go.update', $defense), ['answer' => 'go'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(DefenseSchedule::CAPA_GO, $defense->fresh()->capa_answer);
        $this->assertSame('go', $this->row()['capa']);
    }

    public function test_not_ready_cancels_the_defense_and_the_team_waits_for_a_new_date(): void
    {
        $defense = $this->defense();
        $was = $defense->schedule_date->copy();

        $this->actingAs($this->adviser, 'expert')
            ->patch(route('instructor.defense-go.update', $defense), ['answer' => 'not_ready', 'note' => 'Chapter 3 not done'])
            ->assertRedirect();

        $defense->refresh();
        $this->assertSame(DefenseOutcome::CANCELLED, $defense->outcome);
        $this->assertTrue($defense->schedule_date->equalTo($was));
        $this->assertSame('Mac Lab', $defense->venue);
        $this->assertSame(0, DefenseCancellation::count());

        $row = $this->row();
        $this->assertSame('cancelled', $row['waiting']);
        $this->assertSame(2, $row['attempt']);
        $this->assertSame(['kind' => 'cancelled', 'date' => $was->toDateString(), 'note' => 'Chapter 3 not done', 'auto' => false], $row['tries'][0]);
        $this->assertTrue(Notification::where('student_id', $this->leader->student_id)->where('title', 'like', '%cancelled%')->exists());
    }

    public function test_only_the_teams_adviser_answers(): void
    {
        $defense = $this->defense();
        $someone = CapstoneExpert::factory()->create(['approved_status' => true]);

        $this->actingAs($someone, 'expert')->patch(route('instructor.defense-go.update', $defense), ['answer' => 'go'])->assertForbidden();
        $this->assertNull($defense->fresh()->capa_answer);
    }

    public function test_the_pre_proposal_needs_no_go(): void
    {
        $defense = $this->defense(DefenseOutcome::PRE_PROPOSAL);

        $this->actingAs($this->adviser, 'expert')->patch(route('instructor.defense-go.update', $defense), ['answer' => 'not_ready'])
            ->assertSessionHas('status', 'The Pre-proposal Defense needs no go from the CaPA.');
        $this->assertNotNull($defense->fresh()->schedule_date);
    }

    public function test_the_capsa_can_record_what_the_capa_said(): void
    {
        $defense = $this->defense();

        $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.capa.store', $defense), ['answer' => 'not_ready'])
            ->assertOk();

        $this->assertSame(DefenseOutcome::CANCELLED, $defense->fresh()->outcome);
        $this->assertNotNull($defense->fresh()->schedule_date);
    }

    public function test_a_redefense_waits_for_a_date_with_its_earlier_try_on_record(): void
    {
        $first = $this->defense();
        $first->update(['schedule_date' => now()->subDay(), 'end_date' => now()->subDay()->addHours(2), 'outcome' => DefenseOutcome::REDEFENSE]);
        DefenseSchedule::create(['team_id' => $this->team->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => 2]);

        $row = $this->row();
        $this->assertSame('redefense', $row['waiting']);
        $this->assertSame('redefense', $row['tries'][0]['kind']);
    }

    /** Cancelled on the day for open requirements: the date stays on record, and the team waits for a new one. */
    public function test_a_defense_cancelled_on_the_day_waits_for_a_new_date(): void
    {
        $defense = $this->defense();
        $defense->update(['schedule_date' => now()->setTime(8, 0), 'end_date' => now()->setTime(10, 0)]);

        app(CapaGo::class)->cancel($defense->fresh(), 'Its requirements were not complete by the defense date', 'Signed Hearing Notice');

        $row = $this->row();
        $this->assertSame('cancelled', $row['waiting']);
        $this->assertSame(['kind' => 'cancelled', 'date' => today()->toDateString(), 'note' => 'Signed Hearing Notice', 'auto' => true], $row['tries'][0]);
        $this->assertSame(2, $row['attempt']);
        $this->assertSame(0, DefenseCancellation::count());
    }

    public function test_scheduling_a_title_defense_asks_the_adviser_for_the_go(): void
    {
        $defense = DefenseSchedule::create(['team_id' => $this->team->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => 1]);
        DefensePanel::create(['defense_id' => $defense->defense_id, 'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.schedule.update', $defense), ['schedule_date' => now()->addWeeks(2)->startOfWeek()->toDateString(), 'start_time' => '08:00', 'venue' => 'Mac Lab'])
            ->assertOk();

        $this->assertTrue(Notification::where('expert_id', $this->adviser->expert_id)->where('title', 'like', 'Is Alpha ready?%')->exists());
    }
}
