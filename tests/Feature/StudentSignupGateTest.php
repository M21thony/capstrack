<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A student's section has to be on a list before they can sign in.
 *
 * CaPSA asked for this: the department decides who is a student on which
 * course, in which section, and until this the site let anybody with a
 * my.cspc.edu.ph address in and made them an account on the spot.
 */
class StudentSignupGateTest extends TestCase
{
    use RefreshDatabase;

    /** Sign in the way the local mock flow does, state and all. */
    private function signIn(string $email, string $name = 'Some Student')
    {
        $state = 'test-state-'.bin2hex(random_bytes(4));

        return $this->withSession(['oauth_state' => $state])
            ->postJson(route('login.google.mock.post'), [
                'state' => $state,
                'email' => $email,
                'name' => $name,
            ]);
    }

    /** Nobody has listed them, so there is no account to sign in to. */
    public function test_a_student_on_no_list_is_turned_away(): void
    {
        $response = $this->signIn('stranger@my.cspc.edu.ph');

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('CaPSA', $response->json('message'));

        // And nothing was created on the way out.
        $this->assertNull(Student::where('email', 'stranger@my.cspc.edu.ph')->first());
        $this->assertGuest();
    }

    /** On the list CaPSA uploaded, so they are expected. */
    public function test_a_student_on_an_uploaded_list_gets_in(): void
    {
        StudentRoster::create([
            'email' => 'listed@my.cspc.edu.ph',
            'name' => 'Listed, Student',
            'student_number' => '221001135',
            'section' => '4th '.json_decode('"·"').' B',
            'program' => 'BSIT',
            'school_year' => '2026-2027',
        ]);

        $response = $this->signIn('listed@my.cspc.edu.ph', 'Listed Student');

        $response->assertOk();

        $student = Student::where('email', 'listed@my.cspc.edu.ph')->first();

        $this->assertNotNull($student);
        $this->assertAuthenticatedAs($student);

        // Pre-filling the academic fields from the list is the real Google
        // callback's job; this local mock only makes the account. What this
        // asserts is the gate: being on the list is what let them in.
    }

    /**
     * Somebody who was already using the site keeps their account.
     *
     * They were let in before this rule existed. Applying it to them would
     * lock out every student who has been working here all along.
     */
    public function test_an_existing_account_still_signs_in_without_a_list(): void
    {
        Student::create([
            'email' => 'already.here@my.cspc.edu.ph',
            'f_name' => 'Already',
            'l_name' => 'Here',
        ]);

        $this->assertSame(0, StudentRoster::count());

        $this->signIn('already.here@my.cspc.edu.ph')->assertOk();

        $this->assertAuthenticated();
    }

    /** The upload prepares the account, so signing in lands in that one. */
    public function test_signing_in_lands_in_the_account_the_upload_prepared(): void
    {
        $prepared = Student::create([
            'email' => 'prepared@my.cspc.edu.ph',
            'f_name' => 'Prepared',
            'l_name' => 'Account',
            'student_number' => '221001199',
        ]);

        $this->signIn('prepared@my.cspc.edu.ph')->assertOk();

        $this->assertSame(1, Student::where('email', 'prepared@my.cspc.edu.ph')->count());
        $this->assertAuthenticatedAs($prepared->fresh());
    }
}
