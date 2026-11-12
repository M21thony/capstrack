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
use App\Support\DefenseOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A redefense is dated by hand: the team finds a time with its panel and
 * tells the CaPSA/TSA. Generate schedule leaves it out.
 */
class RedefenseInScheduleTest extends TestCase
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

    private function team(string $name): Team
    {
        $team = Team::create(['team_name' => $name, 'project_title' => $name.' Title', 'team_status' => 'Active', 'is_confirmed' => true]);
        $member = Student::factory()->withCompleteProfile()->create();
        StudentSchoolYear::create(['student_id' => $member->student_id, 'team_id' => $team->team_id]);

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

    /** The try in play, with a panel and no date yet. Earlier tries ended in a redefense. */
    private function attempt(Team $team, int $attempt): DefenseSchedule
    {
        for ($i = 1; $i < $attempt; $i++) {
            DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => self::STAGE, 'attempt' => $i, 'schedule_date' => '2027-02-0'.$i.' 08:00:00', 'venue' => 'Mac Lab', 'outcome' => DefenseOutcome::REDEFENSE]);
        }

        $schedule = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => self::STAGE, 'attempt' => $attempt]);
        DefensePanel::create(['defense_id' => $schedule->defense_id, 'expert_id' => CapstoneExpert::factory()->create(['approved_status' => true])->expert_id, 'panel_role' => 'Panel Chair', 'created_at' => now()]);

        return $schedule;
    }

    private function draft()
    {
        return collect($this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.schedule-drafts.store'), ['defense_type' => self::STAGE])
            ->assertOk()->json('data.draft'))->keyBy('team_name');
    }

    private function schedule(DefenseSchedule $schedule, string $date, string $time)
    {
        return $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.schedule.update', $schedule), ['schedule_date' => $date, 'start_time' => $time, 'venue' => 'Mac Lab']);
    }

    public function test_generate_leaves_a_redefense_out_and_says_why(): void
    {
        $this->attempt($this->team('Fresh'), 1);
        $this->attempt($this->team('Again'), 2);

        $data = $this->actingAs($this->capsa, 'expert')
            ->postJson(route('api.admin.panel_assignment.schedule-drafts.store'), ['defense_type' => self::STAGE])
            ->assertOk()->json('data');

        $this->assertSame(['Fresh'], array_column($data['draft'], 'team_name'));
        $this->assertSame(['Again'], array_column($data['not_ready'], 'team_name'));
        $this->assertSame('Redefense, scheduled by hand.', $data['not_ready'][0]['reason']);
    }

    /** The team arranged it with its panel: outside the window, off the slot grid. */
    public function test_a_redefense_is_dated_by_hand_at_any_weekday_time(): void
    {
        $current = $this->attempt($this->team('Again'), 2);

        $this->schedule($current, '2027-04-07', '15:30')->assertOk();

        $this->assertSame('2027-04-07 15:30', $current->fresh()->schedule_date->format('Y-m-d H:i'));
    }

    public function test_a_first_try_still_needs_a_slot_time(): void
    {
        $this->schedule($this->attempt($this->team('Fresh'), 1), '2027-03-15', '15:30')->assertStatus(422);
    }
}
