<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A CaPSA/TSA's reach: the sections they handle, and the one way out of them.
 *
 * The filter on the sections page listed every section in the college and,
 * worse, choosing one skipped the ownership check entirely - so any CaPSA/TSA
 * could read another's students by picking their section from a dropdown
 * that offered it. Moving a student the other way was refused outright,
 * which left a genuine transfer with nowhere to go.
 */
class CapsaSectionScopeAndTransferTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(array $sections = ['BSIT-4A']): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => $sections,
        ]);

        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    private function student(string $email, string $yearSection): Student
    {
        return Student::create([
            'email' => $email, 'f_name' => 'Angelo', 'l_name' => 'Bermudo',
            'course' => 'BSIT', 'year_section' => $yearSection,
        ]);
    }

    // ── the filter ────────────────────────────────────────────────────────

    /** The section pills are the sections they handle, not the college's. */
    public function test_the_filter_lists_only_their_own_sections(): void
    {
        $this->student('mine@my.cspc.edu.ph', '4th · A');
        $this->student('theirs@my.cspc.edu.ph', '4th · C');

        $html = $this->actingAs($this->capsa(['BSIT-4A', 'BSIT-4B']), 'expert')
            ->get(route('capsa.sections'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('section=BSIT-4A"', $html);
        $this->assertStringContainsString('section=BSIT-4B"', $html);
        $this->assertStringNotContainsString('section=BSIT-4C"', $html);
    }

    /**
     * Choosing a section used to skip the ownership check below it, so the
     * dropdown was not merely untidy - it was a way in.
     */
    public function test_asking_for_another_section_shows_nothing_of_it(): void
    {
        $mine = $this->student('mine@my.cspc.edu.ph', '4th · A');
        $theirs = $this->student('theirs@my.cspc.edu.ph', '4th · C');

        $html = $this->actingAs($this->capsa(['BSIT-4A']), 'expert')
            ->get(route('capsa.sections', ['section' => 'BSIT-4C']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($theirs->email, $html);
        // Falls back to their own rather than showing an empty page.
        $this->assertStringContainsString($mine->email, $html);
    }

    public function test_choosing_one_of_their_own_narrows_to_it(): void
    {
        $a = $this->student('a@my.cspc.edu.ph', '4th · A');
        $b = $this->student('b@my.cspc.edu.ph', '4th · B');

        $html = $this->actingAs($this->capsa(['BSIT-4A', 'BSIT-4B']), 'expert')
            ->get(route('capsa.sections', ['section' => 'BSIT-4A']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($a->email, $html);
        $this->assertStringNotContainsString($b->email, $html);
    }

    /** Required on edit as on Add Student: the number cannot be blanked. */
    public function test_an_edit_cannot_blank_the_student_number(): void
    {
        $student = $this->student('mine@my.cspc.edu.ph', '4th · A');
        $student->update(['student_number' => '221001138']);
        $capsa = $this->capsa(['BSIT-4A']);

        foreach ([null, ''] as $blank) {
            $this->actingAs($capsa, 'expert')
                ->patchJson(route('capsa.students.update', $student), ['f_name' => 'Angel', 'student_number' => $blank])
                ->assertStatus(422)
                ->assertJsonPath('message', 'Enter the student number.');
        }

        $this->assertSame('221001138', $student->fresh()->student_number);
    }

    // ── the transfer ──────────────────────────────────────────────────────

    /** Not by accident: the first attempt is refused and says why. */
    public function test_moving_a_student_out_asks_first(): void
    {
        $student = $this->student('mine@my.cspc.edu.ph', '4th · A');

        $this->actingAs($this->capsa(['BSIT-4A']), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['year' => 4, 'section' => 'C'])
            ->assertStatus(409)
            ->assertJson(['needs_transfer' => true, 'section' => 'BSIT-4C']);

        $this->assertSame('4th · A', $student->fresh()->year_section);
    }

    /** And goes through when the request says that is what it is. */
    public function test_a_confirmed_transfer_moves_them(): void
    {
        $student = $this->student('mine@my.cspc.edu.ph', '4th · A');

        $this->actingAs($this->capsa(['BSIT-4A']), 'expert')
            ->patchJson(route('capsa.students.update', $student), [
                'year' => 4, 'section' => 'C', 'transfer' => true,
            ])
            ->assertOk()
            ->assertJson(['transferred' => true]);

        $this->assertSame('4th · C', $student->fresh()->year_section);
    }

    /** An ordinary edit inside their own sections asks nothing. */
    public function test_an_edit_within_their_sections_is_not_a_transfer(): void
    {
        $student = $this->student('mine@my.cspc.edu.ph', '4th · A');

        $this->actingAs($this->capsa(['BSIT-4A', 'BSIT-4B']), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['year' => 4, 'section' => 'B'])
            ->assertOk()
            ->assertJson(['transferred' => false]);

        $this->assertSame('4th · B', $student->fresh()->year_section);
    }

    // ── removal, and the door it used to leave open ────────────────────────

    /**
     * The roster row is what admits somebody at sign-in - not the account.
     * Leaving it behind meant a student removed here signed in again and had
     * their section put straight back, which looked exactly like the removal
     * had never happened.
     */
    public function test_removing_a_student_withdraws_their_roster_entry(): void
    {
        $student = $this->student('mine@my.cspc.edu.ph', '4th · A');

        StudentRoster::create([
            'name' => 'Angelo Bermudo',
            'email' => $student->email,
            'student_number' => '21-04128',
            'program' => 'BSIT',
            'section' => '4th · A',
            'school_year' => '2025-2026',
        ]);

        $this->actingAs($this->capsa(['BSIT-4A']), 'expert')
            ->deleteJson(route('capsa.students.destroy', $student))
            ->assertOk();

        $this->assertNull($student->fresh()->year_section);
        $this->assertSame(0, StudentRoster::where('email', $student->email)->count());
    }
}
