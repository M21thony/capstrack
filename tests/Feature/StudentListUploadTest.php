<?php

namespace Tests\Feature;

use App\Models\CapstoneExpert;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

    /** Every student is added with their number: a row without one is skipped and named. */
    public function test_a_new_student_without_a_student_number_is_skipped(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER, [
                ['numbered@my.cspc.edu.ph', 'Cruz, Ana B.', '221001136', 'BSIT', 4, 'B'],
                ['nonumber@my.cspc.edu.ph', 'Reyes, Ben C.', '', 'BSIT', 4, 'B'],
            ]),
        ])->assertOk();

        $this->assertSame(1, $response->json('skipped'));
        $this->assertStringContainsString("Row 3: 'nonumber@my.cspc.edu.ph' has no student number - skipped.", implode(' ', $response->json('errors') ?? []));
        $this->assertTrue(Student::where('email', 'numbered@my.cspc.edu.ph')->exists());
        $this->assertFalse(Student::where('email', 'nonumber@my.cspc.edu.ph')->exists());
    }

    /** A student already on file with a number may be on a list that leaves the number out. */
    public function test_a_row_without_a_number_still_updates_a_student_who_has_one(): void
    {
        $this->actingAs($this->capsa(), 'expert');
        $student = Student::create(['email' => 'known@my.cspc.edu.ph', 'f_name' => 'K', 'l_name' => 'N', 'student_number' => '221001137']);

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER, [['known@my.cspc.edu.ph', 'N, K', '', 'BSIT', 4, 'B']]),
        ])->assertOk()->assertJson(['skipped' => 0]);

        $student->refresh();
        $this->assertSame('221001137', $student->student_number);
        $this->assertSame('Bachelor of Science in Information Technology', $student->course);
    }

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

        $response = $this->post(route('capsa.rosters.students.store'), [
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

        $this->post(route('capsa.rosters.students.store'), [
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

        $response = $this->post(route('capsa.rosters.students.store'), [
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

        $this->post(route('capsa.rosters.students.store'), ['file' => $this->listFile(self::HEADER, $row)])
            ->assertOk()->assertJson(['created' => 1]);

        $this->post(route('capsa.rosters.students.store'), ['file' => $this->listFile(self::HEADER, $row)])
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

        $response = $this->post(route('capsa.rosters.students.store'), [
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

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(['Name', 'Student No.'], [['Cruz, Juan D.', '221000003']]),
        ])->assertStatus(422);

        $this->assertSame(0, StudentRoster::count());
    }

    /** A bad email is reported with its row number, and the rest still run. */
    public function test_a_bad_email_is_skipped_and_named_by_row(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $good = Student::create(['email' => 'good@my.cspc.edu.ph', 'f_name' => 'G', 'l_name' => 'D']);

        $response = $this->post(route('capsa.rosters.students.store'), [
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
            $this->post(route('capsa.rosters.students.store'), [
                'file' => $this->listFile(self::HEADER, [
                    ['again@my.cspc.edu.ph', 'Gain, A.', $number, 'BSIT', 4, $section],
                ]),
            ])->assertOk();
        }

        // One stored row, carrying the newest upload.
        $this->assertSame(1, StudentRoster::where('email', 'again@my.cspc.edu.ph')->count());
        $this->assertSame('221000006', StudentRoster::where('email', 'again@my.cspc.edu.ph')->value('student_number'));

        // The student number is kept - once set, a re-upload should not
        // churn it - but the section follows the list, because the list is
        // the record of which section a student is in.
        $this->assertSame('221000005', $student->fresh()->student_number);
        $this->assertSame('4th '.json_decode('"·"').' A', $student->fresh()->year_section);
    }

    /**
     * Course and section come from the department's list, so the list wins:
     * a student can pick their course wrongly at sign-up, and a section
     * changes between terms. The name and student number they already have
     * are left where they are.
     */
    public function test_the_list_corrects_the_course_and_section(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $student = Student::create([
            'email' => 'wrong@my.cspc.edu.ph',
            'f_name' => 'Al',
            'l_name' => 'Ready',
            'student_number' => '221000007',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '3rd '.json_decode('"·"').' A',
        ]);

        $response = $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER, [
                ['wrong@my.cspc.edu.ph', 'Ready, Al B.', '999999999', 'BSIT', 4, 'B'],
            ]),
        ]);

        $response->assertOk();
        $response->assertJson(['matched' => 1, 'filled' => 2]);

        $student->refresh();
        $this->assertSame('Bachelor of Science in Information Technology', $student->course);
        $this->assertSame('4th '.json_decode('"·"').' B', $student->year_section);

        // Not these: the name came from their own sign-in, and the number is
        // not something a re-upload should churn.
        $this->assertSame('221000007', $student->student_number);
        $this->assertSame('Al', $student->f_name);
        $this->assertSame('Ready', $student->l_name);
    }

    /** A row that changes nothing is reported as changing nothing. */
    public function test_a_row_that_matches_the_account_changes_nothing(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        Student::create([
            'email' => 'same@my.cspc.edu.ph',
            'f_name' => 'Same',
            'l_name' => 'Same',
            'student_number' => '221000011',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th '.json_decode('"·"').' B',
        ]);

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER, [
                ['same@my.cspc.edu.ph', 'Same, Same', '221000011', 'BSIT', 4, 'B'],
            ]),
        ])->assertOk()->assertJson(['matched' => 1, 'filled' => 0, 'already_complete' => 1]);
    }

    // -- A list that keeps the parts of a name apart -----------------------

    private const HEADER_SPLIT = ['Email', 'Last Name', 'First Name', 'Middle Initial', 'Student No.', 'Course/Program', 'Year', 'Section'];

    /** Last Name, First Name and Middle Initial are read straight. */
    public function test_a_list_can_keep_the_name_in_separate_columns(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER_SPLIT, [
                ['split@my.cspc.edu.ph', 'Aurillas', 'John Kenneth', 'B.', '221001135', 'BSIT', 4, 'B'],
            ]),
        ])->assertOk()->assertJson(['created' => 1]);

        $student = Student::where('email', 'split@my.cspc.edu.ph')->first();

        $this->assertSame('John Kenneth', $student->f_name);
        $this->assertSame('B.', $student->m_name);
        $this->assertSame('Aurillas', $student->l_name);
    }

    /** The stored list row still reads as a name. */
    public function test_the_stored_row_keeps_a_readable_name(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER_SPLIT, [
                ['readable@my.cspc.edu.ph', 'Balilla', 'Mark Anthony', 'B.', '221001145', 'BSIT', 4, 'B'],
            ]),
        ])->assertOk();

        $this->assertSame(
            'Balilla, Mark Anthony B.',
            StudentRoster::where('email', 'readable@my.cspc.edu.ph')->value('name')
        );
    }

    /** A middle initial is optional. */
    /** A plain CSV, written in whatever encoding the program that saved it used. */
    private function csvFile(array $lines, string $encoding, bool $bom = false): UploadedFile
    {
        $text = implode("\r\n", $lines)."\r\n";
        $bytes = ($bom ? "\xEF\xBB\xBF" : '').mb_convert_encoding($text, $encoding, 'UTF-8');

        $path = tempnam(sys_get_temp_dir(), 'list').'.csv';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'Student List.csv', 'text/csv', null, true);
    }

    private const CSV_WITH_N_TILDE = [
        'Email,Last Name,First Name,Middle Name,Student No.,Course/Program,Year,Section',
        'juan.pena@my.cspc.edu.ph,Peña,Juan,Nuñez,221001135,BSIT,4,B',
        'maria.ibanez@my.cspc.edu.ph,Ibáñez,María,Santos,221001136,BSIT,4,B',
    ];

    /**
     * Excel on Windows saves "CSV (Comma delimited)" in Windows-1252, not
     * UTF-8. Read as UTF-8, every ñ came out as a replacement character.
     */
    public function test_a_csv_saved_by_excel_on_windows_keeps_its_n_tilde(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->csvFile(self::CSV_WITH_N_TILDE, 'Windows-1252'),
        ])->assertOk()->assertJson(['created' => 2, 'skipped' => 0]);

        $juan = Student::where('email', 'juan.pena@my.cspc.edu.ph')->first();
        $this->assertSame('Peña', $juan->l_name);
        $this->assertSame('Nuñez', $juan->m_name);
        $this->assertSame('Ibáñez', Student::where('email', 'maria.ibanez@my.cspc.edu.ph')->value('l_name'));
        $this->assertSame('María', Student::where('email', 'maria.ibanez@my.cspc.edu.ph')->value('f_name'));
        $this->assertSame('Peña, Juan Nuñez', StudentRoster::where('email', 'juan.pena@my.cspc.edu.ph')->value('name'));
    }

    public function test_the_preview_shows_the_n_tilde_from_a_windows_csv(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('capsa.rosters.students.preview'), [
            'file' => $this->csvFile(self::CSV_WITH_N_TILDE, 'Windows-1252'),
        ])->assertOk()->assertJsonPath('rows.0.name', 'Peña, Juan Nuñez');
    }

    /** "CSV UTF-8" from Excel, byte-order mark and all, still reads the same. */
    public function test_a_utf8_csv_with_or_without_a_byte_order_mark_keeps_its_n_tilde(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        foreach ([false, true] as $bom) {
            $this->post(route('capsa.rosters.students.preview'), [
                'file' => $this->csvFile(self::CSV_WITH_N_TILDE, 'UTF-8', $bom),
            ])->assertOk()
                ->assertJsonPath('rows.0.name', 'Peña, Juan Nuñez')
                ->assertJsonPath('rows.1.name', 'Ibáñez, María Santos');
        }
    }

    public function test_a_workbook_keeps_its_n_tilde(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(
                ['Email', 'Last Name', 'First Name', 'Middle Name', 'Student No.', 'Course/Program', 'Year', 'Section'],
                [['nino.pena@my.cspc.edu.ph', 'Peña', 'Niño', 'Muñoz', '221001137', 'BSIT', 4, 'B']]
            ),
        ])->assertOk();

        $student = Student::where('email', 'nino.pena@my.cspc.edu.ph')->first();
        $this->assertSame('Niño', $student->f_name);
        $this->assertSame('Muñoz', $student->m_name);
    }

    public function test_the_middle_initial_can_be_left_out(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER_SPLIT, [
                ['nomi@my.cspc.edu.ph', 'Cruz', 'Juan', '', '221000050', 'BSIT', 4, 'B'],
            ]),
        ])->assertOk()->assertJson(['created' => 1]);

        $student = Student::where('email', 'nomi@my.cspc.edu.ph')->first();

        $this->assertSame('Juan', $student->f_name);
        $this->assertSame('Cruz', $student->l_name);
        $this->assertNull($student->m_name);
    }

    // -- School Year column ------------------------------------------------

    private const HEADER_SY = ['Email', 'Name', 'Student No.', 'Course/Program', 'Year', 'Section', 'School Year'];

    /**
     * The school year on the list has to reach student_s_y, not just the
     * stored roster row: that column is what the classmate picker and every
     * section lookup filter on. Writing it only to the roster left them
     * untouched, so a list could import and change nothing anyone sees.
     */
    public function test_the_school_year_lands_on_the_enrolment_row(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $student = Student::create(['email' => 'sy@my.cspc.edu.ph', 'f_name' => 'S', 'l_name' => 'Y']);
        StudentSchoolYear::create(['student_id' => $student->student_id, 'school_year' => '2020-2021']);

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER_SY, [
                ['sy@my.cspc.edu.ph', 'Why, Ess B.', '221000020', 'BSIT', 4, 'B', '2026-2027'],
            ]),
        ])->assertOk();

        $this->assertSame(
            '2026-2027',
            StudentSchoolYear::where('student_id', $student->student_id)->value('school_year')
        );
    }

    /** A prepared account has no enrolment row yet, so one is made for it. */
    public function test_a_prepared_account_gets_an_enrolment_row_with_the_year(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER_SY, [
                ['fresh@my.cspc.edu.ph', 'Fresh, New B.', '221000021', 'BSIT', 4, 'B', '2026-2027'],
            ]),
        ])->assertOk()->assertJson(['created' => 1]);

        $student = Student::where('email', 'fresh@my.cspc.edu.ph')->first();

        $this->assertSame(
            '2026-2027',
            StudentSchoolYear::where('student_id', $student->student_id)->value('school_year')
        );
    }

    /** The forms a person actually types all come to the one written form. */
    public function test_a_hand_typed_school_year_is_brought_to_one_form(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        foreach ([['2026-2027', 'a'], ['2026-27', 'b'], ['26-27', 'c'], ['2026', 'd']] as [$written, $tag]) {
            $email = "sy{$tag}@my.cspc.edu.ph";

            $this->post(route('capsa.rosters.students.store'), [
                'file' => $this->listFile(self::HEADER_SY, [
                    [$email, 'Typed, Various B.', '22100002'.$tag, 'BSIT', 4, 'B', $written],
                ]),
            ])->assertOk();

            $student = Student::where('email', $email)->first();

            $this->assertSame(
                '2026-2027',
                StudentSchoolYear::where('student_id', $student->student_id)->value('school_year'),
                "'{$written}' did not come out as 2026-2027.",
            );
        }
    }

    /**
     * A stray School Year column is ignored rather than obeyed.
     *
     * The Admin declares the school year for the whole system, so a column
     * of them on an old file is one more thing that can disagree with it.
     * It used to be read, and a mistyped year skipped the student - losing
     * a real person over a column that should not have been there.
     */
    public function test_a_school_year_column_is_ignored_and_the_students_still_arrive(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER_SY, [
                ['junk@my.cspc.edu.ph', 'Junk, Year B.', '221000030', 'BSIT', 4, 'B', '26-1'],
                ['gap@my.cspc.edu.ph', 'Gap, Year B.', '221000031', 'BSIT', 4, 'B', '2026-2028'],
            ]),
        ]);

        $response->assertOk();
        $response->assertJson(['skipped' => 0]);

        $this->assertSame(1, Student::where('email', 'junk@my.cspc.edu.ph')->count());
        $this->assertSame(
            Term::currentSchoolYear(),
            StudentRoster::where('email', 'junk@my.cspc.edu.ph')->value('school_year'),
            'the term that is running, not the one typed in the file'
        );
    }

    /** With no School Year column it falls back to the term that is running. */
    public function test_without_the_column_the_current_term_is_used(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile(self::HEADER, [
                ['noyear@my.cspc.edu.ph', 'No, Year B.', '221000040', 'BSIT', 4, 'B'],
            ]),
        ])->assertOk();

        $student = Student::where('email', 'noyear@my.cspc.edu.ph')->first();

        $this->assertSame(
            Term::currentSchoolYear(),
            StudentSchoolYear::where('student_id', $student->student_id)->value('school_year')
        );
    }

    // -- The preview, and the template it is filled in from ----------------

    /**
     * The preview reads the file and writes nothing.
     *
     * That is the whole point of it: an upload rewrites the course, section
     * and school year on every account it matches and nothing undoes it, so
     * looking has to be free.
     */
    public function test_previewing_a_list_changes_nothing(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $student = Student::create([
            'email' => 'look@my.cspc.edu.ph',
            'f_name' => 'Look',
            'l_name' => 'Only',
        ]);

        $this->post(route('capsa.rosters.students.preview'), [
            'file' => $this->listFile(self::HEADER, [
                ['look@my.cspc.edu.ph', 'Only, Look B.', '221000020', 'BSIT', 4, 'B'],
                ['newperson@my.cspc.edu.ph', 'New, Person B.', '221000021', 'BSIT', 4, 'B'],
            ]),
        ])->assertOk();

        $student->refresh();
        $this->assertNull($student->student_number);
        $this->assertNull($student->course);
        $this->assertNull($student->year_section);

        $this->assertNull(Student::where('email', 'newperson@my.cspc.edu.ph')->first());
        $this->assertSame(0, StudentRoster::count());
    }

    /** Each row is reported as what it would do, and which fields it moves. */
    public function test_the_preview_says_what_each_row_would_do(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        // Nothing on file yet - the row would make the account.
        // Already right - the row would change nothing.
        Student::create([
            'email' => 'same@my.cspc.edu.ph',
            'f_name' => 'Same',
            'l_name' => 'Same',
            'student_number' => '221000030',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th '.json_decode('"\u00b7"').' B',
        ]);

        // Wrong course on file - the row would correct it.
        Student::create([
            'email' => 'wrong@my.cspc.edu.ph',
            'f_name' => 'Wrong',
            'l_name' => 'Course',
            'student_number' => '221000031',
            'course' => 'Bachelor of Science in Computer Science',
            'year_section' => '4th '.json_decode('"\u00b7"').' B',
        ]);

        $response = $this->post(route('capsa.rosters.students.preview'), [
            'file' => $this->listFile(self::HEADER, [
                ['fresh@my.cspc.edu.ph', 'Fresh, Face B.', '221000032', 'BSIT', 4, 'B'],
                ['same@my.cspc.edu.ph', 'Same, Same', '221000030', 'BSIT', 4, 'B'],
                ['wrong@my.cspc.edu.ph', 'Course, Wrong', '221000031', 'BSIT', 4, 'B'],
            ]),
        ]);

        $response->assertOk();
        $response->assertJson(['create' => 1, 'update' => 1, 'unchanged' => 1, 'skipped' => 0]);

        $rows = collect($response->json('rows'))->keyBy('email');

        $this->assertSame('create', $rows['fresh@my.cspc.edu.ph']['action']);
        $this->assertSame('unchanged', $rows['same@my.cspc.edu.ph']['action']);
        $this->assertSame('update', $rows['wrong@my.cspc.edu.ph']['action']);

        // Named, so a CaPSA can see it is the course about to be rewritten.
        $this->assertSame(['course'], $rows['wrong@my.cspc.edu.ph']['changes']);
    }

    /** Year and section are reported apart, the way the list shows them. */
    public function test_the_preview_reports_year_and_section_separately(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->post(route('capsa.rosters.students.preview'), [
            'file' => $this->listFile(self::HEADER, [
                ['apart@my.cspc.edu.ph', 'Apart, Kept B.', '221000033', 'BSIT', 4, 'B'],
            ]),
        ]);

        $row = $response->assertOk()->json('rows.0');

        $this->assertSame('BSIT', $row['program']);
        $this->assertSame('4th', $row['year']);
        $this->assertSame('B', $row['section']);
    }

    /** A row the upload would skip is reported as skipped, not as a change. */
    public function test_the_preview_reports_the_rows_that_would_be_skipped(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->post(route('capsa.rosters.students.preview'), [
            'file' => $this->listFile(self::HEADER, [
                ['typo@my.cpsc.edu.ph', 'Typo, Do Not B.', '221000034', 'BSIT', 4, 'B'],
            ]),
        ]);

        $response->assertOk();
        $response->assertJson(['skipped' => 1, 'create' => 0, 'update' => 0]);
        $this->assertSame([], $response->json('rows'));
    }

    /** A file with no Email column is refused by the preview, as by the upload. */
    public function test_the_preview_refuses_a_file_with_no_email_column(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $this->post(route('capsa.rosters.students.preview'), [
            'file' => $this->listFile(['Name', 'Student No.'], [['Cruz, Juan', '221000035']]),
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    /** The template carries the headings the upload reads. */
    public function test_the_template_is_offered_with_the_headings_that_are_read(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->get(route('capsa.rosters.students.template'));

        $response->assertOk();
        $response->assertDownload('Student List Template.xlsx');

        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())
            ->getActiveSheet()
            ->toArray();

        // No School Year: the Admin sets it for the whole system, so a
        // column asking a CaPSA to type it on every row is a way to
        // disagree with that and nothing else.
        $this->assertSame(
            ['Email', 'Last Name', 'First Name', 'Middle Name', 'Student No.', 'Course/Program', 'Year', 'Section'],
            $sheet[0]
        );
    }

    /**
     * The template, filled in, is a list this accepts.
     *
     * The headings matching is not the same as the upload reading them - this
     * walks the template through the real import so the two cannot drift.
     */
    public function test_a_list_written_from_the_template_imports(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $headings = IOFactory::load(
            $this->get(route('capsa.rosters.students.template'))->baseResponse->getFile()->getPathname()
        )->getActiveSheet()->toArray()[0];

        $this->post(route('capsa.rosters.students.store'), [
            'file' => $this->listFile($headings, [
                ['fromtemplate@my.cspc.edu.ph', 'Dela Cruz', 'Juan', 'B.', '221000036', 'BSIT', 4, 'B', '2026-2027'],
            ]),
        ])->assertOk()->assertJson(['created' => 1, 'skipped' => 0]);

        $student = Student::where('email', 'fromtemplate@my.cspc.edu.ph')->first();

        $this->assertSame('Juan', $student->f_name);
        $this->assertSame('B.', $student->m_name);
        $this->assertSame('Dela Cruz', $student->l_name);
        $this->assertSame('221000036', $student->student_number);
        $this->assertSame('Bachelor of Science in Information Technology', $student->course);
        $this->assertSame('4th '.json_decode('"\u00b7"').' B', $student->year_section);
    }

    // -- The page the upload is done from ----------------------------------

    /**
     * The modal is on the page, with the template inside it.
     *
     * CaPSA asked for the template to be in the modal where the list is
     * uploaded, rather than somewhere she has to go and find it.
     */
    public function test_the_upload_modal_offers_the_template(): void
    {
        $this->actingAs($this->capsa(), 'expert');

        $response = $this->get(route('capsa.sections'));

        $response->assertOk();
        $response->assertSee('id="studentRosterModal"', false);
        $response->assertSee(route('capsa.rosters.students.template'), false);
        $response->assertSee('Download template', false);

        // Preview first, save second - both steps present on the page, since
        // a button whose target is missing does nothing when clicked.
        $response->assertSee('id="studentRosterStep1"', false);
        $response->assertSee('id="studentRosterStep2"', false);
        $response->assertSee('id="studentRosterPreviewBtn"', false);
        $response->assertSee('id="studentRosterUploadBtn"', false);
        $response->assertSee('id="studentRosterPreviewTable"', false);

        // A file can be dropped as well as browsed for, and either way it
        // lands in the one input Preview and Save both read.
        $response->assertSee('id="studentRosterDrop"', false);
        $response->assertSee('id="studentRosterChip"', false);

        // Only .xlsx is read, so offering CSV would be an invitation to a
        // file that gets refused at the door.
        $response->assertDontSee('CSV', false);
        $response->assertDontSee('.csv', false);

        // Saving is offered without previewing first - the preview is there
        // to be used, not to be got past.
        $response->assertSee('uploadStudentRoster(this)', false);
    }

    /** Program, Year and Section read down three columns, not one. */
    public function test_the_student_list_keeps_program_year_and_section_apart(): void
    {
        $capsa = $this->capsa();
        $capsa->update(['sections' => ['BSIT-4B']]);
        $this->actingAs($capsa, 'expert');

        Student::create([
            'email' => 'listed@my.cspc.edu.ph',
            'f_name' => 'Listed',
            'l_name' => 'Student',
            'course' => 'Bachelor of Science in Information Technology',
            'year_section' => '4th '.json_decode('"\u00b7"').' B',
        ]);

        $response = $this->get(route('capsa.sections'));

        $response->assertOk();
        $response->assertSee('<th>Program</th>', false);
        $response->assertSee('<th>Year</th>', false);
        $response->assertSee('<th>Section</th>', false);
        $response->assertDontSee('<th>Year & Section</th>', false);
    }
}
