<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminProfileController;
use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Support\SectionCatalog;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An Admin writes the department's sections down.
 *
 * Sections were worked out from the students enrolled in them, which is true
 * once a term is running and useless before it: a fresh system has no
 * students, so it offers no sections, so nobody can be made a CaPSA/TSA over one
 * - and a CaPSA/TSA is who uploads the students. This is the way out of that.
 */
class AdminSectionsSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): CapstoneExpert
    {
        return CapstoneExpert::create([
            'email' => 'admin.sections@test.com',
            'first_name' => 'Admin',
            'last_name' => 'Sections',
            'is_admin' => true,
            'approved_status' => true,
        ]);
    }

    /**
     * The knot this exists to cut: no students, therefore no sections,
     * therefore no CaPSA/TSA.
     */
    public function test_a_capsa_can_be_made_on_a_system_with_no_students(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->assertSame(0, Student::count());
        $this->assertSame([], AdminProfileController::getAllAvailableSections());

        // Adding one range of sections is the whole of the fix.
        $this->post(route('admin.settings.sections.store'), [
            'programs' => ['BSIT'],
            'from' => 'A',
            'to' => 'B',
        ])->assertRedirect();

        $this->assertSame(['BSIT-3A', 'BSIT-3B'], AdminProfileController::getAllAvailableSections());

        $this->postJson(route('admin.experts.capsa.store'), [
            'email' => 'first.capsa@cspc.edu.ph',
            'name' => 'Rosa Villanueva',
            'sections' => ['BSIT-3B'],
        ])->assertOk();

        $this->assertSame(['BSIT-3B'], CapstoneExpert::where('email', 'first.capsa@cspc.edu.ph')->first()->sections);
    }

    /** A range is a range: A through D is four, in order. */
    public function test_a_letter_range_makes_every_section_between(): void
    {
        $this->assertSame(
            ['BSIT-4A', 'BSIT-4B', 'BSIT-4C', 'BSIT-4D'],
            SectionCatalog::range('BSIT', 4, 'A', 'D')
        );
    }

    /** Given the range backwards, read it the way it was obviously meant. */
    public function test_a_backwards_range_is_read_the_right_way_round(): void
    {
        $this->assertSame(
            SectionCatalog::range('BSIT', 4, 'A', 'C'),
            SectionCatalog::range('BSIT', 4, 'C', 'A')
        );
    }

    /**
     * Several programs at once, each made for 3rd year only, where capstone
     * starts. Made for both years at once, every CaPSA/TSA looked like they ran
     * 3rd and 4th year together; a 4th-year section comes from the list.
     */
    public function test_programs_are_added_for_3rd_year_only(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->post(route('admin.settings.sections.store'), [
            'programs' => ['BSIT', 'BSIS'],
            'from' => 'A',
            'to' => 'B',
        ])->assertRedirect();

        $this->assertSame(['BSIS-3A', 'BSIS-3B', 'BSIT-3A', 'BSIT-3B'], SectionCatalog::defined());
        $this->get(route('admin.settings'))->assertOk()->assertSee('BSIS-3A');
    }

    /**
     * Adding a range twice does not list anything twice, and the order stays
     * readable - by program, then year, then letter.
     */
    public function test_adding_the_same_range_again_changes_nothing(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $payload = ['programs' => ['BSIT'], 'from' => 'A', 'to' => 'C'];

        $this->post(route('admin.settings.sections.store'), $payload);
        $this->post(route('admin.settings.sections.store'), $payload);

        $this->assertSame(['BSIT-3A', 'BSIT-3B', 'BSIT-3C'], SectionCatalog::defined());
    }

    /** A year level sent by an old form is ignored; there is no first-year capstone section. */
    public function test_a_year_level_is_no_longer_chosen(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->post(route('admin.settings.sections.store'), [
            'programs' => ['BSIT'], 'years' => [1], 'from' => 'A', 'to' => 'A',
        ])->assertRedirect();

        $this->assertSame(['BSIT-3A'], SectionCatalog::defined());
        $this->get(route('admin.settings'))->assertOk()->assertDontSee('Year levels');
    }

    /**
     * A section somebody is enrolled in is offered whether or not an Admin
     * wrote it down. This is what stops the change breaking a running term.
     */
    public function test_a_section_from_an_enrolled_student_is_still_offered(): void
    {
        Student::create([
            'email' => 'enrolled@my.cspc.edu.ph',
            'f_name' => 'En',
            'l_name' => 'Rolled',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '3rd '.json_decode('"·"').' A',
        ]);

        SectionCatalog::store(['BSIT-4B']);

        $this->assertSame(['BSCS-3A', 'BSIT-4B'], AdminProfileController::getAllAvailableSections());
    }

    /** A 4th-year section appears once the student list puts students in it. */
    public function test_a_4th_year_section_comes_from_the_student_list(): void
    {
        SectionCatalog::store(['BSIT-3A']);

        $this->assertSame(['BSIT-3A'], AdminProfileController::getAllAvailableSections());

        Student::create([
            'email' => 'promoted@my.cspc.edu.ph', 'f_name' => 'Pro', 'l_name' => 'Moted',
            'course' => 'Bachelor of Science in Information Technology', 'year_section' => '4th '.json_decode('"·"').' A',
        ]);

        $this->assertSame(['BSIT-3A', 'BSIT-4A'], AdminProfileController::getAllAvailableSections());
    }

    /** The 4th-year sections made in advance come off the list, unless a CaPSA/TSA holds one. */
    public function test_the_migration_drops_4th_year_sections_made_in_advance(): void
    {
        SectionCatalog::store(['BSIT-3A', 'BSIT-3B', 'BSIT-4A', 'BSIT-4B']);
        CapstoneExpert::factory()->create(['approved_status' => true, 'sections' => ['BSIT-4B']]);

        (require base_path('database/migrations/2026_09_24_094143_keep_only_starting_year_sections_in_settings.php'))->up();

        $this->assertSame(['BSIT-3A', 'BSIT-3B', 'BSIT-4B'], SectionCatalog::defined());
    }

    /** Removing one takes that one out and leaves the rest. */
    public function test_a_section_can_be_removed(): void
    {
        $this->actingAs($this->admin(), 'expert');

        SectionCatalog::store(['BSIT-4A', 'BSIT-4B', 'BSIT-4C']);

        $this->delete(route('admin.settings.sections.destroy'), ['section' => 'BSIT-4B'])
            ->assertRedirect();

        $this->assertSame(['BSIT-4A', 'BSIT-4C'], SectionCatalog::defined());

        $this->delete(route('admin.settings.sections.destroy'), ['section' => ['BSIT-4A', 'BSIT-4C']])
            ->assertRedirect();

        $this->assertSame([], SectionCatalog::defined());
    }

    /** Writing down the department's sections is Admin's, not a CaPSA/TSA's. */
    public function test_a_capsa_cannot_write_the_sections(): void
    {
        $capsa = $this->makeCapsa(CapstoneExpert::create([
            'email' => 'plain.capsa@gmail.com',
            'first_name' => 'Plain',
            'last_name' => 'Capsa',
            'approved_status' => true,
        ]));

        $this->actingAs($capsa, 'expert');

        $this->post(route('admin.settings.sections.store'), [
            'programs' => ['BSIT'], 'from' => 'A', 'to' => 'B',
        ])->assertForbidden();

        $this->assertSame([], SectionCatalog::defined());
    }

    /** A program that is not one of the four is refused. */
    public function test_an_unknown_program_is_refused(): void
    {
        $this->actingAs($this->admin(), 'expert');

        $this->post(route('admin.settings.sections.store'), [
            'programs' => ['BSHM'], 'from' => 'A', 'to' => 'B',
        ])->assertSessionHasErrors('programs.0');

        $this->assertSame([], SectionCatalog::defined());
    }

    /** The settings page shows the three parts apart, not as "BSIT-4A". */
    public function test_the_page_separates_course_year_and_section(): void
    {
        $this->actingAs($this->admin(), 'expert');

        SectionCatalog::store(['BSIT-4A', 'BSIT-4B']);

        $response = $this->get(route('admin.settings'));

        $response->assertOk();
        $response->assertSee('sec-prog">BSIT', false);
        $response->assertSee('4th year');
        $response->assertSee('4A · 4B');
        $response->assertDontSee('>BSIT-4A<', false);
    }
}
