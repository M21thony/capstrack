<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

/**
 * A team can defend the same stage more than once.
 *
 * defense_schedule used to carry UNIQUE(team_id, defense_type), so there was
 * exactly one "Title Defense" row per team, ever. A redefense had nowhere to
 * go - and reusing the existing row destroyed the first attempt, because
 * panel_grades is keyed on (defense_id, expert_id) and written with
 * updateOrCreate. The failed attempt, and the panel's reasons for it,
 * silently vanished.
 */
class DefenseRedefenseTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeam(string $name = 'Defending Team'): Team
    {
        return Team::create([
            'team_name' => $name,
            'team_status' => 'Active',
            'is_confirmed' => true,
            'member_count' => 5,
        ]);
    }

    private function makePanelist(string $email): CapstoneExpert
    {
        return CapstoneExpert::factory()->create([
            'approved_status' => true,
            'email' => $email,
        ]);
    }

    public function test_a_team_can_have_a_second_attempt_at_the_same_stage(): void
    {
        $team = $this->makeTeam();

        $first = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'attempt' => 1,
        ]);

        $second = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'attempt' => 2,
        ]);

        $this->assertNotSame($first->defense_id, $second->defense_id);
        $this->assertSame(2, DefenseSchedule::where('team_id', $team->team_id)->count());
        $this->assertTrue($second->isRedefense());
        $this->assertFalse($first->isRedefense());
    }

    // The point of the whole change: the first attempt's scores, verdict and
    // remarks have to survive the second.
    public function test_a_redefense_does_not_overwrite_the_first_attempts_grades(): void
    {
        $team = $this->makeTeam();
        $chair = $this->makePanelist('chair@gmail.com');
        $rubric = GradingRubric::create([
            'rubric_name' => 'Title Defense Rubric',
            'rubric_type' => 'Title Defense',
            'total_points' => 100,
            'is_active' => true,
        ]);

        $first = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'attempt' => 1,
        ]);

        PanelGrade::create([
            'defense_id' => $first->defense_id,
            'expert_id' => $chair->expert_id,
            'rubric_id' => $rubric->rubric_id,
            'scores' => [],
            'total_score' => 71,
            'verdict' => 'revision',
            'remarks' => 'Scope is too wide for one semester.',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $second = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'attempt' => 2,
        ]);

        // The same panelist grades the redefense - which is exactly the case
        // that used to overwrite the first attempt in place.
        PanelGrade::create([
            'defense_id' => $second->defense_id,
            'expert_id' => $chair->expert_id,
            'rubric_id' => $rubric->rubric_id,
            'scores' => [],
            'total_score' => 88,
            'verdict' => 'approved',
            'remarks' => 'Scope now sensible.',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $firstGrade = PanelGrade::where('defense_id', $first->defense_id)->first();
        $secondGrade = PanelGrade::where('defense_id', $second->defense_id)->first();

        $this->assertSame('revision', $firstGrade->verdict, 'the first attempt must survive the redefense');
        $this->assertSame('Scope is too wide for one semester.', $firstGrade->remarks);
        $this->assertSame(71.0, (float) $firstGrade->total_score);

        $this->assertSame('approved', $secondGrade->verdict);
        $this->assertSame(88.0, (float) $secondGrade->total_score);
    }

    // Each attempt gets its own panel, so a replaced panelist on the
    // redefense does not rewrite who sat on the first one.
    public function test_each_attempt_carries_its_own_panel(): void
    {
        $team = $this->makeTeam();
        $original = $this->makePanelist('original.chair@gmail.com');
        $replacement = $this->makePanelist('replacement.chair@gmail.com');

        $first = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'attempt' => 1,
        ]);
        DefensePanel::create([
            'defense_id' => $first->defense_id,
            'expert_id' => $original->expert_id,
            'panel_role' => 'Panel Chair',
            'created_at' => now(),
        ]);

        $second = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'attempt' => 2,
        ]);
        DefensePanel::create([
            'defense_id' => $second->defense_id,
            'expert_id' => $replacement->expert_id,
            'panel_role' => 'Panel Chair',
            'created_at' => now(),
        ]);

        $this->assertSame($original->expert_id, $first->panels()->first()->expert_id);
        $this->assertSame($replacement->expert_id, $second->panels()->first()->expert_id);
    }

    // The pair still identifies one row per try - a duplicate attempt number
    // is a mistake, not a third defense.
    public function test_the_same_attempt_cannot_be_recorded_twice(): void
    {
        $team = $this->makeTeam();

        DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'attempt' => 1,
        ]);

        $this->expectException(QueryException::class);

        DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Title Defense',
            'attempt' => 1,
        ]);
    }

    public function test_existing_schedules_default_to_the_first_attempt(): void
    {
        $team = $this->makeTeam();

        $schedule = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Pre-proposal Defense',
        ]);

        $this->assertSame(1, (int) $schedule->fresh()->attempt);
    }

    // Everything that used to ask for "this team's Title Defense" means the
    // try that is currently in play.
    public function test_the_latest_attempt_scope_returns_the_current_try(): void
    {
        $team = $this->makeTeam();

        foreach ([1, 2, 3] as $attempt) {
            DefenseSchedule::create([
                'team_id' => $team->team_id,
                'defense_type' => 'Title Defense',
                'attempt' => $attempt,
            ]);
        }

        $current = DefenseSchedule::where('team_id', $team->team_id)
            ->where('defense_type', 'Title Defense')
            ->latestAttempt()
            ->first();

        $this->assertSame(3, (int) $current->attempt);
    }
}
