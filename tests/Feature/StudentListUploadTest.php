<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * A CaPSA uploads the department's student list.
 *
 * The point is the students who signed in before the list existed: they have
 * no student number, course or section on their profile, because nothing had
 * ever told the site what those were.
 */
class StudentListUploadTest extends TestCase
{
    use RefreshDatabase;

    private function capsa(): CapstoneExpert
    {
        return $this->makeCapsa(CapstoneExpert::create([
            'email' => 'capsa.list@test.com',
            'first_name' => 'Cap',
            'last_name' => 'Sa',
            'is_admin' => true,
            'approved_status' => true,
            'sections' => ['BSIT-4B'],
        ]));
    }

    /** Build a real .xlsx, the way the department's list is shaped. */
    private function listFile(array $header, array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([$header], null, 'A1');
        $sheet->fromArray($rows, null, 'A2');

        $path = tempnam(sys_get_temp_dir(), 'list').'.xlsx';
        (new Xlsx($book))->save($path);

        return new UploadedFile($path, 'Student List.xlsx', null, null, true);
    }

    private const HEADER = ['Email', 'Name', 'Student No.', 'Course/Program', 'Year', 'Section'];

    public function test_it_fills_in_the_blanks_on_an_account_that_signed_in_first(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        // Signed in with Google and nothing else - which is all a student has
        // to do, and is exactly why these are empty.
        $student = Student::create([
            'email' => 'early@my.cspc.edu.ph',
            'f_name' => 'Early',
            'l_name' => 'Bird',
        ]);

        $this->assertNull($student->student_number);
        $this->assertNull($student->course);
        $this->assertNull($student->year_section);

        $response = $this->post(route('admin.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER, [
                ['early@my.cspc.edu.ph', 'Bird, Early A.', '221001135', 'BSIT', 4, 'B'],
            ]),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'inserted' => 1, 'matched' => 1, 'skipped' => 0]);

        $student->refresh();
        $this->assertSame('221001135', $student->student_number);
        $this->assertSame('Bachelor of Science in Information Technology', $student->course);
        $this->assertSame('4th '.json_decode('"·"').' B', $student->year_section);

        // The name came from Google when they signed in, so it was never a
        // blank and the list does not get to rewrite it.
        $this->assertSame('Early', $student->f_name);
        $this->assertSame('Bird', $student->l_name);
    }

    /**
     * Columns are read by their headings. Read by position, a list in any
     * other order put the email in the name field and the section in the
     * course - silently, because every value is just a string.
     */
    public function test_the_columns_can_be_in_any_order(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $student = Student::create(['email' => 'shuffled@my.cspc.edu.ph', 'f_name' => 'S', 'l_name' => 'H']);

        $this->post(route('admin.rosters.students.store'), [
            'file' => $this->listFile(
                ['Section', 'Course/Program', 'Name', 'Year', 'Email', 'Student No.'],
                [['A', 'BSCS', 'Cruz, Juan D.', 3, 'shuffled@my.cspc.edu.ph', '221000001']]
            ),
        ])->assertOk();

        $student->refresh();
        $this->assertSame('Bachelor of Science in Computer Science', $student->course);
        $this->assertSame('3rd '.json_decode('"·"').' A', $student->year_section);
        $this->assertSame('221000001', $student->student_number);
    }

    /**
     * The upload has to work for students who have not signed in yet - that
     * is most of a list at the start of a semester. The account is prepared
     * from the row, so it is ready and filled in before they ever arrive.
     */
    public function test_it_prepares_an_account_for_someone_who_has_not_signed_in(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->assertNull(Student::where('email', 'nobody@my.cspc.edu.ph')->first());

        $response = $this->post(route('admin.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER, [
                ['nobody@my.cspc.edu.ph', 'Nobody, At All B.', '221000002', 'BSIT', 4, 'B'],
            ]),
        ]);

        $response->assertOk();
        $response->assertJson(['created' => 1, 'matched' => 0, 'skipped' => 0]);

        $student = Student::where('email', 'nobody@my.cspc.edu.ph')->first();

        $this->assertNotNull($student, 'No account was prepared for them.');
        $this->assertSame('221000002', $student->student_number);
        $this->assertSame('Bachelor of Science in Information Technology', $student->course);
        $this->assertSame('4th '.json_decode('"\u00b7"').' B', $student->year_section);
        $this->assertSame('Nobody', $student->l_name);
        $this->assertSame('At All B.', $student->f_name);

        $this->assertSame(1, StudentRoster::where('email', 'nobody@my.cspc.edu.ph')->count());
    }

    /**
     * A second upload of the same list must not make a second account - the
     * first one now exists, so the row matches it instead.
     */
    public function test_a_prepared_account_is_not_made_twice(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $row = [['twice@my.cspc.edu.ph', 'Twice, Once B.', '221000008', 'BSIT', 4, 'B']];

        $this->post(route('admin.rosters.students.store'), ['file' => $this->listFile(self::HEADER, $row)])
            ->assertOk()->assertJson(['created' => 1]);

        $this->post(route('admin.rosters.students.store'), ['file' => $this->listFile(self::HEADER, $row)])
            ->assertOk()->assertJson(['created' => 0, 'matched' => 1]);

        $this->assertSame(1, Student::where('email', 'twice@my.cspc.edu.ph')->count());
    }

    /**
     * A list is typed by hand, and an address off the student domain is one
     * nobody can ever sign in with - "my.cpsc" for "my.cspc" is the mistake
     * this exists to catch. Preparing an account on it would be worse than
     * doing nothing: it would look imported and match nobody, ever.
     */
    public function test_an_address_outside_the_student_domain_is_refused(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->post(route('admin.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER, [
                ['typo@my.cpsc.edu.ph', 'Typo, Do Not B.', '221000009', 'BSIT', 4, 'B'],
                ['nodomain@my.edu.ph', 'Short, Domain B.', '221000010', 'BSIT', 4, 'B'],
            ]),
        ]);

        $response->assertOk();
        $response->assertJson(['skipped' => 2, 'created' => 0, 'inserted' => 0]);
        $this->assertStringContainsString('@my.cspc.edu.ph', $response->json('errors.0'));

        $this->assertSame(0, Student::where('email', 'typo@my.cpsc.edu.ph')->count());
        $this->assertSame(0, StudentRoster::count(), 'A row nobody can use was still stored.');
    }

    /** Email is the only thing an account can be matched on. */
    public function test_a_list_with_no_email_column_is_refused(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('admin.rosters.students.store'), [
            'file' => $this->listFile(['Name', 'Student No.'], [['Cruz, Juan D.', '221000003']]),
        ])->assertStatus(422);

        $this->assertSame(0, StudentRoster::count());
    }

    /** A bad email is reported with its row number, and the rest still run. */
    public function test_a_bad_email_is_skipped_and_named_by_row(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $good = Student::create(['email' => 'good@my.cspc.edu.ph', 'f_name' => 'G', 'l_name' => 'D']);

        $response = $this->post(route('admin.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER, [
                ['not-an-email', 'Broken, Row', '1', 'BSIT', 4, 'B'],
                ['good@my.cspc.edu.ph', 'Good, Row', '221000004', 'BSIT', 4, 'B'],
            ]),
        ]);

        $response->assertOk();
        $response->assertJson(['skipped' => 1, 'matched' => 1]);
        $this->assertStringContainsString('Row 2', $response->json('errors.0'));

        $this->assertSame('221000004', $good->fresh()->student_number);
    }

    /**
     * Uploading again updates the stored list rather than duplicating it -
     * but a value already on the account is left where it is. The list is a
     * spreadsheet kept by hand; one stale row should not be able to move a
     * student out of their section.
     */
    public function test_uploading_again_updates_the_list_but_not_a_filled_profile(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $student = Student::create(['email' => 'again@my.cspc.edu.ph', 'f_name' => 'A', 'l_name' => 'G']);

        foreach ([['221000005', 'B'], ['221000006', 'A']] as [$number, $section]) {
            $this->post(route('admin.rosters.students.store'), [
                'file' => $this->listFile(self::HEADER, [
                    ['again@my.cspc.edu.ph', 'Gain, A.', $number, 'BSIT', 4, $section],
                ]),
            ])->assertOk();
        }

        // One stored row, carrying the newest upload.
        $this->assertSame(1, StudentRoster::where('email', 'again@my.cspc.edu.ph')->count());
        $this->assertSame('221000006', StudentRoster::where('email', 'again@my.cspc.edu.ph')->value('student_number'));

        // The account keeps what the first upload filled in.
        $this->assertSame('221000005', $student->fresh()->student_number);
        $this->assertSame('4th '.json_decode('"·"').' B', $student->fresh()->year_section);
    }

    /** Nothing a student already has is touched, and the count says so. */
    public function test_an_account_that_is_already_complete_is_left_alone(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $student = Student::create([
            'email' => 'complete@my.cspc.edu.ph',
            'f_name' => 'Al',
            'l_name' => 'Ready',
            'student_number' => '221000007',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '3rd '.json_decode('"·"').' A',
        ]);

        $response = $this->post(route('admin.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER, [
                ['complete@my.cspc.edu.ph', 'Ready, Al B.', '999999999', 'BSIT', 4, 'B'],
            ]),
        ]);

        $response->assertOk();
        $response->assertJson(['matched' => 1, 'filled' => 0, 'already_complete' => 1]);

        $student->refresh();
        $this->assertSame('221000007', $student->student_number);
        $this->assertSame('Bachelor of Science in Computer Science', $student->course);
        $this->assertSame('3rd '.json_decode('"·"').' A', $student->year_section);
    }
}
