<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\Deliverable;
use App\Models\ExpertRole;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsChecklists;
use Tests\TestCase;

/**
 * Panel comment 16: the CaPA alone decides whether a team is ready, any time
 * before or on the defense day. Nothing cancels a defense by itself, not even
 * one whose requirements are still open on the day.
 */
class CapaDecidesReadinessTest extends TestCase
{
    use BuildsChecklists;
    use RefreshDatabase;

    private CapstoneExpert $capsa;

    private CapstoneExpert $adviser;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->capsa = $this->capsaFor(['BSIT-3A']);
        // An open requirement: the old day-of check would have cancelled on it.
        $checklist = $this->emptyChecklist($this->capsa);
        $this->addDeliverable($checklist, ['title' => 'Signed Hearing Notice']);
        $this->addDeliverable($checklist, ['title' => 'Title Defense', 'kind' => Deliverable::DEFENSE, 'defense_type' => DefenseOutcome::TITLE_DEFENSE]);
        [$this->team] = $this->teamInSection('Alpha');

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

    private function defenseAt(Carbon $at): DefenseSchedule
    {
        $schedule = DefenseSchedule::create([
            'team_id' => $this->team->team_id, 'defense_type' => DefenseOutcome::TITLE_DEFENSE, 'attempt' => 1,
            'schedule_date' => $at, 'end_date' => $at->copy()->addHours(2), 'venue' => 'Mac Lab',
        ]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        return $schedule;
    }

    private function todo(): TestResponse
    {
        return $this->actingAs($this->adviser, 'expert')->get(route('instructor.teams', ['tab' => 'todo']))->assertOk();
    }

    public function test_there_is_no_automatic_cancel_command_or_schedule(): void
    {
        $this->assertArrayNotHasKey('defenses:cancel-unready', Artisan::all());

        $this->artisan('schedule:list')->doesntExpectOutputToContain('cancel-unready')->assertSuccessful();
    }

    public function test_a_defense_today_with_open_requirements_stays_scheduled(): void
    {
        $this->travelTo(today()->setTime(0, 10));
        $defense = $this->defenseAt(today()->setTime(13, 0));

        $this->artisan('schedule:run')->assertSuccessful();
        $this->travelTo(today()->setTime(13, 30));
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertNull($defense->fresh()->outcome);
        $this->assertSame(1, DefenseSchedule::count());
    }

    public function test_the_capa_gives_the_go_on_the_day_after_the_start_time(): void
    {
        $this->travelTo(today()->setTime(14, 0));
        $defense = $this->defenseAt(today()->setTime(8, 0));

        $this->todo()->assertSee('Defenses waiting for your go')->assertSee('Alpha');

        $this->actingAs($this->adviser, 'expert')
            ->patchJson(route('instructor.defense-go.update', $defense), ['answer' => 'go'])
            ->assertOk()->assertJson(['success' => true]);

        $this->assertSame(DefenseSchedule::CAPA_GO, $defense->fresh()->capa_answer);
        // Still there through the day, in case the team is not ready after all.
        $this->todo()->assertSee('Good for Title Defense')->assertSee('>Not ready, cancel</button>', false);
    }

    public function test_the_capsa_records_a_not_ready_on_the_day_and_the_next_try_opens(): void
    {
        $this->travelTo(today()->setTime(9, 0));
        $defense = $this->defenseAt(today()->setTime(8, 0));

        $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.capa.store', $defense), ['answer' => 'not_ready', 'note' => 'Chapter 3 is not done.'])
            ->assertOk();

        $defense->refresh();
        $this->assertSame(DefenseOutcome::CANCELLED, $defense->outcome);
        $this->assertSame('Chapter 3 is not done.', $defense->outcome_remarks);
        $this->assertSame(today()->setTime(8, 0)->toDateTimeString(), $defense->schedule_date->toDateTimeString());

        $next = DefenseSchedule::where('team_id', $this->team->team_id)->where('attempt', 2)->sole();
        $this->assertNull($next->schedule_date);
        $this->assertNull($next->outcome);
    }

    public function test_the_day_after_it_is_too_late_and_the_todo_lets_it_go(): void
    {
        $defense = $this->defenseAt(today()->subDay()->setTime(8, 0));

        $this->actingAs($this->adviser, 'expert')
            ->patchJson(route('instructor.defense-go.update', $defense), ['answer' => 'go'])
            ->assertStatus(422)->assertJson(['success' => false, 'message' => 'The day of this defense has passed.']);

        $this->assertNull($defense->fresh()->capa_answer);
        $this->todo()->assertDontSee('Defenses waiting for your go')->assertDontSee('Good for Title Defense');
    }
}
