<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin's unscoped list of student accounts.
 *
 * The page had no tests at all, and it is the one an Admin uses instead of
 * Student Section List - which lists only the sections the viewer covers,
 * and an Admin covers none.
 */
class AdminStudentAccountsPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => 'admin.students@test.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'is_admin' => true,
            'approved_status' => true,
        ]);
    }

    /**
     * Program, year and section read down three columns.
     *
     * They were one cell - the full course name and "4th · B" run together -
     * which reads as neither of them.
     */
    public function test_program_year_and_section_are_their_own_columns(): void
    {
        $this->actingAs($this->admin(), 'expert');

        Student::create([
            'email' => 'listed@my.cspc.edu.ph',
            'f_name' => 'John Kenneth',
            'l_name' => 'Aurillas',
            'student_number' => '221001135',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th '.json_decode('"·"').' B',
        ]);

        $response = $this->get(route('admin.students'));

        $response->assertOk();
        $response->assertSee('<th>Program</th>', false);
        $response->assertSee('<th>Year</th>', false);
        $response->assertSee('<th>Section</th>', false);
        $response->assertDontSee('<th>Course / Section</th>', false);

        // The code, not the whole course name, with the full one on hover.
        $response->assertSee('std-prog">BSIT', false);
        $response->assertSee('title="Bachelor of Science in Information Technology"', false);

        // Year and section apart, from the one string they are stored in.
        $response->assertSee('std-num">4th', false);
        $response->assertSee('std-num">B', false);
    }

    /**
     * A student who has not been on a list yet reads as having nothing set,
     * rather than as a blank the eye slides over.
     */
    public function test_a_student_with_nothing_set_shows_it(): void
    {
        $this->actingAs($this->admin(), 'expert');

        Student::create([
            'email' => 'early@my.cspc.edu.ph',
            'f_name' => 'Early',
            'l_name' => 'Bird',
        ]);

        $response = $this->get(route('admin.students'));

        $response->assertOk();
        $response->assertSee('early@my.cspc.edu.ph', false);
        // A literal em dash, not the entity - Blade prints the character.
        $response->assertSee('—', false);
    }

    /** Student Section List is the CaPSA's, so Admin is not offered it. */
    public function test_admin_is_not_offered_student_section_list(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->get(route('admin.students'))
            ->assertOk()
            ->assertDontSee('Student Section List', false);
    }

    /** And the page it pointed at is not reachable by an Admin either. */
    public function test_the_section_list_route_is_capsa_only(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->get(route('capsa.sections'))->assertForbidden();
    }
}
