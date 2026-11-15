<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamRating;
use App\Models\TeamRatingForm;
use App\Models\TeamRatingRubric;
use App\Support\PeerRatingRubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Team Rating rubric is no longer one fixed list: the CaPSA/TSA writes
 * her own on Grading Rubrics, edits and deletes them, and chooses the one new
 * ratings are made with. A rating keeps a copy of the rubric it was made
 * with, so none of that changes the criteria under scores already given.
 */
class TeamRatingRubricTest extends TestCase
{
    use RefreshDatabase;

    private const SECTION = 'BSIT-3A';

    private CapstoneExpert $capsa;

    /** @var list<Student> */
    private array $members = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => [self::SECTION]]);
        $this->makeCapsa($this->capsa);

        for ($i = 0; $i < 3; $i++) {
            $this->members[] = Student::factory()->withCompleteProfile()->create([
                'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
            ]);
        }

        $team = Team::create([
            'team_name' => 'Team Alpha', 'team_status' => 'Active', 'leader_id' => $this->members[0]->student_id,
            'is_confirmed' => true, 'member_count' => 3,
        ]);

        foreach ($this->members as $m) {
            StudentSchoolYear::create(['student_id' => $m->student_id, 'team_id' => $team->team_id]);
        }
    }

    /**
     * @param  list<string>  $names
     * @return array{name: string, criteria: list<array{name: string, short: string, levels: list<string>}>}
     */
    private function payload(string $name = 'Midterm rubric', array $names = ['Attendance', 'Output']): array
    {
        return [
            'name' => $name,
            'criteria' => array_map(fn (string $n) => [
                'name' => $n, 'short' => '',
                'levels' => ["Always $n", "Often $n", "Sometimes $n", "Rarely $n", "Never $n"],
            ], $names),
        ];
    }

    private function create(array $payload): TeamRatingRubric
    {
        $id = $this->actingAs($this->capsa, 'expert')
            ->postJson(route('capsa.team_rating_rubrics.store'), $payload)
            ->assertCreated()->json('id');

        return TeamRatingRubric::findOrFail($id);
    }

    /** A rating made the way the CaPSA/TSA makes one, open today. */
    private function makeRating(string $name = 'Midterm Rating'): TeamRatingForm
    {
        $id = $this->actingAs($this->capsa, 'expert')->postJson(route('capsa.team_ratings.store'), [
            'name' => $name, 'sections' => [self::SECTION], 'follows_checklist' => false,
            'opens_on' => today()->toDateString(), 'days_to_rate' => 7, 'own' => ['Chapter 1'],
        ])->assertCreated()->json('id');

        return TeamRatingForm::with('items')->findOrFail($id);
    }

    public function test_with_no_rubric_written_a_rating_uses_the_departments(): void
    {
        $form = $this->makeRating();

        $this->assertNull($form->getAttribute('rubric'));
        $this->assertTrue($form->rubric()->isDefault);
        $this->assertSame(array_keys(PeerRatingRubric::CRITERIA), $form->rubric()->names());
        $this->assertSame(PeerRatingRubric::MAX, $form->rubric()->max());
    }

    public function test_the_capsa_writes_a_rubric_and_it_is_listed_beside_the_departments(): void
    {
        $rubric = $this->create($this->payload());

        $this->assertSame(['Attendance', 'Output'], array_column($rubric->criteria, 'name'));
        $this->assertSame(['Attendance', 'Output'], array_column($rubric->criteria, 'short'), 'a blank short heading is the name');
        $this->assertFalse($rubric->is_active, 'written, not yet in use');
        $this->assertSame(20, $rubric->asRubric()->max());

        $this->actingAs($this->capsa, 'expert')->get(route('capsa.rubric_management'))
            ->assertOk()
            ->assertSee('Team Rating rubrics')
            ->assertSee('Midterm rubric')
            ->assertSee('Participation Rubric')
            ->assertSee('openTeamRatingRubrics()', false)
            ->assertDontSee('All programs · fixed');
    }

    public function test_a_rubric_needs_a_name_a_criterion_and_all_five_descriptions(): void
    {
        $post = fn (array $payload) => $this->actingAs($this->capsa, 'expert')->postJson(route('capsa.team_rating_rubrics.store'), $payload);

        $post(['name' => '', 'criteria' => $this->payload()['criteria']])->assertStatus(422)->assertJsonValidationErrors('name');
        $post(['name' => 'Empty', 'criteria' => []])->assertStatus(422)->assertJsonValidationErrors('criteria');

        $short = $this->payload('Four levels');
        array_pop($short['criteria'][0]['levels']);
        $post($short)->assertStatus(422)->assertJsonValidationErrors('criteria.0.levels');

        $blank = $this->payload('Blank level');
        $blank['criteria'][1]['levels'][2] = '   ';
        $post($blank)->assertStatus(422)->assertJsonValidationErrors('criteria.1.levels.2');

        $post($this->payload('Twice', ['Output', 'output']))->assertStatus(422)->assertJsonValidationErrors('criteria.0.name');

        $tooMany = $this->payload('Too many', array_map(fn ($i) => 'Criterion '.$i, range(1, PeerRatingRubric::MAX_CRITERIA + 1)));
        $post($tooMany)->assertStatus(422)->assertJsonValidationErrors('criteria');

        $this->create($this->payload('Taken'));
        $post($this->payload('Taken'))->assertStatus(422)->assertJsonValidationErrors('name');

        $this->assertSame(1, TeamRatingRubric::count());
    }

    public function test_only_one_rubric_is_in_use_and_the_departments_can_be_chosen_again(): void
    {
        $first = $this->create($this->payload('First'));
        $second = $this->create($this->payload('Second'));
        $use = fn (string|int $which) => $this->actingAs($this->capsa, 'expert')->putJson(route('capsa.team_rating_rubrics.use', ['rubric' => $which]))->assertOk();

        $use($first->id);
        $use($second->id);
        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame('Second', TeamRatingRubric::inUse()->name);

        $listing = collect($use('default')->json('rubrics'));
        $this->assertNull(TeamRatingRubric::inUse());
        $this->assertTrue($listing->firstWhere('id', null)['in_use'], 'the department\'s is in use again');
        $this->assertTrue($listing->firstWhere('id', null)['fixed']);
    }

    public function test_a_new_rating_is_made_with_the_rubric_in_use_and_students_rate_on_it(): void
    {
        $this->create($this->payload())->putInUse();
        $form = $this->makeRating();
        [$me, $mate, $other] = $this->members;
        $item = $form->items->first();

        $this->assertSame(['Attendance', 'Output'], $form->rubric()->names());
        $this->assertSame(20, $form->rubric()->max());

        $this->actingAs($me)->get(route('student.team_rating.rate', [$form, $mate->student_id]))
            ->assertOk()
            ->assertSee('Attendance')
            ->assertSee('Always Output')
            ->assertSee('Totals out of 20', false)
            ->assertDontSee('Contribution to Group Discussions');

        $draft = fn (Student $ratee, array $scores) => $this->actingAs($me)
            ->patchJson(route('student.team_rating.draft', [$form, $ratee->student_id]), ['picks' => [$item->id => $scores]]);

        $draft($mate, [10, 7])->assertOk();
        $draft($other, [5, 4])->assertStatus(422);
        $draft($other, [5, 3])->assertOk();

        $row = TeamRating::firstWhere(['rater_id' => $me->student_id, 'ratee_id' => $mate->student_id]);
        $this->assertSame([10, 7], $row->scores);
        $this->assertSame(17, $row->total, 'two criteria are a complete rating on this rubric');

        $this->actingAs($me)->postJson(route('student.team_rating.submit', $form))->assertOk();

        $results = $form->results();
        $this->assertSame([10.0, 7.0], $results[$mate->student_id][$item->id]['criteria']);
        $this->assertSame(85.0, $form->percentFor($results[$mate->student_id]), '17 of 20');
    }

    public function test_editing_or_deleting_a_rubric_leaves_ratings_already_made_as_they_were(): void
    {
        $rubric = $this->create($this->payload());
        $rubric->putInUse();
        $before = $this->makeRating('Before the edit');

        $this->actingAs($this->capsa, 'expert')
            ->putJson(route('capsa.team_rating_rubrics.update', $rubric), $this->payload('Midterm rubric v2', ['Attendance', 'Output', 'Teamwork']))
            ->assertOk();

        $after = $this->makeRating('After the edit');

        $this->assertSame(['Attendance', 'Output'], $before->fresh()->rubric()->names());
        $this->assertSame(20, $before->fresh()->rubric()->max());
        $this->assertSame(['Attendance', 'Output', 'Teamwork'], $after->rubric()->names());
        $this->assertSame('Midterm rubric v2', $after->rubric()->name);

        $this->actingAs($this->capsa, 'expert')->deleteJson(route('capsa.team_rating_rubrics.destroy', $rubric))->assertOk();

        $this->assertSame(0, TeamRatingRubric::count());
        $this->assertSame(['Attendance', 'Output', 'Teamwork'], $after->fresh()->rubric()->names(), 'the rating kept its copy');
        $this->assertTrue($this->makeRating('After the delete')->rubric()->isDefault);
    }

    public function test_the_team_ratings_tab_reads_each_rating_on_its_own_rubric(): void
    {
        $old = $this->makeRating('On the department rubric');
        $this->create($this->payload())->putInUse();
        $new = $this->makeRating('On my rubric');

        $html = $this->actingAs($this->capsa, 'expert')
            ->get(route('capsa.team_management', ['tab' => 'team_ratings']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('out of 20 or 70 (each rating has its own rubric)', $html);
        $this->assertMatchesRegularExpression('/const RUBRICS = \{"'.$old->id.'":\{"name":"Member\\\\u0027s Participation Rubric"/', $html);
        $this->assertStringContainsString('"'.$new->id.'":{"name":"Midterm rubric","criteria":{"Attendance":', $html);
        $this->assertStringContainsString('const RUBRIC_IN_USE = {"name":"Midterm rubric"', $html);
    }

    public function test_a_student_or_an_expert_who_is_not_a_capsa_cannot_write_rubrics(): void
    {
        $expert = CapstoneExpert::factory()->create(['approved_status' => true]);

        $this->actingAs($expert, 'expert')->postJson(route('capsa.team_rating_rubrics.store'), $this->payload())->assertForbidden();
        $this->assertSame(0, TeamRatingRubric::count());
    }
}
