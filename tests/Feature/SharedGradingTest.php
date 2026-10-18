<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\DefensePanel;
use App\Models\DefenseSchedule;
use App\Models\GradingRubric;
use App\Models\Notification;
use App\Models\PanelGrade;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One panelist grading for others: the rest join that grade - not everyone
 * brings a laptop - and each joined panelist's grade is a copy of it.
 */
class SharedGradingTest extends TestCase
{
    use RefreshDatabase;

    private DefenseSchedule $defense;

    private GradingRubric $rubric;

    /** @var array{chair: CapstoneExpert, one: CapstoneExpert, two: CapstoneExpert} */
    private array $panel;

    protected function setUp(): void
    {
        parent::setUp();

        $team = Team::create(['team_name' => 'Shared Team', 'project_title' => 'One Sheet']);
        $this->defense = DefenseSchedule::create([
            'team_id' => $team->team_id,
            'defense_type' => 'Pre-proposal Defense',
            'schedule_date' => now()->addDay(),
        ]);

        $roles = ['chair' => 'Panel Chair', 'one' => 'Panel Member 1', 'two' => 'Panel Member 2'];
        foreach ($roles as $key => $role) {
            $expert = CapstoneExpert::create([
                'email' => $key.'.panel@gmail.com',
                'first_name' => ucfirst($key),
                'last_name' => 'Panelist',
                'approved_status' => true,
            ]);
            DefensePanel::create([
                'defense_id' => $this->defense->defense_id,
                'expert_id' => $expert->expert_id,
                'panel_role' => $role,
                'created_at' => now(),
            ]);
            $this->panel[$key] = $expert;
        }

        $this->rubric = GradingRubric::create([
            'rubric_name' => 'Shared Rubric',
            'rubric_type' => 'Pre-proposal Defense',
            'total_points' => 100,
            'is_active' => true,
        ]);
        $this->rubric->criteria()->create([
            'criterion_name' => 'Everything',
            'category' => 'All',
            'category_max_points' => 100,
            'max_points' => 100,
            'score_levels' => json_encode([
                ['label' => 'Excellent', 'score' => 100, 'desc' => 'Great'],
                ['label' => 'Good', 'score' => 80, 'desc' => 'Good'],
                ['label' => 'Fair', 'score' => 70, 'desc' => 'Fair'],
            ]),
            'sort_order' => 0,
        ]);
    }

    private function id(string $who): int
    {
        return (int) $this->panel[$who]->expert_id;
    }

    private function url(string $suffix = ''): string
    {
        return '/instructor/grading/'.$this->defense->defense_id.$suffix;
    }

    /** @param  list<string>  $joined */
    private function setGrader(string $by, ?string $grader, array $joined = [])
    {
        return $this->actingAs($this->panel[$by], 'expert')->putJson($this->url('/grader'), [
            'grader_id' => $grader === null ? null : $this->id($grader),
            'joined' => array_map(fn ($w) => $this->id($w), $joined),
        ]);
    }

    private function grade(string $who, float $score, string $method = 'patchJson', string $remarks = 'Notes')
    {
        $criterion = $this->rubric->criteria()->first();

        return $this->actingAs($this->panel[$who], 'expert')->{$method}($this->url(), [
            'scores' => [$criterion->criterion_id => $score],
            'remarks' => $remarks,
        ]);
    }

    private function gradeOf(string $who): ?PanelGrade
    {
        return PanelGrade::where('defense_id', $this->defense->defense_id)->where('expert_id', $this->id($who))->first();
    }

    public function test_one_panelist_grades_for_the_whole_panel(): void
    {
        $this->setGrader('chair', 'one', ['chair', 'two'])->assertOk()
            ->assertJsonPath('data.grader', $this->id('one'));

        $this->grade('one', 80, 'putJson', 'Fix the scope')->assertOk();

        foreach (['chair', 'two'] as $who) {
            $copy = $this->gradeOf($who);
            $this->assertSame($this->id('one'), (int) $copy->graded_by_expert_id);
            $this->assertSame(PanelGrade::DRAFT, $copy->status);
            $this->assertEquals(80, (float) $copy->total_score);
            $this->assertSame('Fix the scope', $copy->remarks);
        }

        $this->grade('one', 80)->assertOk();

        $this->defense->refresh();
        $this->assertTrue($this->defense->allGradesIn());
        $this->assertSame(80.0, $this->defense->panelAverage());
    }

    public function test_a_split_panel_averages_every_panelist(): void
    {
        $this->setGrader('one', 'one', ['chair'])->assertOk();

        $this->grade('one', 80)->assertOk();
        $this->grade('two', 70)->assertOk();

        // 80 for the chair and member one, 70 for member two: (80 + 80 + 70) / 3.
        $this->assertSame(76.67, $this->defense->refresh()->panelAverage());
    }

    public function test_the_shared_grade_and_an_abstention(): void
    {
        $this->setGrader('chair', 'one', ['chair'])->assertOk();
        $this->grade('one', 80)->assertOk();

        $this->actingAs($this->panel['two'], 'expert')
            ->postJson($this->url('/abstain'), ['reason' => 'I advised this team.'])
            ->assertOk();

        $this->assertSame(80.0, $this->defense->refresh()->panelAverage());
    }

    public function test_a_joined_panelist_cannot_grade_until_they_leave(): void
    {
        $this->setGrader('chair', 'one', ['two'])->assertOk();

        $this->grade('two', 70, 'putJson')->assertStatus(422)
            ->assertJsonPath('message', 'One Panelist is grading this team for you. Leave their grade first to grade on your own.');

        $this->actingAs($this->panel['two'], 'expert')->deleteJson($this->url('/join'))->assertOk();

        $own = $this->gradeOf('two');
        $this->assertNull($own->graded_by_expert_id);
        $this->assertSame([], $own->scores);

        $this->grade('two', 70)->assertOk();
        $this->assertEquals(70, (float) $this->gradeOf('two')->total_score);
    }

    public function test_leaving_tells_the_grader(): void
    {
        $this->setGrader('chair', 'one', ['two'])->assertOk();

        $this->actingAs($this->panel['two'], 'expert')->deleteJson($this->url('/join'))->assertOk();

        $this->assertTrue(Notification::where('expert_id', $this->id('one'))->where('type', 'shared_grading')->exists());
    }

    public function test_joining_tells_the_panelist_who_was_joined(): void
    {
        $this->setGrader('chair', 'one', ['two'])->assertOk();

        $this->assertTrue(Notification::where('expert_id', $this->id('two'))->where('type', 'shared_grading')->exists());
    }

    public function test_nobody_is_joined_once_they_turned_in_their_own_grade(): void
    {
        $this->grade('two', 70)->assertOk();

        $this->setGrader('chair', 'one', ['two'])->assertStatus(422)
            ->assertJsonPath('message', 'Two Panelist already turned in a grade of their own.');

        $this->assertNull($this->gradeOf('two')->graded_by_expert_id);
    }

    public function test_an_unsubmitted_draft_can_still_be_joined(): void
    {
        $this->grade('two', 70, 'putJson')->assertOk();

        $this->setGrader('chair', 'one', ['two'])->assertOk();

        $this->assertSame($this->id('one'), (int) $this->gradeOf('two')->graded_by_expert_id);
    }

    public function test_everyone_grading_again_clears_the_shared_grade(): void
    {
        $this->setGrader('chair', 'one', ['chair', 'two'])->assertOk();
        $this->grade('one', 80)->assertOk();

        $this->setGrader('two', null)->assertOk()->assertJsonPath('data.grader', null);

        foreach (['chair', 'two'] as $who) {
            $own = $this->gradeOf($who);
            $this->assertNull($own->graded_by_expert_id);
            $this->assertSame(PanelGrade::DRAFT, $own->status);
            $this->assertNull($own->total_score);
        }
        $this->assertSame(PanelGrade::SUBMITTED, $this->gradeOf('one')->status);
    }

    public function test_the_grader_abstains_for_the_panel(): void
    {
        $this->setGrader('chair', 'one', ['chair', 'two'])->assertOk();

        $this->actingAs($this->panel['one'], 'expert')
            ->postJson($this->url('/abstain'), ['reason' => 'A redefense: no grade is given.'])
            ->assertOk();

        foreach (['chair', 'two'] as $who) {
            $this->assertSame(PanelGrade::ABSTAINED, $this->gradeOf($who)->status);
        }
        $this->defense->refresh();
        $this->assertTrue($this->defense->allGradesIn());
        $this->assertNull($this->defense->panelAverage());

        // And taking it back takes it back for everyone.
        $this->actingAs($this->panel['one'], 'expert')->deleteJson($this->url('/abstain'))->assertOk();
        $this->assertSame(PanelGrade::DRAFT, $this->gradeOf('two')->status);
    }

    public function test_a_joined_panelist_cannot_abstain_on_their_own(): void
    {
        $this->setGrader('chair', 'one', ['two'])->assertOk();

        $this->actingAs($this->panel['two'], 'expert')
            ->postJson($this->url('/abstain'), ['reason' => 'I advised this team.'])
            ->assertStatus(422);
    }

    public function test_the_grader_records_the_decision_when_grading_for_the_chair(): void
    {
        $this->setGrader('two', 'one', ['chair', 'two'])->assertOk();

        $this->grade('one', 80)->assertOk()->assertJsonPath('data.can_record_outcome', true);

        $this->actingAs($this->panel['one'], 'expert')
            ->postJson($this->url('/outcome'), ['outcome' => 'passed'])
            ->assertOk();

        $this->defense->refresh();
        $this->assertSame('passed', $this->defense->outcome);
        $this->assertSame($this->id('one'), (int) $this->defense->outcome_recorded_by);
    }

    public function test_a_grader_not_grading_for_the_chair_cannot_record_the_decision(): void
    {
        $this->setGrader('one', 'one', ['two'])->assertOk();
        $this->grade('one', 80)->assertOk();
        $this->grade('chair', 70)->assertOk();

        $this->actingAs($this->panel['one'], 'expert')
            ->postJson($this->url('/outcome'), ['outcome' => 'passed'])
            ->assertStatus(403);
    }

    public function test_who_grades_cannot_change_after_the_decision(): void
    {
        foreach (['chair', 'one', 'two'] as $who) {
            $this->grade($who, 80)->assertOk();
        }
        $this->actingAs($this->panel['chair'], 'expert')
            ->postJson($this->url('/outcome'), ['outcome' => 'passed'])
            ->assertOk();

        $this->setGrader('chair', 'one', ['two'])->assertStatus(422);
    }

    public function test_only_the_panel_sets_who_grades(): void
    {
        $outsider = CapstoneExpert::create([
            'email' => 'outsider@gmail.com', 'first_name' => 'Out', 'last_name' => 'Sider', 'approved_status' => true,
        ]);

        $this->actingAs($outsider, 'expert')->putJson($this->url('/grader'), [
            'grader_id' => $this->id('one'),
            'joined' => [$this->id('two')],
        ])->assertForbidden();

        $this->actingAs($this->panel['chair'], 'expert')->putJson($this->url('/grader'), [
            'grader_id' => $this->id('one'),
            'joined' => [$outsider->expert_id],
        ])->assertStatus(422);
    }

    public function test_the_list_shows_who_grades_and_who_is_graded_for(): void
    {
        $this->setGrader('chair', 'one', ['chair', 'two'])->assertOk();

        $this->actingAs($this->panel['two'], 'expert')->get('/instructor/grading')
            ->assertOk()
            ->assertSee('Who grades')
            ->assertSee('One Panelist')
            ->assertSee('Grading for you');
    }

    public function test_a_joined_panelist_sees_who_grades_for_them(): void
    {
        $this->setGrader('chair', 'one', ['two'])->assertOk();

        $this->actingAs($this->panel['two'], 'expert')->get($this->url())
            ->assertOk()
            ->assertSee('One Panelist is grading for you')
            ->assertSee('Grade on my own instead')
            ->assertDontSee('id="gs-submit"', false);
    }

    public function test_the_grader_sees_who_they_grade_for(): void
    {
        $this->setGrader('chair', 'one', ['chair', 'two'])->assertOk();

        $this->actingAs($this->panel['one'], 'expert')->get($this->url())
            ->assertOk()
            ->assertSee('Grading for the panel')
            ->assertSee('Submit for all 3')
            ->assertSee('Panel remarks')
            ->assertSee('Opens when you submit');
    }

    public function test_the_team_sees_a_shared_grade_once(): void
    {
        $student = Student::factory()->withCompleteProfile()->create();
        $this->defense->team->update(['leader_id' => $student->student_id]);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'team_id' => $this->defense->team_id]);

        $this->setGrader('chair', 'one', ['chair'])->assertOk();
        $this->grade('one', 80)->assertOk();
        $this->grade('two', 70)->assertOk();
        $this->actingAs($this->panel['one'], 'expert')
            ->postJson($this->url('/outcome'), ['outcome' => 'passed'])
            ->assertOk();

        $page = $this->actingAs($student, 'web')->get(route('student.defense-scores'))->assertOk();
        $page->assertSee('Shared grade · 2 panelists')
            ->assertSee('Chair Panelist and One Panelist')
            ->assertSee('76.67');
    }
}
