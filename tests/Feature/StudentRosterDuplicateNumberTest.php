<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * A duplicate student number no longer 500s the whole student-list upload.
 *
 * students.student_number is unique, so a row carrying a number another
 * account already holds - the template's own example row is the usual
 * culprit - used to throw straight through to a "Server Error". The upload
 * now keeps importing, drops just that number, and tells the CaPSA/TSA which
 * rows it happened on.
 */
class StudentRosterDuplicateNumberTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        $capsa = CapstoneExpert::factory()->create([
            'approved_status' => true, 'is_admin' => false, 'sections' => ['BSIT-4A'],
        ]);

        $capsa->roles()->attach(Role::firstOrCreate(['role_name' => CapstoneExpert::CAPSA_ROLE])->role_id);

        return $capsa;
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function sheet(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray(
            [['Email', 'Last Name', 'First Name', 'Middle Name', 'Student No.', 'Course/Program', 'Year', 'Section'], ...$rows],
            null,
            'A1'
        );

        $path = tempnam(sys_get_temp_dir(), 'roster').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'students.xlsx', null, null, true);
    }

    public function test_a_row_reusing_another_accounts_number_imports_without_crashing(): void
    {
        Student::factory()->create([
            'email' => 'someone.else@my.cspc.edu.ph',
            'student_number' => '221001135',
        ]);

        $response = $this->actingAs($this->capsa(), 'expert')
            ->post(route('capsa.rosters.students.store'), [
                'file' => $this->sheet([
                    ['juan.delacruz@my.cspc.edu.ph', 'Dela Cruz', 'Juan', 'Bautista', '221001135', 'BSIT', 4, 'B'],
                ]),
            ])
            ->assertSuccessful();

        $response->assertJson(['success' => true, 'created' => 1]);

        // The new account exists but carries no number, since the number it
        // asked for belongs to someone else.
        $created = Student::where('email', 'juan.delacruz@my.cspc.edu.ph')->firstOrFail();
        $this->assertNull($created->student_number);

        // And the CaPSA/TSA is told, by row, rather than left with a crash.
        $errors = implode(' ', $response->json('errors'));
        $this->assertStringContainsString('221001135', $errors);
        $this->assertStringContainsString('another account', $errors);
        // Named, so the CaPSA/TSA can find the account that holds it.
        $this->assertStringContainsString('someone.else@my.cspc.edu.ph', $errors);
    }

    public function test_the_preview_warns_about_a_reused_number_before_upload(): void
    {
        Student::factory()->create([
            'email' => 'someone.else@my.cspc.edu.ph',
            'student_number' => '221001135',
        ]);

        $errors = $this->actingAs($this->capsa(), 'expert')
            ->post(route('capsa.rosters.students.preview'), [
                'file' => $this->sheet([
                    ['juan.delacruz@my.cspc.edu.ph', 'Dela Cruz', 'Juan', 'Bautista', '221001135', 'BSIT', 4, 'B'],
                ]),
            ])
            ->assertSuccessful()
            ->json('errors');

        $this->assertStringContainsString('221001135', implode(' ', $errors));
    }

    public function test_a_free_number_still_lands_on_the_new_account(): void
    {
        $response = $this->actingAs($this->capsa(), 'expert')
            ->post(route('capsa.rosters.students.store'), [
                'file' => $this->sheet([
                    ['maria.santos@my.cspc.edu.ph', 'Santos', 'Maria', 'Cruz', '221009999', 'BSIT', 4, 'B'],
                ]),
            ])
            ->assertSuccessful();

        $response->assertJson(['success' => true, 'created' => 1]);

        $this->assertSame('221009999', Student::where('email', 'maria.santos@my.cspc.edu.ph')->value('student_number'));
    }

    /** Google never issues "ñ", so "tobriñas" is read as the account "tobrinas". */
    public function test_an_accented_email_is_read_as_its_plain_google_address(): void
    {
        $response = $this->actingAs($this->capsa(), 'expert')
            ->post(route('capsa.rosters.students.store'), [
                'file' => $this->sheet([
                    ['Tobriñas@my.cspc.edu.ph', 'Tobriñas', 'Ana', 'Reyes', '221001140', 'BSIT', 4, 'A'],
                ]),
            ])
            ->assertSuccessful();

        $response->assertJson(['success' => true, 'created' => 1]);
        $this->assertTrue(Student::where('email', 'tobrinas@my.cspc.edu.ph')->exists());

        $errors = implode(' ', $response->json('errors'));
        $this->assertStringContainsString("read as 'tobrinas@my.cspc.edu.ph'", $errors);
        $this->assertStringNotContainsString('not a valid email', $errors);
    }

    public function test_an_email_that_is_still_invalid_is_skipped(): void
    {
        $response = $this->actingAs($this->capsa(), 'expert')
            ->post(route('capsa.rosters.students.store'), [
                'file' => $this->sheet([
                    ['tobriñas at my.cspc.edu.ph', 'Tobriñas', 'Ana', 'Reyes', '221001140', 'BSIT', 4, 'A'],
                ]),
            ])
            ->assertSuccessful();

        $this->assertSame(0, Student::count());
        $this->assertStringContainsString('not a valid email', implode(' ', $response->json('errors')));
    }
}
