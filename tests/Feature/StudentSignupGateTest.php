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

        // The list already said who they are, so the account arrives filled
        // in rather than empty - and that is true of this local mock as much
        // as of the real Google callback, because both go through the one
        // method that decides it.
        $this->assertSame('221001135', $student->student_number);
        $this->assertSame('Bachelor of Science in Information Technology', $student->course);
        $this->assertSame('4th '.json_decode('"·"').' B', $student->year_section);
    }

    /**
     * An account on its own is not a way in.
     *
     * This used to say the opposite: anyone holding a students row signed in
     * whether or not they were listed. It was written to grandfather people
     * who predated the list, and it quietly made every account permanent -
     * removing a student deletes their roster row, and they came straight
     * back in on the account instead, so nothing revoked anything. By the
     * time it was found there was nobody left to grandfather: every student
     * in the database was on the list.
     */
    public function test_an_account_without_a_listing_is_turned_away(): void
    {
        Student::create([
            'email' => 'already.here@my.cspc.edu.ph',
            'f_name' => 'Already',
            'l_name' => 'Here',
        ]);

        $this->assertSame(0, StudentRoster::count());

        $this->signIn('already.here@my.cspc.edu.ph')->assertStatus(403);

        $this->assertGuest();
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

        // The upload writes both - the listing is what admits them, the
        // account is what they land in.
        StudentRoster::create([
            'email' => 'prepared@my.cspc.edu.ph', 'name' => 'Prepared Account',
            'student_number' => '221001199', 'section' => '4th B',
            'program' => 'BSIT', 'school_year' => Student::schoolYearLabel(),
        ]);

        $this->signIn('prepared@my.cspc.edu.ph')->assertOk();

        $this->assertSame(1, Student::where('email', 'prepared@my.cspc.edu.ph')->count());
        $this->assertAuthenticatedAs($prepared->fresh());
    }
}
