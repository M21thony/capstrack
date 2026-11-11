<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who is allowed to sign in as a student.
 *
 * The class list is the answer. A student is on it because a CaPSA/TSA put them
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
        $controller = app(AuthController::class);
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
     * The one that was broken. Taking a student off the list is how a CaPSA/TSA
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

    /** A CaPSA/TSA who handles BSIT 3B, as the page would have them. */
    private function capsaFor(string $section): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create(['sections' => [$section]]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    /**
     * Signed in before any list was uploaded: an account and a section, so
     * their CaPSA/TSA sees them in the section - but no list row, so sign-in
     * told them they were not on it.
     */
    private function studentFromBeforeTheList(string $email = 'jorabusa@my.cspc.edu.ph', ?string $yearSection = '3rd · B'): Student
    {
        return Student::factory()->create([
            'email' => $email,
            'f_name' => 'Jolina',
            'l_name' => 'Rabusa',
            'student_number' => $email === 'jorabusa@my.cspc.edu.ph' ? '221001130' : fake()->unique()->numerify('#########'),
            'course' => 'BSIT',
            'year_section' => $yearSection,
        ]);
    }

    private function runBackfill(): void
    {
        $migration = require database_path('migrations/2026_09_19_181703_add_missing_roster_rows_for_sectioned_students.php');
        $migration->up();
    }

    public function test_a_student_their_capsa_can_see_is_put_back_on_the_list(): void
    {
        $this->studentFromBeforeTheList();
        $this->assertNull($this->gate('jorabusa@my.cspc.edu.ph'), 'the reported bug');

        $this->runBackfill();

        $this->assertNotNull($this->gate('jorabusa@my.cspc.edu.ph'));
        $this->assertDatabaseHas('student_rosters', [
            'email' => 'jorabusa@my.cspc.edu.ph',
            'section' => '3rd · B',
            'program' => 'BSIT',
            'student_number' => '221001130',
        ]);
    }

    public function test_the_backfill_leaves_removed_students_out_and_listed_ones_alone(): void
    {
        // Removed by their CaPSA/TSA: section cleared, list row gone.
        $this->studentFromBeforeTheList('removed@my.cspc.edu.ph', null);
        // Already listed, under a different case.
        $this->roster('Listed@my.cspc.edu.ph');
        $this->studentFromBeforeTheList('listed@my.cspc.edu.ph');

        $this->runBackfill();

        $this->assertNull($this->gate('removed@my.cspc.edu.ph'), 'removal still has to hold');
        $this->assertSame(1, StudentRoster::whereRaw('LOWER(email) = ?', ['listed@my.cspc.edu.ph'])->count());
    }

    public function test_a_capsa_saving_a_student_puts_them_on_the_list(): void
    {
        $student = $this->studentFromBeforeTheList();

        $this->actingAs($this->capsaFor('BSIT-3B'), 'expert')
            ->patchJson('/capsa/students/'.$student->student_id, ['section' => 'C'])
            ->assertStatus(409); // BSIT-3C is not theirs; nothing is saved

        $this->assertSame(0, StudentRoster::count());

        $this->actingAs($this->capsaFor('BSIT-3B'), 'expert')
            ->patchJson('/capsa/students/'.$student->student_id, ['f_name' => 'Jolina Mae'])
            ->assertOk();

        $this->assertNotNull($this->gate('jorabusa@my.cspc.edu.ph'));
        $this->assertDatabaseHas('student_rosters', ['email' => 'jorabusa@my.cspc.edu.ph', 'name' => 'Jolina Mae Rabusa', 'section' => '3rd · B']);
    }

    public function test_the_list_follows_a_section_change(): void
    {
        $this->roster('jorabusa@my.cspc.edu.ph');
        $student = $this->studentFromBeforeTheList();

        $this->actingAs($this->capsaFor('BSIT-3B'), 'expert')
            ->patchJson('/capsa/students/'.$student->student_id, ['section' => 'C', 'transfer' => true])
            ->assertOk();

        $this->assertSame(1, StudentRoster::count());
        $this->assertDatabaseHas('student_rosters', ['email' => 'jorabusa@my.cspc.edu.ph', 'section' => '3rd · C']);
        $this->assertNotNull($this->gate('jorabusa@my.cspc.edu.ph'), 'a transfer is not a removal');
    }

    /** Removing them through the CaPSA/TSA page withdraws the invitation. */
    public function test_the_capsa_removal_takes_them_off_the_list(): void
    {
        $this->roster('joaurilla@my.cspc.edu.ph');
        $student = $this->gate('joaurilla@my.cspc.edu.ph');
        $student->update(['course' => 'BSIT', 'year_section' => '4th · A']);

        $capsa = CapstoneExpert::factory()->create(['sections' => ['BSIT-4A']]);
        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        $this->actingAs($capsa, 'expert')->deleteJson('/capsa/students/'.$student->student_id);

        $this->assertNull($this->gate('joaurilla@my.cspc.edu.ph'));
    }
}
