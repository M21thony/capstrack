<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

        $rows = IOFactory::load($request->file('file')->getRealPath())
            ->getActiveSheet()
            ->toArray();

        $header = array_shift($rows);
        $columns = $this->mapHeadings($header ?? []);

        if (! isset($columns['email'])) {
            return response()->json([
                'success' => false,
                'message' => 'That file has no Email column, and email is the only thing a student account can be matched on.',
            ], 422);
        }

        $importedBy = Auth::guard('expert')->id();
        $schoolYear = Term::currentSchoolYear();

        $inserted = 0;
        $matched = 0;
        $filled = 0;
        $alreadyComplete = 0;
        $skipped = 0;
        $unmatched = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $get = fn (string $key) => isset($columns[$key]) ? trim((string) ($row[$columns[$key]] ?? '')) : '';

            $email = mb_strtolower($get('email'));
            $name = $get('name');

            if ($email === '' && $name === '') {
                continue; // a blank line at the end of the sheet
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $errors[] = "Row {$rowNum}: '".($email ?: '(blank)')."' is not a valid email - skipped.";

                continue;
            }

            $courseCode = Student::mapCourseToProgram($get('course'));
            $yearSection = $this->yearSection($get('year'), $get('section'), $get('year_section'));

            StudentRoster::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'student_number' => $get('student_number') ?: null,
                    'section' => $yearSection,
                    'program' => $courseCode ?? '',
                    'school_year' => $get('school_year') ?: $schoolYear,
                    'imported_by' => $importedBy,
                ]
            );

            $inserted++;

            // The point of the whole upload: a student who signed in before
            // this list existed has no student number, course or section on
            // their profile. Fill them in from the official list.
            $student = Student::whereRaw('LOWER(email) = ?', [$email])->first();

            if (! $student) {
                $unmatched[] = $email;

                continue;
            }

            [$first, $last] = $this->splitName($name, $student);

            // Only what the account is missing. A student who filled their own
            // profile in has said what their course and section are, and the
            // list is a spreadsheet kept by hand - overwriting them would let
            // one stale row quietly rename a student or move them out of their
            // section. Blanks are what this upload exists to fix.
            $fill = array_filter([
                'f_name' => $first,
                'l_name' => $last,
                'student_number' => $get('student_number'),
                'course' => $courseCode ? Student::PROGRAM_COURSES[$courseCode] : null,
                'year_section' => $yearSection,
            ], fn ($value, $field) => filled($value) && blank($student->{$field}), ARRAY_FILTER_USE_BOTH);

            if ($fill !== []) {
                $student->update($fill);
                $filled += count($fill);
            } else {
                $alreadyComplete++;
            }

            $matched++;
        }

        return response()->json([
            'success' => true,
            'inserted' => $inserted,
            'matched' => $matched,
            'filled' => $filled,
            'already_complete' => $alreadyComplete,
            'skipped' => $skipped,
            'unmatched' => $unmatched,
            'errors' => $errors,
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

        $letter = $section !== '' ? mb_strtoupper(mb_substr($section, 0, 1)) : '';

        if ($year === '') {
            return $letter;
        }

        $n = (int) $year;
        $ordinal = match (true) {
            $n === 1 => '1st',
            $n === 2 => '2nd',
            $n === 3 => '3rd',
            $n >= 4 => $n.'th',
            default => $year,
        };

        return $letter === '' ? $ordinal : $ordinal.' · '.$letter;
    }

    /**
     * A name off a department list reads "Balilla, Mark Anthony B." - surname
     * first, then given names. Split on the comma, not the first space, which
     * would have made "Balilla," the first name.
     */
    private function splitName(string $name, Student $student): array
    {
        if ($name === '') {
            return [$student->f_name, $student->l_name];
        }

        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));

            return [$first !== '' ? $first : $student->f_name, $last];
        }

        $parts = preg_split('/\s+/', $name, 2);

        return [$parts[0], $parts[1] ?? $parts[0]];
    }
}
