<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A CaPSA uploads the official student list for their sections.
 *
 * Students never wait on this - they can sign in with Google and use the site
 * the moment they arrive, list or no list, with their academic fields simply
 * starting empty. This upload matches the list to accounts that already
 * exist, by email, and fills those fields in. It is not a gate on who may
 * have an account.
 *
 * Columns are read by their headings rather than by position. The list is a
 * spreadsheet a department keeps by hand, so column order is not something to
 * depend on - read positionally, a list in any other order quietly put the
 * email in the name field and the section in the course.
 */
class StudentRosterController extends Controller
{
    /** Headings this understands, each mapped to what it means. */
    private const HEADINGS = [
        'email' => 'email',
        'e-mail' => 'email',
        'name' => 'name',
        'full name' => 'name',
        'student name' => 'name',
        'last name' => 'last_name',
        'surname' => 'last_name',
        'family name' => 'last_name',
        'first name' => 'first_name',
        'given name' => 'first_name',
        'middle initial' => 'middle_initial',
        'middle name' => 'middle_initial',
        'm.i.' => 'middle_initial',
        'mi' => 'middle_initial',
        'student no' => 'student_number',
        'student no.' => 'student_number',
        'student number' => 'student_number',
        'student id' => 'student_number',
        'course' => 'course',
        'program' => 'course',
        'course/program' => 'course',
        'year' => 'year',
        'year level' => 'year',
        'section' => 'section',
        'year & section' => 'year_section',
        'year and section' => 'year_section',
        'year section' => 'year_section',
        'school year' => 'school_year',
        'sy' => 'school_year',
    ];

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $read = $this->read($request->file('file')->getRealPath());

        if (isset($read['message'])) {
            return response()->json(['success' => false, 'message' => $read['message']], 422);
        }

        $importedBy = Auth::guard('expert')->id();

        $inserted = 0;
        $matched = 0;
        $created = 0;
        $enrolments = [];
        $filled = 0;
        $alreadyComplete = 0;

        foreach ($read['rows'] as $row) {
            StudentRoster::updateOrCreate(
                ['email' => $row['email']],
                [
                    'name' => $row['name'],
                    'student_number' => $row['student_number'] ?: null,
                    'section' => $row['year_section'],
                    'program' => $row['program'],
                    'school_year' => $row['school_year'],
                    'imported_by' => $importedBy,
                ]
            );

            $inserted++;

            // The point of the whole upload: a student who signed in before
            // this list existed has no student number, course or section on
            // their profile. Fill them in from the official list.
            $student = Student::whereRaw('LOWER(email) = ?', [$row['email']])->first();

            // Nobody has signed in on this address yet. Make the account now,
            // ready and filled in, rather than storing the row and waiting -
            // the list has everything the account needs, and a student who
            // has not arrived yet is exactly who this is for. Signing in
            // later matches on email, so this is the account they land in.
            if (! $student) {
                [$first, $middle, $last] = $this->nameParts($row['raw'], null);

                $student = Student::create([
                    'email' => $row['email'],
                    'f_name' => $first,
                    'm_name' => $middle,
                    'l_name' => $last,
                    'student_number' => $row['student_number'] ?: null,
                    'course' => $row['course'],
                    'year_section' => $row['year_section'] ?: null,
                ]);

                $created++;
                $enrolments[] = [$student->student_id, $row['school_year']];

                continue;
            }

            $changes = $this->changesFor($student, $row);

            if ($changes !== []) {
                $student->update($changes);
                $filled += count($changes);
            } else {
                $alreadyComplete++;
            }

            $enrolments[] = [$student->student_id, $row['school_year']];

            $matched++;
        }

        foreach ($enrolments as [$studentId, $year]) {
            $this->recordSchoolYear($studentId, $year);
        }

        return response()->json([
            'success' => true,
            'inserted' => $inserted,
            'matched' => $matched,
            'created' => $created,
            'filled' => $filled,
            'already_complete' => $alreadyComplete,
            'skipped' => $read['skipped'],
            'errors' => $read['errors'],
        ]);
    }

    /**
     * What the file would do, without doing any of it.
     *
     * An upload rewrites the course, section and school year on every account
     * it matches, so a list with a wrong column - or last term's sections -
     * changes real profiles, and nothing undoes it. This runs the same
     * reading and the same comparison store() runs and reports the outcome
     * per row instead of writing it, so a CaPSA sees what a file does before
     * it does it.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt'],
        ]);

        $read = $this->read($request->file('file')->getRealPath());

        if (isset($read['message'])) {
            return response()->json(['success' => false, 'message' => $read['message']], 422);
        }

        $rows = [];
        $create = 0;
        $update = 0;
        $unchanged = 0;

        foreach ($read['rows'] as $row) {
            $student = Student::whereRaw('LOWER(email) = ?', [$row['email']])->first();

            if (! $student) {
                $action = 'create';
                $changes = [];
                $create++;
            } elseif (($changes = $this->changesFor($student, $row)) !== []) {
                $action = 'update';
                $update++;
            } else {
                $action = 'unchanged';
                $unchanged++;
            }

            $rows[] = [
                'row' => $row['row'],
                'name' => $row['name'],
                'email' => $row['email'],
                'student_number' => $row['student_number'],
                'program' => $row['program'],
                'year' => $row['year'],
                'section' => $row['section'],
                'school_year' => $row['school_year'],
                'action' => $action,
                // Named, not counted: "course, year_section" says what a
                // re-upload is about to rewrite, where "2 fields" does not.
                'changes' => array_keys($changes),
            ];
        }

        return response()->json([
            'success' => true,
            'rows' => $rows,
            'create' => $create,
            'update' => $update,
            'unchanged' => $unchanged,
            'skipped' => $read['skipped'],
            'errors' => $read['errors'],
        ]);
    }

    /** The headings the template is written with, in the order it writes them. */
    private const TEMPLATE_HEADINGS = [
        'Email', 'Last Name', 'First Name', 'Middle Initial',
        'Student No.', 'Course/Program', 'Year', 'Section', 'School Year',
    ];

    /**
     * The blank list, with the headings this reads and one filled-in row.
     *
     * The modal described the columns in prose, which left a CaPSA to type
     * nine headings by hand - and "Students No." for "Student No." reads as
     * no column at all, so the number silently never arrives. Handing over
     * the file removes that whole class of mistake, and the example row
     * shows the two things prose kept having to explain: the school year is
     * written 2026-2027, and the year is a bare number.
     */
    public function template(): BinaryFileResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Student List');

        $sheet->fromArray([self::TEMPLATE_HEADINGS], null, 'A1');
        $sheet->fromArray([[
            'juan.delacruz'.Student::EMAIL_DOMAIN,
            'Dela Cruz', 'Juan', 'B.', '221001135', 'BSIT', 4, 'B', Term::currentSchoolYear(),
        ]], null, 'A2');

        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'list').'.xlsx';
        (new Xlsx($book))->save($path);

        return response()->download($path, 'Student List Template.xlsx')->deleteFileAfterSend();
    }

    /**
     * The file, read into rows this understands. Nothing is written.
     *
     * Both the preview and the upload go through here, so what the preview
     * shows and what the upload does cannot drift apart - they are the same
     * reading of the same file.
     *
     * @return array{message?: string, rows?: array<int, array>, errors?: string[], skipped?: int}
     */
    private function read(string $path): array
    {
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();

        $header = array_shift($rows);
        $columns = $this->mapHeadings($header ?? []);

        if (! isset($columns['email'])) {
            return ['message' => 'That file has no Email column, and email is the only thing a student account can be matched on.'];
        }

        $schoolYear = Term::currentSchoolYear();

        $parsed = [];
        $errors = [];
        $skipped = 0;

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $get = fn (string $key) => isset($columns[$key]) ? trim((string) ($row[$columns[$key]] ?? '')) : '';

            $email = mb_strtolower($get('email'));
            $name = trim($get('name').' '.$get('last_name').' '.$get('first_name'));

            if ($email === '' && $name === '') {
                continue; // a blank line at the end of the sheet
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $errors[] = "Row {$rowNum}: '".($email ?: '(blank)')."' is not a valid email - skipped.";

                continue;
            }

            // A student can only ever sign in from this domain, so an address
            // outside it is one nobody will be able to use. Saying so here is
            // the point: the list is typed by hand, and a row reading
            // "my.cpsc" for "my.cspc" would otherwise be stored, counted as
            // imported, and never match anybody.
            if (! str_ends_with($email, Student::EMAIL_DOMAIN)) {
                $skipped++;
                $errors[] = "Row {$rowNum}: '{$email}' is not a ".Student::EMAIL_DOMAIN
                    .' address, so nobody could sign in with it - skipped.';

                continue;
            }

            // A school year has one written form here - "2026-2027" - and
            // every reader filters on an exact match, so a hand-typed
            // "2026-27" or "26-27" has to be brought to it rather than
            // stored as-is and quietly matching nobody.
            $rowSchoolYear = $schoolYear;

            if ($get('school_year') !== '') {
                $rowSchoolYear = $this->normaliseSchoolYear($get('school_year'));

                if ($rowSchoolYear === null) {
                    $skipped++;
                    $errors[] = "Row {$rowNum}: '".$get('school_year')."' is not a school year - write it as 2026-2027 - skipped.";

                    continue;
                }
            }

            $courseCode = Student::mapCourseToProgram($get('course'));
            [$year, $section] = $this->yearAndSection($get('year'), $get('section'), $get('year_section'));

            $raw = [
                'name' => $get('name'),
                'last' => $get('last_name'),
                'first' => $get('first_name'),
                'middle' => $get('middle_initial'),
            ];

            $parsed[] = [
                'row' => $rowNum,
                'email' => $email,
                'name' => $this->displayName($raw),
                'student_number' => $get('student_number'),
                'program' => $courseCode ?? '',
                'course' => $courseCode ? Student::PROGRAM_COURSES[$courseCode] : null,
                'year' => $year,
                'section' => $section,
                'year_section' => $this->yearSection($get('year'), $get('section'), $get('year_section')),
                'school_year' => $rowSchoolYear,
                'raw' => $raw,
            ];
        }

        return ['rows' => $parsed, 'errors' => $errors, 'skipped' => $skipped];
    }

    /**
     * What this row would change on an account that already exists.
     *
     * The preview reports these and the upload writes them, from this one
     * method - so the preview cannot promise something the upload does not do.
     */
    private function changesFor(Student $student, array $row): array
    {
        [$first, $middle, $last] = $this->nameParts($row['raw'], $student);

        // Course, year and section come from the department's own list, so
        // the list wins - a student who picked their course at sign-up can
        // have picked it wrong, and a section changes between terms. The
        // school year is written the same way, on the enrolment row.
        $fromList = array_filter([
            'course' => $row['course'],
            'year_section' => $row['year_section'],
        ], 'filled');

        // Name and student number are only filled when missing. The name on
        // an account came from the student's own Google sign-in and is the
        // one they are known by; the number, once set, is not something a
        // re-upload should churn.
        $fromList += array_filter([
            'f_name' => $first,
            'm_name' => $middle,
            'l_name' => $last,
            'student_number' => $row['student_number'],
        ], fn ($value, $field) => filled($value) && blank($student->{$field}), ARRAY_FILTER_USE_BOTH);

        // Only what actually differs, so the count means something and an
        // unchanged row is not reported as touched.
        return array_filter(
            $fromList,
            fn ($value, $field) => (string) $student->{$field} !== (string) $value,
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * A school year in the one form the rest of the app reads: "2026-2027".
     *
     * Accepts what a person actually types into a spreadsheet - 2026-2027,
     * 2026-27, 26-27, or a single 2026 - and returns null for anything else,
     * because a value nothing can match is worse than no value: it looks
     * imported and then quietly excludes the student from every lookup that
     * filters on it.
     */
    private function normaliseSchoolYear(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^(\d{4})\s*-\s*(\d{4})$/', $value, $m)) {
            return (int) $m[2] === (int) $m[1] + 1 ? $m[1].'-'.$m[2] : null;
        }

        if (preg_match('/^(\d{4})\s*-\s*(\d{2})$/', $value, $m)) {
            $end = (int) substr($m[1], 0, 2) * 100 + (int) $m[2];

            return $end === (int) $m[1] + 1 ? $m[1].'-'.$end : null;
        }

        if (preg_match('/^(\d{2})\s*-\s*(\d{2})$/', $value, $m)) {
            $start = 2000 + (int) $m[1];

            return (int) $m[2] === (int) $m[1] + 1 ? $start.'-'.($start + 1) : null;
        }

        if (preg_match('/^(\d{4})$/', $value, $m)) {
            return $m[1].'-'.((int) $m[1] + 1);
        }

        return null;
    }

    /**
     * Put the school year on the student's enrolment row.
     *
     * The roster row is a record of the upload; student_s_y is what the app
     * actually reads - the classmate picker and every section lookup filter
     * on this column. Writing it to the roster alone left them unchanged,
     * which is how a list could be imported and change nothing anyone sees.
     */
    private function recordSchoolYear(int $studentId, string $schoolYear): void
    {
        $row = StudentSchoolYear::where('student_id', $studentId)->first();

        if ($row) {
            $row->update(['school_year' => $schoolYear]);

            return;
        }

        // No enrolment row yet - a prepared account has none until they join
        // a team. Make one now so the year is on record and the student is
        // findable in their section straight away.
        StudentSchoolYear::create([
            'student_id' => $studentId,
            'school_year' => $schoolYear,
        ]);
    }

    /** Which spreadsheet column holds what, by its heading. */
    private function mapHeadings(array $header): array
    {
        $columns = [];

        foreach ($header as $index => $label) {
            $key = self::HEADINGS[trim(mb_strtolower((string) $label))] ?? null;

            if ($key !== null && ! isset($columns[$key])) {
                $columns[$key] = $index;
            }
        }

        return $columns;
    }

    /**
     * The year and the section apart - "4th" and "B".
     *
     * A list gives them in their own columns or joined in one "Year &
     * Section" column, and the student list on screen shows them as separate
     * columns either way, so both shapes have to come back apart.
     *
     * @return array{0: string, 1: string} year, section
     */
    private function yearAndSection(string $year, string $section, string $combined): array
    {
        if ($combined !== '') {
            $parts = array_map('trim', explode('·', $combined));

            return count($parts) === 2 ? [$parts[0], $parts[1]] : ['', trim($combined)];
        }

        $letter = $section !== '' ? mb_strtoupper(mb_substr($section, 0, 1)) : '';

        if ($year === '') {
            return ['', $letter];
        }

        $n = (int) $year;

        $ordinal = match (true) {
            $n === 1 => '1st',
            $n === 2 => '2nd',
            $n === 3 => '3rd',
            $n >= 4 => $n.'th',
            default => $year,
        };

        return [$ordinal, $letter];
    }

    /**
     * The year and section as the rest of the app writes them: "4th · B".
     *
     * PersonalInfoController builds it that way when a student fills in their
     * own profile, and every section lookup reads that shape - so a list that
     * gives year and section in separate columns has to be joined the same
     * way, or an imported student lands in a section of their own that no
     * CaPSA covers.
     */
    private function yearSection(string $year, string $section, string $combined): string
    {
        if ($combined !== '') {
            return $combined;
        }

        [$ordinal, $letter] = $this->yearAndSection($year, $section, $combined);

        if ($ordinal === '') {
            return $letter;
        }

        return $letter === '' ? $ordinal : $ordinal.' · '.$letter;
    }

    /**
     * The parts of a name, from whichever shape the list uses.
     *
     * A list that keeps Last Name, First Name and Middle Initial in their own
     * columns says exactly who is who, so those are read straight. A list with
     * one Name column reads "Balilla, Mark Anthony B." - surname first - and
     * is split on the comma, not the first space, which would have made
     * "Balilla," the first name.
     *
     * @param  array{name: string, last: string, first: string, middle: string}  $raw
     * @return array{0: ?string, 1: ?string, 2: ?string} first, middle, last
     */
    private function nameParts(array $raw, ?Student $student): array
    {
        ['name' => $name, 'last' => $last, 'first' => $first, 'middle' => $middle] = $raw;

        if ($last !== '' || $first !== '') {
            return [
                $first !== '' ? $first : $student?->f_name,
                $middle !== '' ? $middle : $student?->m_name,
                $last !== '' ? $last : $student?->l_name,
            ];
        }

        if ($name === '') {
            return [$student?->f_name, $student?->m_name, $student?->l_name];
        }

        if (str_contains($name, ',')) {
            [$surname, $given] = array_map('trim', explode(',', $name, 2));

            return [$given !== '' ? $given : $student?->f_name, $middle !== '' ? $middle : $student?->m_name, $surname];
        }

        $parts = preg_split('/\s+/', $name, 2);

        return [$parts[0], $middle !== '' ? $middle : $student?->m_name, $parts[1] ?? $parts[0]];
    }

    /**
     * A readable name for the stored list row, whichever shape came in.
     *
     * @param  array{name: string, last: string, first: string, middle: string}  $raw
     */
    private function displayName(array $raw): string
    {
        if ($raw['name'] !== '') {
            return $raw['name'];
        }

        $given = trim($raw['first'].' '.$raw['middle']);

        return trim($raw['last'] !== '' && $given !== '' ? $raw['last'].', '.$given : $raw['last'].$given);
    }
}
