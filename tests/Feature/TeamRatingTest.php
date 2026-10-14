<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Checklist;
use App\Models\ChecklistMilestone;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamRating;
use App\Models\TeamRatingForm;
use App\Support\PeerRatingRubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Team Rating: each student rates every teammate but themself, per activity,
 * on the 7-criterion rubric. Drafts save as they go; submit sends them all at
 * once and nothing changes after. Only the CaPSA sees what anyone was given.
 */
class TeamRatingTest extends TestCase
{
    use RefreshDatabase;

    private const SECTION = 'BSIT-3A';

    private CapstoneExpert $capsa;

    /** @var list<Student> */
    private array $members;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capsa = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => [self::SECTION]]);
        $this->makeCapsa($this->capsa);
        [, $this->members] = $this->makeTeam('Team Alpha', 3);
    }

    /** @return array{0: Team, 1: list<Student>} */
    private function makeTeam(string $name, int $size, string $yearSection = '3rd · A'): array
    {
        $members = [];

        for ($i = 0; $i < $size; $i++) {
            $members[] = Student::factory()->withCompleteProfile()->create([
                'email' => strtolower(str_replace(' ', '', $name)).$i.'@my.cspc.edu.ph',
                'course' => 'Bachelor of Science in Information Technology',
                'year_section' => $yearSection,
            ]);
        }

        $team = Team::create([
            'team_name' => $name,
            'team_status' => 'Active',
            'leader_id' => $members[0]->student_id,
            'is_confirmed' => true,
            'member_count' => $size,
        ]);

        foreach ($members as $m) {
            StudentSchoolYear::create(['student_id' => $m->student_id, 'team_id' => $team->team_id]);
        }

        return [$team, $members];
    }

    /** An open rating with the CaPSA's own activities. */
    private function openForm(array $labels = ['Chapter 1'], array $overrides = []): TeamRatingForm
    {
        $form = TeamRatingForm::create(array_merge([
            'capsa_id' => $this->capsa->expert_id,
            'name' => 'Midterm Team Participation Rating',
            'sections' => [self::SECTION],
            'days_to_rate' => 7,
            'opens_on' => today()->subDay(),
            'due_on' => today()->addDays(5),
        ], $overrides));

        foreach ($labels as $l) {
            $form->items()->create(['label' => $l]);
        }

        return $form->load('items');
    }

    private function full(int $v = 10): array
    {
        return array_fill(0, count(PeerRatingRubric::CRITERIA), $v);
    }

    private function draft(Student $rater, TeamRatingForm $form, Student $ratee, array $picks)
    {
        return $this->actingAs($rater)->patchJson(route('student.team_rating.draft', [$form, $ratee->student_id]), ['picks' => $picks]);
    }

    /** Every teammate fully rated by $rater with $v. */
    private function rateAll(Student $rater, TeamRatingForm $form, int $v = 10): void
    {
        foreach ($this->members as $m) {
            if ($m->is($rater)) {
                continue;
            }
            $this->draft($rater, $form, $m, $form->items->mapWithKeys(fn ($i) => [$i->id => $this->full($v)])->all())->assertOk();
        }
    }

    public function test_a_draft_saves_partial_picks_and_the_rate_page_shows_them(): void
    {
        $form = $this->openForm();
        [$me, $mate] = $this->members;
        $item = $form->items->first();

        $this->draft($me, $form, $mate, [$item->id => [10, 7, null, null, null, null, null]])->assertOk()->assertJsonStructure(['savedAt']);

        $row = TeamRating::firstWhere(['rater_id' => $me->student_id, 'ratee_id' => $mate->student_id]);
        $this->assertSame([10, 7, null, null, null, null, null], $row->scores);
        $this->assertNull($row->total);

        $this->actingAs($me)->get(route('student.team_rating.rate', [$form, $mate->student_id]))
            ->assertOk()
            ->assertSee('2 of 7', false)
            ->assertSee('Rate '.$mate->f_name, false);
    }

    public function test_rating_yourself_or_someone_outside_the_team_is_refused(): void
    {
        $form = $this->openForm();
        [, $outsiders] = $this->makeTeam('Team Beta', 2);
        $me = $this->members[0];
        $picks = [$form->items->first()->id => $this->full()];

        $this->draft($me, $form, $me, $picks)->assertNotFound();
        $this->draft($me, $form, $outsiders[0], $picks)->assertNotFound();
        $this->actingAs($me)->get(route('student.team_rating.rate', [$form, $me->student_id]))->assertNotFound();
        $this->assertSame(0, TeamRating::count());
    }

    public function test_a_student_from_another_section_cannot_rate(): void
    {
        $form = $this->openForm();
        [, $others] = $this->makeTeam('Team Gamma', 2, '3rd · B');

        $this->draft($others[0], $form, $others[1], [$form->items->first()->id => $this->full()])->assertForbidden();
    }

    public function test_a_score_off_the_rubric_is_refused(): void
    {
        $form = $this->openForm();

        $this->draft($this->members[0], $form, $this->members[1], [$form->items->first()->id => [10, 8, 5, 3, 1, 7, 7]])
            ->assertUnprocessable()->assertJsonValidationErrors('picks');
        $this->assertSame(0, TeamRating::count());
    }

    public function test_submit_refuses_until_every_teammate_is_fully_rated(): void
    {
        $form = $this->openForm(['Chapter 1', 'Chapter 2']);
        [$me, $a, $b] = $this->members;
        [$one, $two] = $form->items;

        $this->draft($me, $form, $a, [$one->id => $this->full(), $two->id => $this->full()]);
        $this->draft($me, $form, $b, [$one->id => $this->full(), $two->id => [10, 10, 10, 10, 10, 10, null]]);

        $this->actingAs($me)->postJson(route('student.team_rating.submit', $form))
            ->assertUnprocessable()->assertJsonValidationErrors('ratings');
        $this->assertSame(0, TeamRating::whereNotNull('submitted_at')->count());
    }

    public function test_submit_locks_every_rating_and_tells_the_capsa(): void
    {
        $form = $this->openForm();
        $me = $this->members[0];
        $this->rateAll($me, $form);

        $this->actingAs($me)->postJson(route('student.team_rating.submit', $form))->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(2, TeamRating::where('rater_id', $me->student_id)->whereNotNull('submitted_at')->count());
        $this->assertDatabaseHas('notifications', ['type' => 'team_rating_submitted']);

        // Nothing changes after submit, and the rate page sends them back to the tab.
        $this->draft($me, $form, $this->members[1], [$form->items->first()->id => $this->full(1)])->assertUnprocessable();
        $this->actingAs($me)->postJson(route('student.team_rating.submit', $form))->assertUnprocessable();
        $this->actingAs($me)->get(route('student.team_rating.rate', [$form, $this->members[1]->student_id]))
            ->assertRedirect(route('student.team', ['tab' => 'rating']));
        $this->assertSame(70, TeamRating::where('rater_id', $me->student_id)->first()->total);
    }

    public function test_saving_before_it_opens_or_after_it_closes_is_refused(): void
    {
        $scheduled = $this->openForm(['A'], ['opens_on' => today()->addDays(2), 'due_on' => today()->addDays(9)]);
        $closed = $this->openForm(['B'], ['closed_at' => now()]);
        $past = $this->openForm(['C'], ['opens_on' => today()->subDays(9), 'due_on' => today()->subDay()]);
        [$me, $mate] = $this->members;

        foreach ([$scheduled, $closed, $past] as $form) {
            $this->draft($me, $form, $mate, [$form->items->first()->id => $this->full()])->assertUnprocessable();
        }
        $this->assertSame(0, TeamRating::count());
    }

    public function test_the_team_rating_tab_lists_the_open_rating_with_a_due_count(): void
    {
        $form = $this->openForm();

        $this->actingAs($this->members[0])->get(route('student.team', ['tab' => 'rating']))
            ->assertOk()
            ->assertSee($form->name)
            ->assertSee('1 due', false);
    }

    public function test_every_style_and_script_block_carries_the_csp_nonce(): void
    {
        // The CSP silently drops an inline block with no nonce: the page loses all its styling.
        $views = ['student/team-rating/rate', 'student/team-rating/_panel', 'capsa/partials/team-management/team_ratings'];

        foreach ($views as $view) {
            preg_match_all('/<(style|script)\b[^>]*>/', file_get_contents(resource_path("views/$view.blade.php")), $tags);
            foreach ($tags[0] as $tag) {
                $this->assertStringContainsString('nonce=', $tag, "$view: $tag");
            }
        }
    }

    public function test_capsa_create_refuses_an_activity_already_in_a_rating(): void
    {
        $checklist = Checklist::create(['capsa_id' => $this->capsa->expert_id, 'program' => 'BSIT', 'title' => 'BSIT']);
        $m = ChecklistMilestone::create(['checklist_id' => $checklist->id, 'name' => 'Chapter 1', 'due_on' => today()->addDays(3), 'position' => 1]);
        $body = ['name' => 'Midterm', 'sections' => [self::SECTION], 'follows_checklist' => true, 'days_to_rate' => 7, 'milestones' => [$m->id]];

        $this->actingAs($this->capsa, 'expert')->postJson(route('capsa.team_ratings.store'), $body)->assertCreated();
        $this->actingAs($this->capsa, 'expert')->postJson(route('capsa.team_ratings.store'), ['name' => 'Again'] + $body)
            ->assertUnprocessable()->assertJsonValidationErrors('milestones');
    }

    public function test_following_the_checklist_needs_a_dated_milestone(): void
    {
        $checklist = Checklist::create(['capsa_id' => $this->capsa->expert_id, 'program' => 'BSIT', 'title' => 'BSIT']);
        $m = ChecklistMilestone::create(['checklist_id' => $checklist->id, 'name' => 'Chapter 1', 'position' => 1]);

        $this->actingAs($this->capsa, 'expert')->postJson(route('capsa.team_ratings.store'), [
            'name' => 'Midterm', 'sections' => [self::SECTION], 'follows_checklist' => true, 'days_to_rate' => 7, 'milestones' => [$m->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('milestones');
        $this->assertSame(0, TeamRatingForm::count());
    }

    public function test_moving_a_milestone_moves_the_window_until_the_capsa_sets_a_date(): void
    {
        $checklist = Checklist::create(['capsa_id' => $this->capsa->expert_id, 'program' => 'BSIT', 'title' => 'BSIT']);
        $m = ChecklistMilestone::create(['checklist_id' => $checklist->id, 'name' => 'Chapter 1', 'due_on' => today()->addDays(3), 'position' => 1]);
        $this->actingAs($this->capsa, 'expert')->postJson(route('capsa.team_ratings.store'), [
            'name' => 'Midterm', 'sections' => [self::SECTION], 'follows_checklist' => true, 'days_to_rate' => 7, 'milestones' => [$m->id],
        ])->assertCreated();

        $this->assertTrue(TeamRatingForm::first()->opensOn()->isSameDay(today()->addDays(3)));

        $m->update(['due_on' => today()->addDays(6)]);
        $form = TeamRatingForm::first();
        $this->assertTrue($form->opensOn()->isSameDay(today()->addDays(6)));
        $this->assertTrue($form->dueOn()->isSameDay(today()->addDays(13)));

        $this->actingAs($this->capsa, 'expert')->patchJson(route('capsa.team_ratings.update', $form), ['action' => 'due', 'due_on' => today()->addDays(10)->toDateString()])->assertOk();
        $m->update(['due_on' => today()->addDays(20)]);
        $this->assertTrue(TeamRatingForm::first()->dueOn()->isSameDay(today()->addDays(10)));
    }

    public function test_another_capsa_cannot_change_or_remind_this_rating(): void
    {
        $form = $this->openForm();
        $other = CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => [self::SECTION]]);
        $this->makeCapsa($other);

        $this->actingAs($other, 'expert')->patchJson(route('capsa.team_ratings.update', $form), ['action' => 'close'])->assertForbidden();
        $this->actingAs($other, 'expert')->postJson(route('capsa.team_ratings.remind', $form))->assertForbidden();
        $this->assertNull($form->fresh()->closed_at);
    }

    public function test_a_student_cannot_reach_the_capsa_side(): void
    {
        $form = $this->openForm();

        $res = $this->actingAs($this->members[0])->patchJson(route('capsa.team_ratings.update', $form), ['action' => 'close']);

        $this->assertFalse($res->isSuccessful());
        $this->assertNull($form->fresh()->closed_at);
    }

    public function test_results_average_only_submitted_ratings(): void
    {
        $form = $this->openForm();
        [$a, $b, $c] = $this->members;
        $this->rateAll($a, $form, 10);
        $this->rateAll($b, $form, 5);
        $this->rateAll($c, $form, 1);
        foreach ([$a, $b] as $s) {
            $this->actingAs($s)->postJson(route('student.team_rating.submit', $form))->assertOk();
        }

        $results = $form->fresh()->results();
        $item = $form->items->first()->id;

        // c was rated by a (70) and b (35). c's own 1s were never submitted, so they count nowhere.
        $this->assertSame(52.5, $results[$c->student_id][$item]['avg']);
        $this->assertSame(2, $results[$c->student_id][$item]['n']);
        $this->assertSame(35.0, (float) $results[$a->student_id][$item]['avg']);
        $this->assertSame(1, $results[$a->student_id][$item]['n']);
        $this->assertSame(75.0, TeamRatingForm::percentFor($results[$c->student_id]));
        $this->assertNull(TeamRatingForm::percentFor([]));
        $this->assertEqualsCanonicalizing([$a->student_id, $b->student_id], $form->submittedRaterIds());
    }

    public function test_notify_announces_once_and_reminds_the_day_before_it_closes(): void
    {
        $form = $this->openForm(['A'], ['due_on' => today()->addDay()]);
        $this->rateAll($this->members[0], $form);
        $this->actingAs($this->members[0])->postJson(route('student.team_rating.submit', $form))->assertOk();

        $this->artisan('team-ratings:notify')->assertSuccessful();
        $this->artisan('team-ratings:notify')->assertSuccessful();

        $this->assertSame(3, DB::table('notifications')->where('type', 'team_rating_opened')->count());
        // Only the two who have not submitted, and only once.
        $this->assertSame(2, DB::table('notifications')->where('type', 'team_rating_reminder')->count());
        $this->assertNotNull($form->fresh()->reminded_at);
    }
}
