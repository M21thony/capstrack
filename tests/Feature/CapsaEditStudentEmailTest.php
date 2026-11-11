<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A CaPSA/TSA correcting the address a student signs in with.
 *
 * Sign-in finds a student by email, so the address has to move together with
 * the class-list entry that admits them - otherwise the new address is turned
 * away and the old one still builds a second, empty account.
 */
class CapsaEditStudentEmailTest extends TestCase
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

    private function student(string $email = 'a.bermudo@my.cspc.edu.ph', string $number = '231002054'): Student
    {
        $student = Student::create([
            'email' => $email,
            'student_number' => $number,
            'f_name' => 'Angelo',
            'l_name' => 'Bermudo',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th · A',
        ]);

        StudentRoster::create([
            'email' => $email,
            'name' => 'Angelo Bermudo',
            'student_number' => $number,
            'section' => '4th · A',
            'program' => 'BSIT',
            'school_year' => '2026-2027',
        ]);

        return $student;
    }

    private function signInWithGoogle(string $email)
    {
        // A fresh visitor: the CaPSA/TSA who made the change is not the one signing in.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->flushSession();
        $this->withSession(['oauth_state' => 'state-1']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::response(['email' => $email, 'given_name' => 'Angelo', 'family_name' => 'Bermudo']),
        ]);

        return $this->get('/login/google/callback?state=state-1&code=c');
    }

    public function test_the_student_signs_in_with_the_new_address_into_the_same_account(): void
    {
        $student = $this->student();

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['email' => 'angelo.bermudo@my.cspc.edu.ph'])
            ->assertOk();

        $this->signInWithGoogle('angelo.bermudo@my.cspc.edu.ph')->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student->fresh(), 'web');
        $this->assertSame(1, Student::count());
    }

    public function test_a_capsa_can_change_a_students_email(): void
    {
        $student = $this->student();

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['email' => 'angelo.bermudo@my.cspc.edu.ph'])
            ->assertOk()
            ->assertJsonPath('student.email', 'angelo.bermudo@my.cspc.edu.ph');

        $this->assertSame($student->student_id, Student::where('email', 'angelo.bermudo@my.cspc.edu.ph')->value('student_id'));
    }

    public function test_the_class_list_entry_moves_to_the_new_address(): void
    {
        $student = $this->student();

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['email' => 'angelo.bermudo@my.cspc.edu.ph'])
            ->assertOk();

        $this->assertDatabaseHas(StudentRoster::class, ['email' => 'angelo.bermudo@my.cspc.edu.ph', 'student_number' => '231002054']);
        $this->assertDatabaseMissing(StudentRoster::class, ['email' => 'a.bermudo@my.cspc.edu.ph']);
    }

    public function test_an_address_already_on_the_new_list_leaves_one_entry(): void
    {
        $student = $this->student();
        StudentRoster::create(['email' => 'angelo.bermudo@my.cspc.edu.ph', 'name' => 'Angelo Bermudo', 'section' => '4th · A', 'program' => 'BSIT', 'school_year' => '2026-2027']);

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['email' => 'angelo.bermudo@my.cspc.edu.ph'])
            ->assertOk();

        $this->assertSame(1, StudentRoster::where('email', 'angelo.bermudo@my.cspc.edu.ph')->count());
        $this->assertDatabaseMissing(StudentRoster::class, ['email' => 'a.bermudo@my.cspc.edu.ph']);
    }

    public function test_an_address_another_student_has_is_refused_and_names_them(): void
    {
        $student = $this->student();
        Student::create(['email' => 'n.enriquez@my.cspc.edu.ph', 'student_number' => '221001155', 'f_name' => 'Nadine', 'l_name' => 'Enriquez']);

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['email' => 'N.Enriquez@my.cspc.edu.ph'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'That address already belongs to Nadine Enriquez (221001155).');

        $this->assertSame('a.bermudo@my.cspc.edu.ph', $student->fresh()->email);
    }

    public function test_a_non_school_address_is_refused(): void
    {
        $student = $this->student();

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['email' => 'angelo@gmail.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertSame('a.bermudo@my.cspc.edu.ph', $student->fresh()->email);
    }

    public function test_a_blank_address_is_refused(): void
    {
        $student = $this->student();

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['email' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_the_address_is_saved_lower_case_trimmed_and_without_accents(): void
    {
        $student = $this->student();

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['email' => '  Tobriñas@MY.cspc.edu.ph '])
            ->assertOk();

        $this->assertSame('tobrinas@my.cspc.edu.ph', $student->fresh()->email);
    }

    public function test_saving_the_same_address_changes_nothing_else(): void
    {
        $student = $this->student();

        $this->actingAs($this->capsa(), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['email' => 'a.bermudo@my.cspc.edu.ph', 'f_name' => 'Angel'])
            ->assertOk();

        $this->assertSame('Angel', $student->fresh()->f_name);
        $this->assertSame(1, StudentRoster::where('email', 'a.bermudo@my.cspc.edu.ph')->count());
    }

    public function test_a_student_in_another_capsas_section_cannot_have_their_email_changed(): void
    {
        $student = $this->student();

        $this->actingAs($this->capsa(['BSIT-4B']), 'expert')
            ->patchJson(route('capsa.students.update', $student), ['email' => 'angelo.bermudo@my.cspc.edu.ph'])
            ->assertForbidden();

        $this->assertSame('a.bermudo@my.cspc.edu.ph', $student->fresh()->email);
    }

    public function test_the_edit_form_has_an_email_box_with_the_school_domain(): void
    {
        $this->student();

        $this->actingAs($this->capsa(), 'expert')
            ->get(route('capsa.sections'))
            ->assertOk()
            ->assertSee('id="edit-student-email"', false)
            ->assertSee("schoolEmailBox('edit-student-email')", false)
            ->assertSee("schoolEmailBox('add-student-email')", false)
            ->assertSee('@my.cspc.edu.ph', false);
    }
}
