<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\ExpertRole;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamExpertAssignment;
use App\Support\DefenseScores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A team's defense score: every panelist's total added up and divided by the
 * panel (65 + 85 + 75, over 3), seen by the team, their section's CaPSA/TSA and
 * their adviser.
 */
class DefenseScoresTest extends TestCase
{
    use RefreshDatabase;

    private function team(): Team
    {
        $leader = Student::factory()->withCompleteProfile()->create([
            'email' => 'lead@my.cspc.edu.ph', 'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);
        $team = Team::create(['team_name' => 'Scored Team', 'leader_id' => $leader->student_id, 'team_status' => 'Active']);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $team->team_id]);

        return $team;
    }

    /** @param  array<int, float|null>  $scores  one per panelist; null = not submitted */
    private function defense(Team $team, array $scores, string $type = 'Title Defense', int $attempt = 1): DefenseSchedule
    {
        $rubric = GradingRubric::create(['rubric_name' => 'R', 'rubric_type' => $type, 'total_points' => 100, 'is_active' => true]);
        $schedule = DefenseSchedule::create(['team_id' => $team->team_id, 'defense_type' => $type, 'attempt' => $attempt]);

        foreach (array_values($scores) as $i => $score) {
            $panelist = CapstoneExpert::factory()->create(['approved_status' => true]);
            DefensePanel::create([
                'defense_id' => $schedule->defense_id, 'expert_id' => $panelist->expert_id,
                'panel_role' => $i === 0 ? 'Panel Chair' : 'Panel Member '.$i, 'created_at' => now(),
            ]);

            if ($score !== null) {
                PanelGrade::create([
                    'defense_id' => $schedule->defense_id, 'expert_id' => $panelist->expert_id, 'rubric_id' => $rubric->rubric_id,
                    'scores' => [], 'total_score' => $score, 'status' => 'submitted', 'submitted_at' => now(),
                ]);
            }
        }

        return $schedule;
    }

    public function test_the_score_is_the_panel_total_over_three(): void
    {
        $team = $this->team();
        $this->defense($team, [65, 85, 75]);

        $score = DefenseScores::forTeams([$team->team_id])->get($team->team_id)->first();

        $this->assertSame(75.0, $score->average);
        $this->assertSame('Title', $score->label);
    }

    /** Two of three in is not a score yet - it would move when the third arrives. */
    public function test_no_score_until_the_whole_panel_has_graded(): void
    {
        $team = $this->team();
        $this->defense($team, [65, 85, null]);

        $score = DefenseScores::forTeams([$team->team_id])->get($team->team_id)->first();

        $this->assertNull($score->average);
        $this->assertSame(2, $score->graded);
        $this->assertSame(3, $score->panel_size);
    }

    /**
     * A failed attempt is an auto-fail in this flow - its score says nothing
     * the redefense does not - so only the latest attempt of a stage is
     * listed, and the older rows do not repeat the column.
     */
    public function test_only_the_latest_attempt_of_a_stage_is_listed(): void
    {
        $team = $this->team();
        $this->defense($team, [60, 60, 60], 'Title Defense');
        $this->defense($team, [80, 80, 80], 'Title Defense', 2);

        $rows = DefenseScores::forTeams([$team->team_id])->get($team->team_id);

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows->first()->attempt);
        $this->assertSame(80.0, $rows->first()->average);
    }

    public function test_every_stage_is_listed_in_order(): void
    {
        $team = $this->team();
        $this->defense($team, [80, 80, 80], 'Title Defense');
        $this->defense($team, [90, 90, 90], 'Pre-proposal Defense');

        $labels = DefenseScores::forTeams([$team->team_id])->get($team->team_id)->pluck('label')->all();

        $this->assertSame(['Pre-Proposal', 'Title'], $labels);
    }

    public function test_the_sections_capsa_sees_it_on_team_management(): void
    {
        $team = $this->team();
        $this->defense($team, [65, 85, 75]);

        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]));

        $this->actingAs($capsa, 'expert')
            ->get(route('capsa.team_management', ['tab' => 'teams']))
            ->assertOk()
            ->assertSee('aria-label="Defense scores"', false)
            ->assertSeeInOrder(['Scored Team', '75', 'Title']);
    }

    public function test_the_adviser_sees_it_and_a_consultant_does_not(): void
    {
        $team = $this->team();
        $this->defense($team, [65, 85, 74]);

        $engage = function (string $roleName) use ($team): CapstoneExpert {
            $expert = CapstoneExpert::factory()->create(['approved_status' => true]);
            $role = Role::firstOrCreate(['role_name' => $roleName]);
            $expert->roles()->attach($role->role_id);

            TeamExpertAssignment::create([
                'team_id' => $team->team_id, 'expert_id' => $expert->expert_id, 'status' => 'approved',
                'expert_role_id' => ExpertRole::where('expert_id', $expert->expert_id)->where('role_id', $role->role_id)->value('expert_role_id'),
            ]);

            return $expert;
        };

        $adviser = $engage('adviser');
        $consultant = $engage('consultant');

        $this->actingAs($adviser, 'expert')->get(route('instructor.teams'))
            ->assertOk()
            ->assertSee('aria-label="Defense scores"', false)
            ->assertSee('74.67');

        $this->actingAs($consultant, 'expert')->get(route('instructor.teams'))
            ->assertOk()
            ->assertDontSee('74.67');
    }
}
