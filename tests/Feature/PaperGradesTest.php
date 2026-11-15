<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\PanelGrade;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The panel grades on paper. The section's CaPSA/TSA types each sheet's total on
 * Team Management's Grades tab and attaches the signed sheets as one PDF; the
 * Panel Chair sets who grades (Individual, Joint, Mixed) from Grading setup.
 */
class PaperGradesTest extends TestCase
{
    use RefreshDatabase;

    private DefenseSchedule $defense;

    private Team $team;

    private CapstoneExpert $capsa;

    private int $criterionId;

    /** @var array{chair: CapstoneExpert, one: CapstoneExpert, two: CapstoneExpert} */
    private array $panel;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $leader = Student::factory()->withCompleteProfile()->create([
            'email' => 'leader.paper@my.cspc.edu.ph',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '3rd · A',
        ]);
        $this->team = Team::create([
            'team_name' => 'Paper Team', 'project_title' => 'Ink and Sheets',
            'team_status' => 'Active', 'leader_id' => $leader->student_id, 'member_count' => 5,
        ]);
        StudentSchoolYear::create(['student_id' => $leader->student_id, 'team_id' => $this->team->team_id]);

        $this->capsa = $this->makeCapsa(CapstoneExpert::factory()->create([
            'approved_status' => true, 'sections' => ['BSIT-3A'], 'first_name' => 'Cap', 'last_name' => 'Sa',
        ]));

        $this->defense = $this->defenseFor($this->team);

        foreach (['chair' => 'Panel Chair', 'one' => 'Panel Member 1', 'two' => 'Panel Member 2'] as $key => $role) {
            $expert = CapstoneExpert::create([
                'email' => $key.'.paper@gmail.com',
                'first_name' => ucfirst($key),
                'last_name' => 'Panelist',
                'approved_status' => true,
            ]);
            $this->seat($this->defense, $expert, $role);
            $this->panel[$key] = $expert;
        }

        $rubric = GradingRubric::create([
            'rubric_name' => 'Paper Rubric',
            'rubric_type' => 'Pre-proposal Defense',
            'total_points' => 100,
            'is_active' => true,
        ]);
        $this->criterionId = $rubric->criteria()->create([
            'criterion_name' => 'Everything',
            'category' => 'All',
            'category_max_points' => 100,
            'max_points' => 100,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 100, 'desc' => 'Great'],
                ['label' => 'Good', 'score' => 80, 'desc' => 'Good'],
            ]),
            'sort_order' => 0,
        ])->criterion_id;
    }

    private function defenseFor(Team $team): DefenseSchedule
    {
        return DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Pre-proposal Defense',
            'schedule_date' => now()->subHour(),
        ]);
    }

    private function seat(DefenseSchedule $defense, CapstoneExpert $expert, string $role): void
    {
        DefensePanel::create([
            'defense_id' => $defense->defense_id,
            'expert_id' => $expert->expert_id,
            'panel_role' => $role,
            'created_at' => now(),
        ]);
    }

    private function id(string $who): int
    {
        return (int) $this->panel[$who]->expert_id;
    }

    /** @param  array<string, float|int|null>  $totals */
    private function encode(array $totals, bool $submit = false)
    {
        $keyed = [];
        foreach ($totals as $who => $total) {
            $keyed[$this->id($who)] = $total;
        }

        return $this->actingAs($this->capsa, 'expert')
            ->postJson('/capsa/grades/'.$this->defense->defense_id, ['totals' => $keyed, 'submit' => $submit]);
    }

    private function attachSheet()
    {
        return $this->actingAs($this->capsa, 'expert')->post('/capsa/grades/'.$this->defense->defense_id.'/sheet', [
            'sheet' => UploadedFile::fake()->create('sheets.pdf', 50, 'application/pdf'),
        ], ['Accept' => 'application/json']);
    }

    /** A panelist scores and submits on their own laptop. */
    private function submitOwn(string $who, DefenseSchedule $defense, int $score)
    {
        return $this->actingAs($this->panel[$who], 'expert')
            ->patchJson('/instructor/grading/'.$defense->defense_id, ['scores' => [$this->criterionId => $score], 'remarks' => 'Tighten chapter 2.']);
    }

    private function gradeOf(string $who): ?PanelGrade
    {
        return PanelGrade::where('defense_id', $this->defense->defense_id)->where('expert_id', $this->id($who))->first();
    }

    public function test_scores_typed_by_category_add_up_to_the_total(): void
    {
        GradingRubric::first()->criteria()->create([
            'criterion_name' => 'Delivery', 'category' => 'Oral Presentation', 'category_max_points' => 40,
            'max_points' => 40, 'score_levels' => '[]', 'sort_order' => 1,
        ]);
        $send = fn (array $scores) => $this->actingAs($this->capsa, 'expert')->postJson('/capsa/grades/'.$this->defense->defense_id, [
            'totals' => [$this->id('one') => 5], 'categories' => [$this->id('one') => $scores],
        ]);

        $send([60, 41])->assertStatus(422);
        $this->assertNull($this->gradeOf('one'));

        $send([60, 30])->assertOk();
        $this->assertEquals(90, (float) $this->gradeOf('one')->total_score);
        $this->assertEquals(['All' => 60, 'Oral Presentation' => 30], $this->gradeOf('one')->scores['categories']);
    }

    public function test_capsa_saves_drafts_then_submits_without_waiting_for_the_signed_sheets(): void
    {
        $this->encode(['chair' => 90, 'one' => 90])->assertOk()->assertJson(['message' => 'Drafts saved.']);
        $this->assertSame(PanelGrade::DRAFT, $this->gradeOf('one')->status);
        $this->assertNull($this->gradeOf('two'));
        $this->assertFalse($this->defense->fresh()->allGradesIn());

        $this->encode(['chair' => 90, 'one' => 90], true)
            ->assertStatus(422)->assertJson(['message' => 'Type every total before submitting.']);

        $this->encode(['chair' => 90, 'one' => 90, 'two' => 84], true)->assertOk();

        foreach (['chair' => 90, 'one' => 90, 'two' => 84] as $who => $total) {
            $grade = $this->gradeOf($who);
            $this->assertSame(PanelGrade::SUBMITTED, $grade->status);
            $this->assertEquals($total, (float) $grade->total_score);
            $this->assertTrue($grade->isFromPaper());
        }
        $this->assertEquals(88, $this->defense->fresh()->panelAverage());
        // The chair still records the decision.
        $this->assertNull($this->defense->fresh()->outcome);
    }

    public function test_a_total_out_of_range_saves_nothing(): void
    {
        $this->encode(['chair' => 120])
            ->assertStatus(422)->assertJson(['message' => "A total cannot be more than the rubric's 100."]);
        $this->encode(['chair' => -5])->assertStatus(422);

        $this->assertSame(0, PanelGrade::count());
    }

    public function test_a_grade_the_panelist_turned_in_is_never_typed_over(): void
    {
        $this->submitOwn('one', $this->defense, 80)->assertOk();

        $this->attachSheet()->assertOk();
        $this->encode(['chair' => 92, 'one' => 10, 'two' => 70], true)->assertOk();

        $one = $this->gradeOf('one');
        $this->assertEquals(80, (float) $one->total_score);
        $this->assertSame('Tighten chapter 2.', $one->remarks);
        $this->assertSame(80.67, $this->defense->fresh()->panelAverage());
    }

    public function test_a_joint_grade_is_typed_once_and_counts_for_everyone(): void
    {
        $this->actingAs($this->panel['chair'], 'expert')->putJson('/instructor/grading/setup', [
            'defense_ids' => [$this->defense->defense_id], 'method' => 'joint', 'recorder' => 'Panel Chair',
        ])->assertOk()->assertJsonPath('data.updated', 1);

        $this->attachSheet()->assertOk();
        $this->encode(['chair' => 86], true)->assertOk();

        foreach (['chair', 'one', 'two'] as $who) {
            $this->assertEquals(86, (float) $this->gradeOf($who)->total_score, $who);
        }
        $this->assertEquals(86, $this->defense->fresh()->panelAverage());
    }

    public function test_a_capsa_outside_the_section_cannot_touch_the_grades(): void
    {
        $other = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSCS-1A']]));

        $this->actingAs($other, 'expert')
            ->postJson('/capsa/grades/'.$this->defense->defense_id, ['totals' => [$this->id('chair') => 90]])
            ->assertForbidden();
        $this->actingAs($other, 'expert')
            ->getJson('/capsa/grades/'.$this->defense->defense_id.'/sheet')->assertForbidden();

        $this->assertSame(0, PanelGrade::count());
    }

    public function test_the_signed_sheets_must_be_a_pdf_and_can_be_opened(): void
    {
        $this->actingAs($this->capsa, 'expert')->post('/capsa/grades/'.$this->defense->defense_id.'/sheet', [
            'sheet' => UploadedFile::fake()->create('sheets.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJson(['message' => 'The signed sheets have to be one PDF.']);

        $this->attachSheet()->assertOk();
        $path = $this->defense->fresh()->signed_sheets_path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->capsa, 'expert')->get('/capsa/grades/'.$this->defense->defense_id.'/sheet')
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Replacing it removes the old file.
        $this->attachSheet()->assertOk();
        Storage::disk('local')->assertMissing($path);
    }

    public function test_only_the_chair_sets_up_grading_and_started_teams_are_skipped(): void
    {
        // A second defense the same chair also chairs, where grading has already started.
        $started = $this->defenseFor(Team::create(['team_name' => 'Started Team', 'project_title' => 'Already Going']));
        $this->seat($started, $this->panel['chair'], 'Panel Chair');
        $this->seat($started, $this->panel['one'], 'Panel Member 1');
        $this->seat($started, $this->panel['two'], 'Panel Member 2');
        $this->submitOwn('one', $started, 80)->assertOk();

        $this->actingAs($this->panel['one'], 'expert')->putJson('/instructor/grading/setup', [
            'defense_ids' => [$this->defense->defense_id], 'method' => 'joint', 'recorder' => 'Panel Chair',
        ])->assertStatus(422)->assertJsonPath('data.skipped.0.reason', 'You do not chair this defense.');

        $this->actingAs($this->panel['chair'], 'expert')->putJson('/instructor/grading/setup', [
            'defense_ids' => [$this->defense->defense_id, $started->defense_id],
            'method' => 'mixed', 'shared' => ['Panel Chair', 'Panel Member 1'], 'recorder' => 'Panel Member 1',
        ])->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped.0.reason', 'Grading has already started.');

        // Mixed: Member 1 grades for the chair; Member 2 grades alone.
        $this->assertSame($this->id('one'), (int) $this->gradeOf('chair')->graded_by_expert_id);
        $this->assertNull($this->gradeOf('two')?->graded_by_expert_id);
    }
}
