<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use App\Models\Team;
use App\Models\TeamRatingForm;
use App\Models\Term;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The 2nd semester of a school year is not a new school year.
 *
 * Switching semester gives every unfinished student a second student_s_y
 * row, for the new term, and both rows say 2026-2027. The model's own scope
 * reads only the active term's row, but the queries that join the table
 * directly did not: once the 2nd semester started a team of three showed six
 * members and read as full, and every classmate came up twice.
 */
class SecondSemesterReadsOneRowTest extends TestCase
{
    use RefreshDatabase;

    private CapstoneExpert $admin;

    private Team $team;

    /** @var list<Student> */
    private array $members = [];

    protected function setUp(): void
    {
        parent::setUp();
        (new StudentRoleSeeder)->run();

        $this->admin = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'is_admin' => true, 'sections' => ['BSIT-3A']]));
        Term::active()->update(['school_year' => '2026-2027', 'semester' => 1]);
        Term::forgetActive();

        for ($i = 0; $i < 3; $i++) {
            $this->members[] = Student::factory()->withCompleteProfile()->create([
                'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
            ]);
        }

        $this->team = Team::create([
            'team_name' => 'Team Alpha', 'team_status' => 'Active', 'leader_id' => $this->members[0]->student_id,
            'is_confirmed' => true, 'member_count' => 5,
        ]);

        foreach ($this->members as $m) {
            StudentSchoolYear::create(['student_id' => $m->student_id, 'team_id' => $this->team->team_id]);
        }

        // A classmate with no team yet, so the classmate picker has someone to offer.
        $loner = Student::factory()->withCompleteProfile()->create([
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $loner->student_id]);
    }

    /** The Admin moves the school to the 2nd semester of the same year. */
    private function startSecondSemester(): void
    {
        $this->actingAs($this->admin, 'expert')
            ->put(route('admin.settings.update'), ['current_school_year' => '2026-2027', 'semester' => 2])
            ->assertSessionHasNoErrors();
        Term::forgetActive();
    }

    public function test_switching_semester_gives_each_student_a_second_row_in_the_same_year(): void
    {
        $this->startSecondSemester();

        $this->assertSame(2, Term::where('school_year', '2026-2027')->count(), 'two semesters of one year, not two years');
        $this->assertSame(2, StudentSchoolYear::acrossAllTerms()->where('student_id', $this->members[0]->student_id)->count());
        $this->assertSame(1, StudentSchoolYear::where('student_id', $this->members[0]->student_id)->count(), 'the model reads this semester only');
    }

    public function test_the_team_page_counts_each_member_once(): void
    {
        $name = e($this->members[1]->f_name.' '.$this->members[1]->l_name);
        $before = substr_count($this->actingAs($this->members[0])->get(route('student.team'))->assertOk()->getContent(), $name);

        $this->startSecondSemester();

        $html = $this->actingAs($this->members[0])->get(route('student.team'))->assertOk()->getContent();

        $this->assertStringContainsString('Forming · 2 slots open', $html, 'three of five, not six of five and full');
        $this->assertStringNotContainsString('6 / 5', $html);
        $this->assertSame($before, substr_count($html, $name), 'a member is shown as often as in the 1st semester');
    }

    public function test_the_dashboard_counts_each_member_once(): void
    {
        $this->startSecondSemester();

        $this->actingAs($this->members[0])->get(route('student.dashboard'))->assertOk()
            ->assertSee('3 members')
            ->assertDontSee('6 members');
    }

    public function test_a_classmate_is_offered_once(): void
    {
        $this->startSecondSemester();

        $ids = collect($this->actingAs($this->members[0])->getJson(route('team.available-classmates'))->assertOk()->json('classmates'))->pluck('student_id');

        $this->assertNotEmpty($ids);
        $this->assertSame($ids->unique()->values()->all(), $ids->values()->all(), 'nobody twice');
    }

    public function test_a_team_rating_lists_each_teammate_once(): void
    {
        $this->startSecondSemester();
        $capsa = $this->makeCapsa(CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-3A']]));
        $form = TeamRatingForm::create([
            'capsa_id' => $capsa->expert_id, 'name' => 'Finals', 'sections' => ['BSIT-3A'], 'days_to_rate' => 7,
            'opens_on' => today()->subDay(), 'due_on' => today()->addDays(5),
        ]);
        $form->items()->create(['label' => 'Chapter 4']);

        $html = $this->actingAs($this->members[0])
            ->get(route('student.team_rating.rate', [$form, $this->members[1]->student_id]))->assertOk()->getContent();

        $this->assertStringContainsString('teammate 1 of 2', $html);
    }

    public function test_the_submission_drawer_finds_the_team_in_the_new_semester(): void
    {
        $this->startSecondSemester();

        // The 1st semester row still names the team; the 2nd semester one is
        // the one read, so leaving the team now is what the drawer sees.
        StudentSchoolYear::where('student_id', $this->members[2]->student_id)->update(['team_id' => null]);

        $this->actingAs($this->members[0])->getJson(route('student.submission.plan'))->assertOk();
        $this->actingAs($this->members[2])->getJson(route('student.submission.plan'))
            ->assertJsonMissing(['team_id' => $this->team->team_id]);
    }
}
