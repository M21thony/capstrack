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
 * Panel comment 18: teams up for a redefense go into the same Generate
 * schedule as everyone else, and the draft says which try it is.
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

    public function test_a_redefense_team_is_in_the_draft_as_try_two(): void
    {
        $this->attempt($this->team('Again'), 2);

        $this->assertSame(2, $this->draft()['Again']['attempt']);
    }

    public function test_a_first_try_team_is_in_the_draft_as_try_one(): void
    {
        $this->attempt($this->team('Fresh'), 1);

        $this->assertSame(1, $this->draft()['Fresh']['attempt']);
    }

    public function test_redefense_and_first_try_teams_share_one_draft(): void
    {
        $this->attempt($this->team('Fresh'), 1);
        $this->attempt($this->team('Again'), 2);

        $draft = $this->draft();

        $this->assertEqualsCanonicalizing(['Fresh', 'Again'], $draft->keys()->all());
        $this->assertNotSame($draft['Fresh']['date'].$draft['Fresh']['start_time'].$draft['Fresh']['venue'], $draft['Again']['date'].$draft['Again']['start_time'].$draft['Again']['venue']);
    }

    public function test_a_third_try_is_labelled_try_three(): void
    {
        $this->attempt($this->team('Third'), 3);

        $row = $this->draft()['Third'];

        $this->assertSame(3, $row['attempt']);
        $this->assertSame(DefenseSchedule::where('attempt', 3)->value('defense_id'), $row['defense_id']);
    }

    public function test_saving_a_redefense_dates_the_new_try_and_leaves_the_old_one(): void
    {
        $team = $this->team('Again');
        $current = $this->attempt($team, 2);
        $row = $this->draft()['Again'];

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('api.admin.panel_assignment.schedules.update'), ['defense_type' => self::STAGE, 'items' => [['defense_id' => $row['defense_id'], 'date' => $row['date'], 'start_time' => $row['start_time'], 'venue' => $row['venue']]]])
            ->assertOk();

        $this->assertSame($row['date'], $current->fresh()->schedule_date->toDateString());
        $this->assertSame('2027-02-01', DefenseSchedule::where('team_id', $team->team_id)->where('attempt', 1)->first()->schedule_date->toDateString());
    }
}
