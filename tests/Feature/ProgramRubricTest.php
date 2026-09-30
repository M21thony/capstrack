<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Support\DefenseBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A rubric can be written for one program. A team is graded with its own
 * program's rubric when there is one, and with the stage's rubric for every
 * program otherwise.
 */
class ProgramRubricTest extends TestCase
{
    use RefreshDatabase;

    private const STAGE = 'Title Defense';

    private function makeAdmin(): CapstoneExpert
    {
        return $this->makeCapsa(CapstoneExpert::create([
            'email' => 'admin.program@test.com',
            'first_name' => 'Admin',
            'last_name' => 'Program',
            'is_admin' => true,
            'approved_status' => true,
        ]));
    }

    private function makeRubric(string $name, ?string $program, string $type = self::STAGE): GradingRubric
    {
        $rubric = GradingRubric::create([
            'rubric_name' => $name,
            'rubric_type' => $type,
            'program' => $program,
            'total_points' => 100,
            'is_active' => true,
        ]);

        $rubric->criteria()->create([
            'criterion_name' => $name.' Criterion',
            'max_points' => 100,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 100, 'desc' => 'Great'],
                ['label' => 'Good', 'score' => 75, 'desc' => 'Good'],
                ['label' => 'Fair', 'score' => 50, 'desc' => 'Fair'],
                ['label' => 'Poor', 'score' => 25, 'desc' => 'Poor'],
            ]),
            'sort_order' => 0,
        ]);

        return $rubric;
    }

    private function makeTeam(?string $course, ?string $enrolmentProgram = null): Team
    {
        $team = Team::create(['team_name' => 'Team '.($course ?? 'None'), 'team_status' => 'Active']);
        $student = Student::factory()->withCompleteProfile()->create(['course' => $course ?? '']);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $team->team_id, 'program' => $enrolmentProgram]);

        return $team;
    }

    /** @return array{0: CapstoneExpert, 1: DefenseSchedule} */
    private function panelistFor(Team $team): array
    {
        $expert = CapstoneExpert::create([
            'email' => 'panel'.$team->team_id.'@test.com',
            'first_name' => 'Panel',
            'last_name' => 'Ist',
            'approved_status' => true,
        ]);

        $defense = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => self::STAGE,
            'schedule_date' => now()->addDay()->setTime(8, 0),
        ]);

        DefensePanel::create([
            'defense_id' => $defense->defense_id,
            'expert_id' => $expert->expert_id,
            'panel_role' => 'Panel Chair',
            'created_at' => now(),
        ]);

        return [$expert, $defense];
    }

    public function test_a_rubric_can_be_saved_for_one_program(): void
    {
        $this->actingAs($this->makeAdmin(), 'expert')
            ->postJson('/api/admin/grading-rubrics', [
                'rubric_name' => 'BSIT Title Defense',
                'rubric_type' => 'title_defense',
                'program' => 'BSIT',
            ])
            ->assertCreated()
            ->assertJsonPath('data.program', 'BSIT');

        $this->assertDatabaseHas('grading_rubrics', ['rubric_name' => 'BSIT Title Defense', 'program' => 'BSIT']);
    }

    public function test_a_rubric_without_a_program_is_for_every_program(): void
    {
        $this->actingAs($this->makeAdmin(), 'expert')
            ->postJson('/api/admin/grading-rubrics', [
                'rubric_name' => 'Everyone',
                'rubric_type' => 'title_defense',
                'program' => '',
            ])
            ->assertCreated()
            ->assertJsonPath('data.program', null);
    }

    public function test_an_unknown_program_is_refused(): void
    {
        $this->actingAs($this->makeAdmin(), 'expert')
            ->postJson('/api/admin/grading-rubrics', [
                'rubric_name' => 'Nursing',
                'rubric_type' => 'title_defense',
                'program' => 'BSN',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['program']);
    }

    public function test_editing_a_rubric_can_change_or_clear_its_program(): void
    {
        $admin = $this->makeAdmin();
        $rubric = $this->makeRubric('Movable', 'BSIT');

        $this->actingAs($admin, 'expert')
            ->putJson('/api/admin/grading-rubrics/'.$rubric->rubric_id, [
                'rubric_name' => 'Movable', 'rubric_type' => 'title_defense', 'program' => 'BSCS',
            ])
            ->assertOk()
            ->assertJsonPath('data.program', 'BSCS');

        $this->actingAs($admin, 'expert')
            ->putJson('/api/admin/grading-rubrics/'.$rubric->rubric_id, [
                'rubric_name' => 'Movable', 'rubric_type' => 'title_defense',
            ])
            ->assertOk()
            ->assertJsonPath('data.program', null);
    }

    public function test_the_list_says_which_program_a_rubric_is_for(): void
    {
        $this->makeRubric('BSIS Only', 'BSIS');

        $this->actingAs($this->makeAdmin(), 'expert')
            ->getJson('/api/admin/grading-rubrics')
            ->assertOk()
            ->assertJsonPath('data.0.program', 'BSIS');
    }

    public function test_the_rubric_form_offers_every_program(): void
    {
        $response = $this->actingAs($this->makeAdmin(), 'expert')
            ->get(route('capsa.rubric_management'))
            ->assertOk()
            ->assertSee('id="rb-program"', false)
            ->assertSee('<option value="">All programs</option>', false);

        foreach (CapstoneExpert::PROGRAMS as $program) {
            $response->assertSee('<option value="'.$program.'">', false);
        }
    }

    public function test_a_team_is_graded_with_its_own_programs_rubric(): void
    {
        $this->makeRubric('Every Program Rubric', null);
        $this->makeRubric('BSIT Rubric', 'BSIT');
        $this->makeRubric('BSCS Rubric', 'BSCS');
        [$expert, $defense] = $this->panelistFor($this->makeTeam('Bachelor of Science in Information Technology'));

        $this->actingAs($expert, 'expert')
            ->get('/instructor/grading/'.$defense->defense_id)
            ->assertOk()
            ->assertSee('BSIT Rubric Criterion')
            ->assertDontSee('BSCS Rubric Criterion')
            ->assertDontSee('Every Program Rubric Criterion');
    }

    public function test_a_program_without_its_own_rubric_uses_the_one_for_every_program(): void
    {
        $this->makeRubric('Every Program Rubric', null);
        $this->makeRubric('BSIT Rubric', 'BSIT');
        [$expert, $defense] = $this->panelistFor($this->makeTeam('BSCS'));

        $this->actingAs($expert, 'expert')
            ->get('/instructor/grading/'.$defense->defense_id)
            ->assertOk()
            ->assertSee('Every Program Rubric Criterion')
            ->assertDontSee('BSIT Rubric Criterion');
    }

    public function test_another_programs_rubric_is_never_used(): void
    {
        $this->makeRubric('BSIT Rubric', 'BSIT');
        [$expert, $defense] = $this->panelistFor($this->makeTeam('BSCS'));

        $this->actingAs($expert, 'expert')
            ->get('/instructor/grading/'.$defense->defense_id)
            ->assertRedirect(route('instructor.grading'));

        $this->actingAs($expert, 'expert')
            ->patchJson('/instructor/grading/'.$defense->defense_id, ['scores' => []])
            ->assertStatus(422);
    }

    public function test_a_team_with_no_known_program_uses_the_one_for_every_program(): void
    {
        $this->makeRubric('Every Program Rubric', null);
        $this->makeRubric('BSIT Rubric', 'BSIT');
        [$expert, $defense] = $this->panelistFor($this->makeTeam(null));

        $this->actingAs($expert, 'expert')
            ->get('/instructor/grading/'.$defense->defense_id)
            ->assertOk()
            ->assertSee('Every Program Rubric Criterion');
    }

    public function test_an_archived_program_rubric_falls_back_to_the_one_for_every_program(): void
    {
        $this->makeRubric('Every Program Rubric', null);
        $this->makeRubric('BSIT Rubric', 'BSIT')->update(['is_archived' => true]);
        [$expert, $defense] = $this->panelistFor($this->makeTeam('BSIT'));

        $this->actingAs($expert, 'expert')
            ->get('/instructor/grading/'.$defense->defense_id)
            ->assertOk()
            ->assertSee('Every Program Rubric Criterion')
            ->assertDontSee('BSIT Rubric Criterion');
    }

    public function test_a_teams_program_comes_from_its_members_course(): void
    {
        $this->assertSame('BSIT', $this->makeTeam('Bachelor of Science in Information Technology')->program());
        $this->assertSame('BLIS', $this->makeTeam('blis')->program());
    }

    public function test_a_teams_program_falls_back_to_the_enrolment_row(): void
    {
        $this->assertSame('BSIS', $this->makeTeam(null, 'BSIS')->program());
        $this->assertNull($this->makeTeam(null)->program());
    }

    public function test_the_board_names_programs_left_without_a_rubric(): void
    {
        $this->makeRubric('BSIT Rubric', 'BSIT');

        $this->assertSame(['BSCS'], DefenseBoard::programsWithoutRubric(self::STAGE, ['BSIT', 'BSCS']));
        $this->assertFalse(DefenseBoard::hasRubric(self::STAGE, ['BSIT', 'BSCS']));
        $this->assertTrue(DefenseBoard::hasRubric(self::STAGE, ['BSIT']));
        // Not scoped to any program: any rubric for the stage will do.
        $this->assertTrue(DefenseBoard::hasRubric(self::STAGE));
    }

    public function test_a_rubric_for_every_program_covers_them_all(): void
    {
        $this->makeRubric('Every Program Rubric', null);

        $this->assertSame([], DefenseBoard::programsWithoutRubric(self::STAGE, ['BSIT', 'BSCS']));
        $this->assertTrue(DefenseBoard::hasRubric(self::STAGE, ['BSIT', 'BSCS']));
    }

    public function test_no_rubric_at_all_leaves_every_program_uncovered(): void
    {
        $this->assertSame(['BSIT', 'BSCS'], DefenseBoard::programsWithoutRubric(self::STAGE, ['BSIT', 'BSCS']));
        $this->assertFalse(DefenseBoard::hasRubric(self::STAGE));
    }

    public function test_the_scheduling_board_reports_the_capsas_uncovered_programs(): void
    {
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true, 'sections' => ['BSIT-4A', 'BSCS-4A'],
        ]));
        $this->makeRubric('BSIT Rubric', 'BSIT');

        $this->actingAs($capsa, 'expert')
            ->getJson(route('api.admin.panel_assignment.board', ['defense_type' => self::STAGE]))
            ->assertOk()
            ->assertJsonPath('data.has_rubric', false)
            ->assertJsonPath('data.rubric_missing_programs', ['BSCS']);
    }
}
