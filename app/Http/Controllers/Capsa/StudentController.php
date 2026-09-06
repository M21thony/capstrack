<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A CaPSA's own students: the ones in the sections they handle.
 *
 * These used to live on the admin page, which meant the person who runs the
 * subject had to ask someone else to fix a section. They are here now, and
 * gone from there - a button an admin cannot use is a smaller problem than an
 * endpoint they still can.
 *
 * Every method is scoped the same way: a student belongs to you when the
 * section computed from their course and year matches one of yours. That is
 * the whole rule, and it is written once in mine().
 */
class StudentController extends Controller
{
    /** Sections this CaPSA handles - an admin is not scoped. */
    private function sections(): ?array
    {
        $viewer = auth('expert')->user();

        if (! $viewer || $viewer->is_admin) {
            return null;
        }

        return $viewer->sections ?? [];
    }

    /**
     * The stored shape of a year and section: "4 · A".
     *
     * Callers send the year and the letter separately and this puts them
     * together, because the separator is a middle dot with spaces around it -
     * a caller sending "4A" gets the label "BSIT-4" and no error, which is
     * the kind of wrong that is only noticed when a student cannot see their
     * own resources.
     */
    private static function yearSection(string $year, string $section): string
    {
        // The roster import writes "4th", not "4". Both compute to the same
        // section label, so nothing breaks either way - but a student added
        // here would read "4" in the Year column while everyone imported
        // beside them read "4th", which looks like a data fault and is not.
        $n = (int) preg_replace('/[^0-9]/', '', $year);
        $ordinal = match ($n) {
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            default => $n.'th',
        };

        return $ordinal.' · '.strtoupper(trim($section));
    }

    /** True when this student sits in a section the viewer handles. */
    private function mine(Student $student): bool
    {
        $sections = $this->sections();

        if ($sections === null) {
            return true;
        }

        $label = Student::computeSectionLabel($student->course, $student->year_section);

        return $label !== null && in_array($label, $sections, true);
    }

    /**
     * The section a change would put them in, so a CaPSA cannot move somebody
     * into a colleague's section any more than out of one.
     */
    private function targetAllowed(?string $course, ?string $yearSection): bool
    {
        $sections = $this->sections();

        if ($sections === null) {
            return true;
        }

        $label = Student::computeSectionLabel($course, $yearSection);

        return $label !== null && in_array($label, $sections, true);
    }

    /** POST /capsa/students — take on somebody one at a time. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190', Rule::unique('students', 'email')],
            'f_name' => ['required', 'string', 'max:60'],
            'l_name' => ['required', 'string', 'max:60'],
            'student_number' => ['nullable', 'string', 'max:30'],
            'course' => ['required', Rule::in(array_keys(Student::PROGRAM_COURSES))],
            'year' => ['required', 'integer', 'between:1,5'],
            'section' => ['required', 'string', 'max:4', 'regex:/^[A-Za-z]+$/'],
        ]);

        $email = Str::lower(trim($data['email']));
        $yearSection = self::yearSection((string) $data['year'], $data['section']);

        if (! $this->targetAllowed($data['course'], $yearSection)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only add a student to a section you handle.',
            ], 422);
        }

        // The account is a placeholder until they sign in with this address -
        // which is why nothing here asks for a password.
        $student = Student::create([
            'email' => $email,
            'f_name' => trim($data['f_name']),
            'l_name' => trim($data['l_name']),
            'student_number' => $data['student_number'] ?? null,
            'course' => $data['course'],
            'year_section' => $yearSection,
        ]);

        return response()->json(['success' => true, 'student' => $student], 201);
    }

    /**
     * PATCH /capsa/students/{student} — change some of what is known about
     * them. Partial on purpose: the row editor sends everything, and setting a
     * section for a group sends only that.
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        if (! $this->mine($student)) {
            return response()->json([
                'success' => false,
                'message' => 'That student is not in one of your sections.',
            ], 403);
        }

        $data = $request->validate([
            'f_name' => ['sometimes', 'string', 'max:60'],
            'l_name' => ['sometimes', 'string', 'max:60'],
            'student_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'course' => ['sometimes', Rule::in(array_keys(Student::PROGRAM_COURSES))],
            'year' => ['sometimes', 'integer', 'between:1,5'],
            'section' => ['sometimes', 'string', 'max:4', 'regex:/^[A-Za-z]+$/'],
        ]);

        // Where they would end up, not just where they are - otherwise a move
        // out of your section into someone else's would pass the check above
        // and then be beyond your reach to undo.
        $course = $data['course'] ?? $student->course;

        $parts = explode(' · ', (string) $student->year_section);
        $year = $data['year'] ?? preg_replace('/[^0-9]/', '', $parts[0] ?? '');
        $section = $data['section'] ?? ($parts[1] ?? '');
        $yearSection = self::yearSection((string) $year, (string) $section);

        if (! $this->targetAllowed($course, $yearSection)) {
            return response()->json([
                'success' => false,
                'message' => 'That would move them into a section you do not handle.',
            ], 422);
        }

        unset($data['year'], $data['section']);
        $student->fill($data + ['course' => $course, 'year_section' => $yearSection])->save();

        return response()->json(['success' => true, 'student' => $student->fresh()]);
    }

    /**
     * DELETE /capsa/students/{student} — take them off your roster.
     *
     * The account stays. Deleting it would take their team history and their
     * submissions with it, and a student leaving your section is usually
     * joining someone else's rather than leaving the college.
     */
    public function destroy(Student $student): JsonResponse
    {
        if (! $this->mine($student)) {
            return response()->json([
                'success' => false,
                'message' => 'That student is not in one of your sections.',
            ], 403);
        }

        $student->update(['course' => null, 'year_section' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Removed from your section. Their account and history are kept.',
        ]);
    }
}
