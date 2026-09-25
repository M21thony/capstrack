<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentRoster;
use App\Models\StudentSchoolYear;
use App\Models\Term;
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

    /**
     * The name as the class list writes it - "Dela Cruz, Juan Bautista" -
     * so a student added or edited here reads like the ones uploaded
     * beside them.
     *
     * @param  array{f_name?: ?string, m_name?: ?string, l_name?: ?string}  $name
     */
    private static function listName(array $name): string
    {
        $given = trim(($name['f_name'] ?? '').' '.($name['m_name'] ?? ''));
        $last = trim((string) ($name['l_name'] ?? ''));

        return $last !== '' && $given !== '' ? $last.', '.$given : trim($last.$given);
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
            // Not unique:students. Removing a student from a section keeps
            // their account and only clears the section, so the address of
            // somebody you removed a minute ago is still in the table -
            // refusing it here made that removal impossible to undo. An
            // address that belongs to somebody another CaPSA still handles
            // is refused below, where the reason can be stated.
            'email' => ['required', 'email', 'max:190'],
            'f_name' => ['required', 'string', 'max:60'],
            'm_name' => ['nullable', 'string', 'max:60'],
            'l_name' => ['required', 'string', 'max:60'],
            'student_number' => ['nullable', 'string', 'max:30'],
            'course' => ['required', Rule::in(array_keys(Student::PROGRAM_COURSES))],
            // Only 3rd and 4th year take capstone, so those are the only
            // years a student can be put into from here.
            'year' => ['required', 'integer', Rule::in(Student::CAPSTONE_YEARS)],
            'section' => ['required', 'string', 'max:4', 'regex:/^[A-Za-z]+$/'],
        ]);

        $email = Str::lower(trim($data['email']));
        $yearSection = self::yearSection((string) $data['year'], $data['section']);
        $schoolYear = Term::currentSchoolYear();

        if (! $this->targetAllowed($data['course'], $yearSection)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only add a student to a section you handle.',
            ], 422);
        }

        $existing = Student::whereRaw('LOWER(email) = ?', [$email])->first();

        // Somebody already in a section is somebody else's student, or
        // already yours - either way this is not the way to move them.
        if ($existing && filled($existing->year_section)) {
            return response()->json([
                'success' => false,
                'message' => $this->mine($existing)
                    ? 'That student is already in one of your sections.'
                    : 'That address belongs to a student in another section.',
            ], 422);
        }

        $fields = [
            'f_name' => trim($data['f_name']),
            'm_name' => filled($data['m_name'] ?? null) ? trim($data['m_name']) : null,
            'l_name' => trim($data['l_name']),
            'student_number' => $data['student_number'] ?? null,
            // The full course name, as the class-list upload stores it.
            // Classmates are matched on this column, so a student saved as
            // "BSIT" never showed up beside the ones saved from the list.
            'course' => Student::PROGRAM_COURSES[$data['course']],
            'year_section' => $yearSection,
        ];

        // Taking back a student who was removed from a section, rather than
        // making a second account for the same person. Their team, their
        // submissions and everything else hanging off student_id stay.
        // The invitation. Being on this list is what lets a student sign
        // in - the upload writes it for a whole class, and adding one by
        // hand has to write it too, or the student a CaPSA just added is
        // turned away at the door.
        StudentRoster::updateOrCreate(
            ['email' => $email],
            [
                'name' => self::listName($fields),
                'student_number' => $data['student_number'] ?? null,
                'section' => $yearSection,
                'program' => $data['course'],
                // The school year, from wherever the Admin set it.
                'school_year' => $schoolYear,
            ]
        );

        if ($existing) {
            $existing->update($fields);
            StudentSchoolYear::recordFor($existing->student_id, $schoolYear, Student::mapCourseToProgram((string) ($data['course'] ?? '')));

            return response()->json([
                'success' => true,
                'student' => $existing,
                'readded' => true,
                'message' => trim($existing->f_name.' '.$existing->l_name).' is back in your section, with their account and history intact.',
            ]);
        }

        // The account is a placeholder until they sign in with this address -
        // which is why nothing here asks for a password.
        $student = Student::create(['email' => $email] + $fields);

        StudentSchoolYear::recordFor($student->student_id, $schoolYear, Student::mapCourseToProgram((string) ($data['course'] ?? '')));

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
            'm_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'l_name' => ['sometimes', 'string', 'max:60'],
            'student_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'course' => ['sometimes', Rule::in(array_keys(Student::PROGRAM_COURSES))],
            'year' => ['sometimes', 'integer', Rule::in(Student::CAPSTONE_YEARS)],
            'section' => ['sometimes', 'string', 'max:4', 'regex:/^[A-Za-z]+$/'],
            // Deliberate, and asked for in the dialog. A CaPSA may hand a
            // student to a colleague; they may not do it by accident, and
            // they may not reach back afterwards.
            'transfer' => ['sometimes', 'boolean'],
        ]);

        // Where they would end up, not just where they are - otherwise a move
        // out of your section into someone else's would pass the check above
        // and then be beyond your reach to undo.
        // Stored as the full course name, as the class-list upload does.
        $course = isset($data['course']) ? Student::PROGRAM_COURSES[$data['course']] : $student->course;
        unset($data['course']);

        $parts = explode(' · ', (string) $student->year_section);
        $year = $data['year'] ?? preg_replace('/[^0-9]/', '', $parts[0] ?? '');
        $section = $data['section'] ?? ($parts[1] ?? '');
        $yearSection = self::yearSection((string) $year, (string) $section);

        $leavingYou = ! $this->targetAllowed($course, $yearSection);

        // A student moving to another CaPSA's section is a transfer, not an
        // edit: correct to allow, wrong to do without meaning to, and beyond
        // your reach to undo once it is done. So it is refused unless the
        // request says that is what it is - which the dialog asks first.
        if ($leavingYou && ! ($data['transfer'] ?? false)) {
            return response()->json([
                'success' => false,
                'needs_transfer' => true,
                'section' => Student::computeSectionLabel($course, $yearSection),
                'message' => 'That section is not one of yours. Transferring hands this student to '
                    .'the CaPSA who handles it, and you will not be able to edit them afterwards.',
            ], 409);
        }

        unset($data['year'], $data['section'], $data['transfer']);

        if (array_key_exists('m_name', $data)) {
            $data['m_name'] = filled($data['m_name']) ? trim($data['m_name']) : null;
        }

        $student->fill($data + ['course' => $course, 'year_section' => $yearSection])->save();

        $this->keepOnList($student);

        $moved = Student::computeSectionLabel($course, $yearSection);

        return response()->json([
            'success' => true,
            'student' => $student->fresh(),
            'transferred' => $leavingYou,
            'message' => $leavingYou
                ? trim($student->f_name.' '.$student->l_name).' has been transferred to '.$moved.'.'
                : 'Saved.',
        ]);
    }

    /**
     * Make the class list agree with the student a CaPSA just saved.
     *
     * The list is what admits somebody at sign-in, and the page a CaPSA works
     * from is built from the accounts, not the list. The two drifted: a
     * student who signed in before any list was uploaded had an account and
     * a section, showed in their CaPSA's section, and was still turned away
     * as "not on your section's list". Saving them here is the moment a
     * CaPSA vouches for them, so the list is written then, and carries the
     * section they now have rather than the one they were uploaded with.
     */
    private function keepOnList(Student $student): void
    {
        $email = Str::lower(trim((string) $student->email));
        $program = Student::mapCourseToProgram((string) $student->course);

        if ($email === '' || $program === null || blank($student->year_section)) {
            return;
        }

        $fields = [
            'name' => self::listName($student->only(['f_name', 'm_name', 'l_name'])) ?: $email,
            'student_number' => $student->student_number,
            'section' => $student->year_section,
            'program' => $program,
        ];

        $listed = StudentRoster::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($listed) {
            $listed->update($fields);

            return;
        }

        StudentRoster::create(['email' => $email, 'school_year' => Term::currentSchoolYear()] + $fields);
    }

    /**
     * DELETE /capsa/students/{student} — take them off your roster.
     *
     * The account stays. Deleting it would take their team history and their
     * submissions with it, and a student leaving your section is usually
     * joining someone else's rather than leaving the college.
     *
     * The roster entry goes, though. That row - not the account - is what
     * admits somebody at sign-in, so leaving it behind meant a student
     * removed here signed in again and had their account rebuilt from it,
     * looking for all the world like the removal had never happened.
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

        // The invitation, withdrawn. Without this the next sign-in reads the
        // roster, finds them still listed, and puts the section straight
        // back.
        StudentRoster::whereRaw('LOWER(email) = ?', [Str::lower($student->email)])->delete();

        return response()->json([
            'success' => true,
            'message' => 'Removed from your section. Their account and history are kept.',
        ]);
    }
}
