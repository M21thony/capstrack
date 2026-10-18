<?php

namespace Tests\Feature;

use App\Models\Recruitment;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel comment 2: a post names the skills it needs, from the same catalogue
 * experts pick from on their profile, and the feed can be filtered by one.
 */
class RecruitmentSkillsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new StudentRoleSeeder)->run();
    }

    private function student(string $first = 'Ana', string $course = 'BSIT'): Student
    {
        $student = Student::create([
            'email' => strtolower($first).'@my.cspc.edu.ph', 'f_name' => $first, 'l_name' => 'Cruz',
            'course' => $course, 'year_section' => '3rd · A',
        ]);
        StudentSchoolYear::create(['student_id' => $student->student_id]);

        return $student;
    }

    private function publish(Student $student, array $skills): Recruitment
    {
        $this->actingAs($student, 'web')->post(route('recruitment.store'), [
            'recruitment_goal' => 'find_a_team',
            'introduction' => 'I can design screens and test them.',
            'member_count' => 5,
            'tags' => json_encode(['member']),
            'skills' => json_encode($skills),
        ])->assertRedirect(route('student.team'))->assertSessionHasNoErrors();

        return Recruitment::where('student_id', $student->student_id)->firstOrFail();
    }

    public function test_only_catalogue_skills_are_kept_in_catalogue_order(): void
    {
        $post = $this->publish($this->student(), ['UI/UX Design', 'Basket Weaving', 'Web Development', 'UI/UX Design']);

        $this->assertSame(['Web Development', 'UI/UX Design'], $post->skills);
    }

    public function test_editing_replaces_the_skills_and_an_edit_without_them_keeps_them(): void
    {
        $student = $this->student();
        $post = $this->publish($student, ['Web Development']);
        $edit = fn (array $extra) => $this->actingAs($student, 'web')->put(route('recruitment.update', $post), $extra + [
            'recruitment_goal' => 'find_a_team', 'introduction' => 'Updated.', 'roles' => ['member'],
        ])->assertSessionHasNoErrors();

        $edit(['skills' => json_encode(['Software Testing / QA', 'Cybersecurity'])]);
        $this->assertSame(['Software Testing / QA', 'Cybersecurity'], $post->fresh()->skills);

        $edit([]);
        $this->assertSame(['Software Testing / QA', 'Cybersecurity'], $post->fresh()->skills);

        // Your Post shows them too.
        $this->actingAs($student, 'web')->get(route('student.team'))->assertOk()
            ->assertSee('<b>Skills</b>Software Testing / QA, Cybersecurity', false);
    }

    public function test_the_form_offers_the_students_programme_skills_without_grammar(): void
    {
        $this->actingAs($this->student('Ana', 'BSIT'), 'web')->get(route('student.team'))->assertOk()
            ->assertSee('Skills we need')
            ->assertSee("'Skills you have'", false)
            ->assertSee('Research & Writing')
            ->assertSee('value="UI/UX Design"', false)
            ->assertSee('value="Network Systems"', false)
            ->assertDontSee('value="Cataloguing &amp; Classification"', false)
            ->assertDontSee('value="Proofreading"', false);

        $this->actingAs($this->student('Lia', 'BLIS'), 'web')->get(route('student.team'))->assertOk()
            ->assertSee('value="Cataloguing &amp; Classification"', false)
            ->assertDontSee('value="Machine Learning"', false);
    }

    public function test_the_feed_shows_a_classmates_skills_and_filters_by_the_ones_in_use(): void
    {
        $this->publish($this->student('Ben'), ['UI/UX Design', 'Software Testing / QA']);

        $this->actingAs($this->student('Ana'), 'web')->get(route('student.team'))->assertOk()
            ->assertSee('data-skills="Software Testing / QA|UI/UX Design"', false)
            ->assertSee('<b>Skills</b>Software Testing / QA, UI/UX Design', false)
            ->assertSee('aria-label="Filter by skill"', false)
            ->assertSeeInOrder(['Any skill', '<option value="Software Testing / QA">', '<option value="UI/UX Design">'], false)
            ->assertDontSee('<option value="Web Development">', false);
    }

    public function test_a_feed_with_no_skills_has_no_skill_line_or_filter(): void
    {
        $this->publish($this->student('Ben'), []);

        $this->actingAs($this->student('Ana'), 'web')->get(route('student.team'))->assertOk()
            ->assertSee('data-skills=""', false)
            ->assertDontSee('<b>Skills</b>', false)
            ->assertDontSee('id="feed-skill-filter"', false);
    }
}
