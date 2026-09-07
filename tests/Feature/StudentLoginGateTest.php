<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Who is allowed to sign in as a student.
 *
 * The class list is the answer. A student is on it because a CaPSA put them
 * there, and taking them off it is how access is withdrawn - which is the
 * only removal the system actually has.
 *
 * The gate used to admit anyone with EITHER a roster row OR a students row,
 * which meant an account, once created, was permanent: removing somebody
 * from their section deleted their roster row and let them straight back in
 * on the students row instead. Nothing revoked anything.
 */
class StudentLoginGateTest extends TestCase
{
    use RefreshDatabase;

    /** Calls the private gate the Google callback uses. */
    private function gate(string $email): ?Student
    {
        $controller = app(\App\Http\Controllers\AuthController::class);
        $method = new \ReflectionMethod($controller, 'studentSigningIn');
        $method->setAccessible(true);

        return $method->invoke($controller, $email, 'Jo', 'Aurilla', null);
    }

    private function roster(string $email): StudentRoster
    {
        return StudentRoster::create([
            'email' => $email, 'name' => 'Jo Aurilla',
            'school_year' => '2026-2027', 'section' => 'A', 'program' => 'BSIT',
        ]);
    }

    /** On the list, first time: an account is made for them. */
    public function test_a_listed_student_gets_an_account(): void
    {
        $this->roster('joaurilla@my.cspc.edu.ph');

        $student = $this->gate('joaurilla@my.cspc.edu.ph');

        $this->assertNotNull($student);
        $this->assertSame('joaurilla@my.cspc.edu.ph', $student->email);
    }

    /** Not on the list at all: refused. */
    public function test_a_stranger_is_refused(): void
    {
        $this->assertNull($this->gate('nobody@my.cspc.edu.ph'));
    }

    /**
     * The one that was broken. Taking a student off the list is how a CaPSA
     * withdraws access, and it has to hold even though the account they
     * already made is still sitting there.
     */
    public function test_a_student_taken_off_the_list_cannot_sign_back_in(): void
    {
        $roster = $this->roster('joaurilla@my.cspc.edu.ph');
        $student = $this->gate('joaurilla@my.cspc.edu.ph');
        $this->assertNotNull($student, 'they should have got in the first time');

        $roster->delete();

        $this->assertNotNull(
            Student::whereRaw('LOWER(email) = ?', ['joaurilla@my.cspc.edu.ph'])->first(),
            'their account is deliberately kept - work and history hang off it'
        );
        $this->assertNull(
            $this->gate('joaurilla@my.cspc.edu.ph'),
            'off the list is off the system, whatever account rows remain'
        );
    }

    /** Address case is not a way back in. */
    public function test_the_list_is_matched_regardless_of_case(): void
    {
        $this->roster('joaurilla@my.cspc.edu.ph');

        $this->assertNotNull($this->gate('JoAurilla@my.cspc.edu.ph'));
    }

    /** Removing them through the CaPSA page withdraws the invitation. */
    public function test_the_capsa_removal_takes_them_off_the_list(): void
    {
        $this->roster('joaurilla@my.cspc.edu.ph');
        $student = $this->gate('joaurilla@my.cspc.edu.ph');
        $student->update(['course' => 'BSIT', 'year_section' => '4th · A']);

        $capsa = \App\Models\CapstoneExpert::factory()->create(['sections' => ['BSIT-4A']]);
        $capsa->roles()->attach(\App\Models\Role::firstOrCreate(['role_name' => \App\Models\CapstoneExpert::CAPSA_ROLE])->role_id);

        $this->actingAs($capsa, 'expert')->deleteJson('/capsa/students/'.$student->student_id);

        $this->assertNull($this->gate('joaurilla@my.cspc.edu.ph'));
    }
}
