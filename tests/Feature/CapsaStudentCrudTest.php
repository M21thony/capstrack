<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A CaPSA managing their own students.
 *
 * The rule under all of it is one sentence: a student is yours when the
 * section computed from their course and year is one of yours. What is worth
 * testing is that the rule holds at both ends of a change - you cannot reach
 * into a colleague's section, and you cannot push somebody into one either.
 */
class CapsaStudentCrudTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(array $sections = ['BSIT-4A']): CapstoneExpert
    {
        $expert = CapstoneExpert::factory()->create([
            'approved_status' => true,
            'is_admin' => false,
            'sections' => $sections,
        ]);

        $expert->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        return $expert;
    }

    private function student(string $course = 'BSIT', string $yearSection = '4th · A'): Student
    {
        return Student::create([
            'email' => 'a.bermudo@my.cspc.edu.ph',
            'student_number' => '21-04128',
            'f_name' => 'Angelo',
            'l_name' => 'Bermudo',
            'course' => $course,
            'year_section' => $yearSection,
        ]);
    }

    public function test_a_student_can_be_added_by_email(): void
    {
        $this->actingAs($this->capsa(), 'expert')
            ->postJson(route('capsa.students.store'), [
                'email' => 'k.delarosa@my.cspc.edu.ph',
                'f_name' => 'Kristine',
                'l_name' => 'Dela Rosa',
                'student_number' => '21-03994',
                'course' => 'BSIT',
                'year' => 4,
                'section' => 'A',
            ])
            ->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertSame('Kristine', Student::where('email', 'k.delarosa@my.cspc.edu.ph')->value('f_name'));
    }

    public function test_a_student_cannot_be_added_to_a_section_you_do_not_handle(): void
    {
        $this->actingAs($this->capsa(['BSIT-4A']), 'expert')
            ->postJson(route('capsa.students.store'), [
                'email' => 'x@my.cspc.edu.ph',
                'f_name' => 'X', 'l_name' => 'Y',
                'course' => 'BSIT', 'year' => 4, 'section' => 'B',
            ])
            ->assertStatus(422);

        $this->assertSame(0, Student::count());
    }

    /** Somebody already in a section is not added a second time. */
    public function test_a_student_already_in_a_section_is_not_added_again(): void
    {
        $existing = $this->student();

        $this->actingAs($this->capsa(), 'expert')
            ->postJson(route('capsa.students.store'), [
                'email' => $existing->email,
                'f_name' => 'Someone', 'l_name' => 'Else',
                'course' => 'BSIT', 'year' => 4, 'section' => 'A',
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        $this->assertSame(1, Student::where('email', $existing->email)->count());
        // And not quietly renamed by the attempt.
        $this->assertSame('Angelo', $existing->fresh()->f_name);
    }

    /** Nor is somebody another CaPSA is handling. */
    public function test_a_student_in_another_section_cannot_be_taken(): void
    {
        $theirs = $this->student('BSIT', '4th · C');

        $this->actingAs($this->capsa(['BSIT-4A']), 'expert')
            ->postJson(route('capsa.students.store'), [
                'email' => $theirs->email,
                'f_name' => 'Angelo', 'l_name' => 'Bermudo',
                'course' => 'BSIT', 'year' => 4, 'section' => 'A',
            ])
            ->assertStatus(422);

        $this->assertSame('4th · C', $theirs->fresh()->year_section);
    }

    /**
     * Removing a student keeps their account and only clears the section, so
     * the address is still in the table afterwards. Adding refused any
     * address already there, which made a removal impossible to undo: the
     * CaPSA was told the email already exists, with no way forward.
     */
    public function test_a_removed_student_can_be_added_back(): void
    {
        $student = $this->student();
        $capsa = $this->capsa();

        $this->actingAs($capsa, 'expert')
            ->deleteJson(route('capsa.students.destroy', $student))
            ->assertOk();

        $this->assertNull($student->fresh()->year_section);

        $this->actingAs($capsa, 'expert')
            ->postJson(route('capsa.students.store'), [
                'email' => $student->email,
                'f_name' => 'Angelo', 'l_name' => 'Bermudo',
                'course' => 'BSIT', 'year' => 4, 'section' => 'A',
            ])
            ->assertOk()
            ->assertJsonFragment(['readded' => true]);

        $this->assertSame('4th · A', $student->fresh()->year_section);
        // The same person, not a second account for them.
        $this->assertSame(1, Student::where('email', $student->email)->count());
        $this->assertSame($student->student_id, Student::where('email', $student->email)->first()->student_id);
    }

    /** Only the years that take capstone can be assigned. */
    public function test_a_student_cannot_be_put_in_first_or_second_year(): void
    {
        $capsa = $this->capsa(['BSIT-1A', 'BSIT-4A']);

        $this->actingAs($capsa, 'expert')
            ->postJson(route('capsa.students.store'), [
                'email' => 'new.student@my.cspc.edu.ph',
                'f_name' => 'New', 'l_name' => 'Student',
                'course' => 'BSIT', 'year' => 1, 'section' => 'A',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('year');

        $this->assertSame(3, Student::CAPSTONE_YEARS[0]);
        $this->assertSame(4, Student::CAPSTONE_YEARS[1]);
    }

    public function test_a_section_can_be_changed(): void
    {
        $student = $this->student();

        $this->actingAs($this->capsa(['BSIT-4A', 'BSIT-4B']), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['year' => 4, 'section' => 'B'])
            ->assertOk();

        $this->assertSame('4th · B', $student->fresh()->year_section);
    }

    public function test_a_partial_change_leaves_everything_else_alone(): void
    {
        $student = $this->student();

        // Setting a section for a group sends only the section; the names and
        // the number must survive it.
        $this->actingAs($this->capsa(['BSIT-4A', 'BSIT-4B']), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['year' => 4, 'section' => 'B'])
            ->assertOk();

        $fresh = $student->fresh();
        $this->assertSame('Angelo', $fresh->f_name);
        $this->assertSame('21-04128', $fresh->student_number);
    }

    public function test_you_cannot_touch_a_student_in_someone_elses_section(): void
    {
        $student = $this->student('BSIT', '3rd · A');

        $this->actingAs($this->capsa(['BSIT-4A']), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['f_name' => 'Changed'])
            ->assertStatus(403);

        $this->assertSame('Angelo', $student->fresh()->f_name);
    }

    public function test_you_cannot_push_a_student_into_someone_elses_section(): void
    {
        $student = $this->student();

        // Reachable now, but the change would put them beyond reach - so it is
        // refused rather than performed and then unfixable.
        $this->actingAs($this->capsa(['BSIT-4A']), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['year' => 3, 'section' => 'A'])
            ->assertStatus(422);

        $this->assertSame('4th · A', $student->fresh()->year_section);
    }

    public function test_removing_takes_them_off_the_roster_and_keeps_the_account(): void
    {
        $student = $this->student();

        $this->actingAs($this->capsa(), 'expert')
            ->deleteJson(route('capsa.students.destroy', $student))
            ->assertOk();

        $fresh = $student->fresh();

        // The account survives, because their team history and submissions
        // hang off it.
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->course);
        $this->assertNull($fresh->year_section);
        $this->assertSame('a.bermudo@my.cspc.edu.ph', $fresh->email);
    }

    public function test_an_admin_is_not_scoped(): void
    {
        $student = $this->student('BSIT', '3rd · A');

        $admin = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => true, 'sections' => [],
        ]);
        $admin->roles()->attach(Role::firstOrCreate(['role_name' => 'capstone_adviser'])->role_id);

        $this->actingAs($admin, 'expert')
            ->patchJson(route('capsa.students.update', $student), ['f_name' => 'Changed'])
            ->assertOk();

        $this->assertSame('Changed', $student->fresh()->f_name);
    }
}
