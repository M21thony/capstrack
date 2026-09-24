<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Two ways a listed student's sign-in ended on a 500.
 *
 * A listed student signing in for the first time gets an account made from
 * their class-list row. Student numbers are unique, so a number already on
 * another account - a student added by hand under another address, a typo on
 * one list - made that insert fail, and a listed student saw a 500 and could
 * not get in at all.
 */
class StudentSignInNumberClashTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $email)
    {
        $this->withSession(['oauth_state' => 'state-1']);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'a', 'token_type' => 'Bearer', 'expires_in' => 3600]),
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::response(['email' => $email, 'given_name' => 'Juan', 'family_name' => 'Dela Cruz']),
        ]);

        return $this->get('/login/google/callback?state=state-1&code=c');
    }

    public function test_a_number_already_on_another_account_does_not_stop_the_sign_in(): void
    {
        Student::factory()->create(['email' => 'j.delacruz@my.cspc.edu.ph', 'student_number' => '2023-00419']);
        StudentRoster::create([
            'email' => 'juan.delacruz@my.cspc.edu.ph', 'name' => 'Dela Cruz, Juan', 'student_number' => '2023-00419',
            'section' => '4th · B', 'program' => 'BSIT', 'school_year' => '2026-2027',
        ]);

        $this->signIn('juan.delacruz@my.cspc.edu.ph')->assertRedirect(route('student.dashboard'));

        $student = Student::where('email', 'juan.delacruz@my.cspc.edu.ph')->first();
        $this->assertNotNull($student);
        $this->assertAuthenticatedAs($student);
        // The number stays with the account that already had it.
        $this->assertNull($student->student_number);
        $this->assertSame('Bachelor of Science in Information Technology', $student->course);
    }

    public function test_a_listed_student_still_gets_their_number(): void
    {
        StudentRoster::create([
            'email' => 'ana.reyes@my.cspc.edu.ph', 'name' => 'Reyes, Ana', 'student_number' => '2023-00420',
            'section' => '4th · B', 'program' => 'BSIT', 'school_year' => '2026-2027',
        ]);

        $this->signIn('ana.reyes@my.cspc.edu.ph')->assertRedirect(route('student.dashboard'));

        $this->assertSame('2023-00420', Student::where('email', 'ana.reyes@my.cspc.edu.ph')->value('student_number'));
    }

    public function test_google_not_answering_sends_them_back_to_sign_in_instead_of_a_500(): void
    {
        $this->withSession(['oauth_state' => 'state-2']);
        Http::fake(['oauth2.googleapis.com/token' => fn () => throw new ConnectionException('cURL error 28: timed out')]);

        $this->get('/login/google/callback?state=state-2&code=c')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['message' => 'Google did not answer in time. Please sign in again.']);
        $this->assertGuest();
    }
}
