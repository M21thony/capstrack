<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The program and "without a team" filters on the CaPSA's section list.
 *
 * They narrow the rows in the browser rather than on the server, so what the
 * page has to get right is the data behind each row: the filter reads
 * data-program and data-team off the row and nothing else. Drop either
 * attribute and the filter still runs, still reports a result, and quietly
 * hides everybody - which is why they are worth pinning down here.
 */
class CapsaSectionFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => false,
            'sections' => ['BSIT-4A'],
        ]);

        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $capsa;
    }

    public function test_every_row_carries_what_the_filters_read(): void
    {
        Student::create([
            'email' => 'a.reyes@my.cspc.edu.ph',
            'student_number' => '21-04128',
            'f_name' => 'Ana',
            'l_name' => 'Reyes',
            'course' => 'BSIT',
            'year_section' => '4th · A',
        ]);

        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.sections'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-program="BSIT"', $html);
        // Nobody is on a team yet, so the row reads as one without.
        $this->assertStringContainsString('data-team="0"', $html);

        // And the controls that read them.
        $this->assertStringContainsString('id="program-filter"', $html);
        $this->assertStringContainsString('id="team-filter"', $html);
    }

    public function test_the_program_list_holds_only_programs_on_the_page(): void
    {
        Student::create([
            'email' => 'b.cruz@my.cspc.edu.ph',
            'f_name' => 'Ben', 'l_name' => 'Cruz',
            'course' => 'BSIT', 'year_section' => '4th · A',
        ]);

        $html = $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.sections'))
            ->assertOk()
            ->getContent();

        // Read the filter's own options - every program in the college is
        // listed elsewhere on the page, in the editors that assign one.
        $this->assertSame(1, preg_match('/id="program-filter".*?<\/select>/s', $html, $m));

        $this->assertStringContainsString('<option value="BSIT">BSIT</option>', $m[0]);
        // A CaPSA who handles one program should not be offered the other three.
        $this->assertStringNotContainsString('BLIS', $m[0]);
    }
}
