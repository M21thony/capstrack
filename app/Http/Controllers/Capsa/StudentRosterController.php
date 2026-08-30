<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentRoster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;

// CAPSA uploads a per-section student roster. Students never wait on this -
// they can register and use the site the moment they sign in with Google
// (see AuthController::handleGoogleCallback()), roster or no roster, with
// their academic fields simply starting null. This upload's job is to
// match this roster's rows to already-existing Student accounts by email
// and fill in/correct those fields with the official data - never to
// gate who's allowed to have an account in the first place. The upload
// itself is a modal on admin/sections.blade.php, not its own page - this
// controller only handles the store() the modal submits to.
class StudentRosterController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx'],
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray();
        array_shift($rows); // drop header: Name, Email, Student Number, Section, Program, School Year

        $importedBy = Auth::guard('expert')->id();
        $inserted = 0;
        $matched = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            [$name, $email, $studentNumber, $section, $program, $schoolYear] = array_pad($row, 6, null);

            $name = trim((string) $name);
            $email = trim((string) $email);
            $section = trim((string) $section);
            $program = trim((string) $program);
            $schoolYear = trim((string) $schoolYear);

            if ($name === '' || $email === '') {
                $skipped++;
                $errors[] = "Row {$rowNum}: missing name or email - skipped.";

                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $errors[] = "Row {$rowNum}: '{$email}' isn't a valid email - skipped.";

                continue;
            }

            StudentRoster::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'student_number' => $studentNumber !== null ? trim((string) $studentNumber) : null,
                    'section' => $section,
                    'program' => $program,
                    'school_year' => $schoolYear,
                    'imported_by' => $importedBy,
                ]
            );

            $inserted++;

            // Sync into an existing account by email, if one's already
            // registered - this is the whole point: a student who signed
            // up before this list existed gets their real academic info
            // filled in now, instead of being blocked from having an
            // account at all until this moment.
            $student = Student::where('email', $email)->first();

            if ($student) {
                $nameParts = explode(' ', $name, 2);

                $student->update([
                    'f_name' => $nameParts[0],
                    'l_name' => $nameParts[1] ?? $nameParts[0],
                    'student_number' => $studentNumber !== null ? trim((string) $studentNumber) : $student->student_number,
                    'course' => $program !== '' ? $program : $student->course,
                    'year_section' => $section !== '' ? $section : $student->year_section,
                ]);

                $matched++;
            }
        }

        return response()->json([
            'success' => true,
            'inserted' => $inserted,
            'matched' => $matched,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }
}
