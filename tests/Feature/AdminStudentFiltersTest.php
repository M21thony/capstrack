<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\StudentAccountsController;
use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Narrowing the admin student register.
 *
 * The register is one long list of everybody in the college, so the filters
 * are the whole way of using it. What is worth testing is that each one
 * actually excludes - a filter that quietly matches everything looks like it
 * worked and is the reason somebody trusts a list that is not the one they
 * asked for.
 */
class AdminStudentFiltersTest extends TestCase
{
    use RefreshDatabase;

    private int $issued = 0;

    private function admin(): CapstoneExpert
    {
        $admin = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => true,
            'sections' => [],
        ]);

        $admin->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $admin;
    }

    private function student(string $last, string $course, string $yearSection): Student
    {
        return Student::create([
            'email' => strtolower($last).'@my.cspc.edu.ph',
            // Counted, not drawn at random: the column is unique, and a
            // clash makes the run fail somewhere unrelated to what it tests.
            'student_number' => sprintf('21-%05d', ++$this->issued),
            'f_name' => 'Test',
            'l_name' => $last,
            'course' => $course,
            'year_section' => $yearSection,
        ]);
    }

    private function roster(): void
    {
        $this->student('Ancheta', 'BSIT', '4th · A');
        $this->student('Boado', 'BSIT', '3rd · B');
        $this->student('Calixto', 'BSCS', '4th · B');
        // Stored as the full course name rather than the code, which is how a
        // record that came in off a roster reads.
        $this->student('Dimaano', 'Bachelor of Science in Information Systems', '3rd · A');
    }

    public function test_the_program_filter_matches_the_code_and_the_full_course_name(): void
    {
        $this->roster();

        $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.students', ['program' => 'BSIT']))
            ->assertOk()
            ->assertSee('Ancheta')
            ->assertSee('Boado')
            ->assertDontSee('Calixto')
            ->assertDontSee('Dimaano');

        // The one whose course is spelled out in full still has to be found.
        $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.students', ['program' => 'BSIS']))
            ->assertOk()
            ->assertSee('Dimaano')
            ->assertDontSee('Ancheta');
    }

    public function test_the_year_filter_narrows_to_that_year(): void
    {
        $this->roster();

        $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.students', ['year' => '3rd']))
            ->assertOk()
            ->assertSee('Boado')
            ->assertSee('Dimaano')
            ->assertDontSee('Ancheta')
            ->assertDontSee('Calixto');
    }

    public function test_the_section_filter_narrows_to_that_letter(): void
    {
        $this->roster();

        $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.students', ['section' => 'B']))
            ->assertOk()
            ->assertSee('Boado')
            ->assertSee('Calixto')
            ->assertDontSee('Ancheta')
            ->assertDontSee('Dimaano');
    }

    public function test_the_filters_combine(): void
    {
        $this->roster();

        $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.students', ['program' => 'BSIT', 'year' => '4th', 'section' => 'A']))
            ->assertOk()
            ->assertSee('Ancheta')
            ->assertDontSee('Boado')
            ->assertDontSee('Calixto');
    }

    public function test_a_wildcard_in_the_section_is_not_a_wildcard(): void
    {
        $this->roster();

        // Straight into a LIKE, "%" would match every section at once - a
        // filter that says it narrowed and did not.
        $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.students', ['section' => '%']))
            ->assertOk()
            ->assertSee('Ancheta')
            ->assertSee('Boado');
    }

    public function test_only_the_two_capstone_years_are_offered(): void
    {
        $this->roster();

        $this->assertSame(['3rd', '4th'], StudentAccountsController::YEARS);

        $html = $this->actingAs($this->admin(), 'expert')->get(route('admin.students'))->getContent();

        $this->assertStringContainsString('value="3rd"', $html);
        $this->assertStringContainsString('value="4th"', $html);
        $this->assertStringNotContainsString('value="1st"', $html);
        $this->assertStringNotContainsString('value="2nd"', $html);
    }

    public function test_the_section_list_holds_only_letters_somebody_is_enrolled_in(): void
    {
        $this->roster();

        $html = $this->actingAs($this->admin(), 'expert')->get(route('admin.students'))->getContent();

        $this->assertStringContainsString('Section A', $html);
        $this->assertStringContainsString('Section B', $html);
        // The catalog runs A to Z; the filter must not.
        $this->assertStringNotContainsString('Section C', $html);
    }

    public function test_paging_keeps_the_filters(): void
    {
        for ($i = 1; $i <= 35; $i++) {
            $this->student('Surname'.$i, 'BSIT', '4th · A');
        }

        $html = $this->actingAs($this->admin(), 'expert')
            ->get(route('admin.students', ['program' => 'BSIT']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('program=BSIT', $html);
    }
}
