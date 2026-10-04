<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Create Post offers six choices: Leader, Member, and Network Designer/UI
 * Designer or QA Tester/Documentation, each as Primary or Secondary. The
 * post keeps what was picked, and `roles` keeps the team roles behind it,
 * which is what the join flow matches on.
 */
class RecruitmentRoleTagsTest extends TestCase
{
    use RefreshDatabase;

    private const DESIGNER = 'Network Designer/UI Designer';

    private const TESTER = 'QA Tester/Documentation Writer';

    protected function setUp(): void
    {
        parent::setUp();
        (new StudentRoleSeeder)->run();
    }

    private function student(): Student
    {
        $student = Student::create([
            'email' => 'poster@my.cspc.edu.ph', 'f_name' => 'Ana', 'l_name' => 'Cruz',
            'course' => 'BSIT', 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id]);

        return $student;
    }

    private function publish(Student $student, array $tags): ?Recruitment
    {
        $this->actingAs($student, 'web')->post(route('recruitment.store'), [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'I can design screens and write the manuscript.',
            'member_count' => 5,
            'tags' => json_encode($tags),
        ]);

        return Recruitment::where('student_id', $student->student_id)->first();
    }

    public function test_the_six_choices_are_offered_in_order(): void
    {
        $this->assertSame([
            'Leader',
            'Member',
            'Network Designer/UI Designer (Primary)',
            'Network Designer/UI Designer (Secondary)',
            'QA Tester/Documentation (Primary)',
            'QA Tester/Documentation (Secondary)',
        ], array_column(Recruitment::ROLE_OPTIONS, 'label'));

        $this->actingAs($this->student(), 'web')->get(route('student.team'))->assertOk()
            ->assertSee('QA Tester/Documentation (Secondary)', false)
            ->assertSee('"key":"leader"', false);
    }

    public function test_the_post_keeps_the_choices_and_the_roles_behind_them(): void
    {
        $post = $this->publish($this->student(), ['qa_secondary', 'member', 'nd_primary']);

        $this->assertSame(['member', 'nd_primary', 'qa_secondary'], $post->role_tags, 'kept in the order offered');
        $this->assertSame([self::DESIGNER, self::TESTER], $post->roles, 'the team roles the join flow matches on');
        $this->assertSame(['Member', 'Network Designer/UI Designer (Primary)', 'QA Tester/Documentation (Secondary)'], $post->tagLabels());
    }

    public function test_leader_or_member_alone_is_a_post_that_names_no_particular_seat(): void
    {
        $post = $this->publish($this->student(), ['leader']);

        $this->assertNotNull($post);
        $this->assertSame(['leader'], $post->role_tags);
        $this->assertSame([], $post->roles);
    }

    public function test_something_not_on_the_list_is_dropped_and_nothing_valid_is_refused(): void
    {
        $student = $this->student();

        $this->assertNull($this->publish($student, ['Backend Dev', 'qa_urgent']));

        $post = $this->publish($student, ['Backend Dev', 'nd_secondary']);
        $this->assertSame(['nd_secondary'], $post->role_tags);
    }

    public function test_a_bare_role_name_from_an_older_form_reads_as_its_primary(): void
    {
        $post = $this->publish($this->student(), [self::TESTER]);

        $this->assertSame(['qa_primary'], $post->role_tags);
        $this->assertSame([self::TESTER], $post->roles);
    }

    public function test_editing_the_post_saves_the_new_choices(): void
    {
        $student = $this->student();
        $post = $this->publish($student, ['nd_primary']);

        // The edit form sends the choices as roles[].
        $this->actingAs($student, 'web')->put(route('recruitment.update', $post), [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'Updated.',
            'roles' => ['member', 'qa_primary'],
        ])->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame(['member', 'qa_primary'], $post->role_tags);
        $this->assertSame([self::TESTER], $post->roles);
    }

    public function test_an_old_post_shows_its_roles_as_primary(): void
    {
        $student = $this->student();
        $post = Recruitment::create(['student_id' => $student->student_id, 'recruitment_goal' => 'find_a_team', 'roles' => [self::DESIGNER]]);

        $this->assertSame(['Network Designer/UI Designer (Primary)'], $post->tagLabels());
    }

    public function test_the_feed_shows_the_choices_on_the_post(): void
    {
        $student = $this->student();
        $this->publish($student, ['leader', 'qa_secondary']);

        $this->actingAs($student, 'web')->get(route('student.team'))->assertOk()
            ->assertSee('QA Tester/Documentation (Secondary)');
    }
}
