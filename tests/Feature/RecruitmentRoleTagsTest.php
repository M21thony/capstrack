<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Create Post offers a Primary - the role the student takes on the team:
 * Network Designer/UI Designer or QA Tester/Documentation - and Secondary
 * skills, what else they can give. Someone looking for a team names one
 * Primary; a leader names every role the team needs. The post keeps what
 * was picked, and `roles` keeps the team roles behind it, which is what the
 * join flow matches on.
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

    /**
     * @param  list<string>  $tags
     * @param  list<string>  $secondary
     */
    private function publish(Student $student, array $tags, array $secondary = [], string $goal = 'find_a_team'): ?Recruitment
    {
        $this->actingAs($student, 'web')->post(route('recruitment.store'), [
            'recruitment_goal' => $goal,
            'introduction' => 'I can design screens and write the manuscript.',
            'looking_for' => 'We are building an inventory system.',
            'member_count' => 5,
            'tags' => json_encode($tags),
            'secondary' => json_encode($secondary),
        ]);

        return Recruitment::where('student_id', $student->student_id)->first();
    }

    public function test_primary_offers_the_two_member_roles_and_nothing_else(): void
    {
        $this->assertSame(
            ['Network Designer/UI Designer', 'QA Tester/Documentation'],
            array_column(Recruitment::ROLE_OPTIONS, 'label')
        );

        $this->actingAs($this->student(), 'web')->get(route('student.team'))->assertOk()
            ->assertSee('"key":"nd"', false)
            ->assertSee('"key":"qa"', false)
            ->assertDontSee('(Primary)', false)
            ->assertDontSee('(Secondary)', false)
            ->assertDontSee('"key":"leader"', false)
            ->assertDontSee('"label":"Software Engineer/Programmer"', false);
    }

    public function test_secondary_is_a_short_list_that_repeats_nothing(): void
    {
        $skills = Recruitment::SECONDARY_SKILLS;

        $this->assertContains('Web Development', $skills);
        $this->assertLessThanOrEqual(12, count($skills));
        $this->assertCount(count($skills), array_unique(array_map('mb_strtolower', $skills)), 'no skill twice');

        // None of them says a Primary role over again.
        foreach ($skills as $skill) {
            $this->assertDoesNotMatchRegularExpression('/\\b(network|ui|qa|test(ing|er)?|documentation)\\b/i', $skill);
        }

        $html = $this->actingAs($this->student(), 'web')->get(route('student.team'))->assertOk()->getContent();
        foreach ($skills as $skill) {
            $this->assertStringContainsString('data-skill="'.e($skill).'"', $html);
        }
    }

    public function test_the_post_keeps_one_primary_and_every_secondary_picked(): void
    {
        $post = $this->publish($this->student(), ['nd'], ['Research', 'Web Development']);

        $this->assertSame(['nd'], $post->role_tags);
        $this->assertSame([self::DESIGNER], $post->roles, 'the team role the join flow matches on');
        $this->assertSame(['Network Designer/UI Designer'], $post->tagLabels());
        $this->assertSame(['Web Development', 'Research'], $post->skills, 'kept in the order offered');
        $this->assertSame(['Web Development', 'Research'], $post->secondaryLabels());
    }

    public function test_secondary_is_optional(): void
    {
        $post = $this->publish($this->student(), ['qa']);

        $this->assertNotNull($post);
        $this->assertSame([], $post->secondaryLabels());
    }

    public function test_a_secondary_that_is_not_on_the_list_is_dropped(): void
    {
        $post = $this->publish($this->student(), ['qa'], ['Juggling', 'Database', 'Network Designer/UI Designer']);

        $this->assertSame(['Database'], $post->skills);
    }

    public function test_someone_looking_for_a_team_cannot_post_two_primaries(): void
    {
        $student = $this->student();

        $this->actingAs($student, 'web')->postJson(route('recruitment.store'), [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'Both, please.',
            'tags' => json_encode(['nd', 'qa']),
        ])->assertStatus(422)->assertJson(['message' => 'Choose one primary role only.']);

        $this->assertNull(Recruitment::where('student_id', $student->student_id)->first());
    }

    public function test_a_post_with_no_primary_is_refused(): void
    {
        $student = $this->student();

        $this->actingAs($student, 'web')->postJson(route('recruitment.store'), [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'Anything.',
            'tags' => json_encode([]),
            'secondary' => json_encode(['Web Development']),
        ])->assertStatus(422)->assertJson(['message' => 'Choose your primary role.']);
    }

    public function test_a_leader_building_a_team_may_name_both_roles_it_needs(): void
    {
        $post = $this->publish($this->student(), ['qa', 'nd'], ['Mobile Development'], 'build_a_team');

        $this->assertSame(['nd', 'qa'], $post->role_tags, 'kept in the order offered');
        $this->assertSame([self::TESTER, self::DESIGNER], $post->roles, 'in the order picked');
        $this->assertSame(['Mobile Development'], $post->skills);
    }

    public function test_leader_member_and_programmer_are_not_primary_choices(): void
    {
        $student = $this->student();

        $this->assertNull($this->publish($student, ['leader', 'member', 'Leader', 'Software Engineer/Programmer', 'Backend Dev']));

        $post = $this->publish($student, ['Backend Dev', 'leader', 'nd']);
        $this->assertSame(['nd'], $post->role_tags);
    }

    public function test_a_bare_role_name_or_an_older_tag_reads_as_the_role(): void
    {
        $this->assertSame(['qa'], Recruitment::tagKeys([self::TESTER]));
        $this->assertSame(['qa'], Recruitment::tagKeys(['qa_secondary']));
        $this->assertSame(['nd'], Recruitment::tagKeys(['Network Designer/UI Designer (Primary)']));
        $this->assertSame(['nd', 'qa'], Recruitment::tagKeys(['qa_primary', 'nd_secondary', 'nd_primary']));

        $post = $this->publish($this->student(), [self::TESTER]);
        $this->assertSame(['qa'], $post->role_tags);
        $this->assertSame([self::TESTER], $post->roles);
    }

    public function test_editing_the_post_saves_the_new_primary_and_secondary(): void
    {
        $student = $this->student();
        $post = $this->publish($student, ['nd'], ['Research']);

        // The edit form sends the roles as roles[] and Secondary as a JSON list.
        $this->actingAs($student, 'web')->put(route('recruitment.update', $post), [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'Updated.',
            'roles' => ['qa'],
            'secondary' => json_encode(['Database', 'Presentation']),
        ])->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame(['qa'], $post->role_tags);
        $this->assertSame([self::TESTER], $post->roles);
        $this->assertSame(['Database', 'Presentation'], $post->skills);

        $this->actingAs($student, 'web')->put(route('recruitment.update', $post), [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'Updated.',
            'roles' => ['qa', 'nd'],
        ])->assertSessionHasErrors('roles');
    }

    public function test_an_old_post_shows_its_roles_without_primary_or_secondary_beside_them(): void
    {
        $student = $this->student();
        $post = Recruitment::create(['student_id' => $student->student_id, 'recruitment_goal' => 'find_a_team', 'roles' => [self::DESIGNER]]);
        $this->assertSame(['Network Designer/UI Designer'], $post->tagLabels());

        $post->update(['role_tags' => ['leader', 'nd_primary', 'qa_secondary']]);
        $this->assertSame(['Network Designer/UI Designer', 'QA Tester/Documentation'], $post->tagLabels());
    }

    public function test_the_feed_shows_the_primary_and_secondary_on_the_post(): void
    {
        $student = $this->student();
        $this->publish($student, ['qa'], ['Web Development']);

        $this->actingAs($student, 'web')->get(route('student.team'))->assertOk()
            ->assertSee('QA Tester/Documentation')
            ->assertSee('Web Development');
    }
}
