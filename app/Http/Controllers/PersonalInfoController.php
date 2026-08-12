<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonalInfoRequest;
use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PersonalInfoController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $student = auth()->user();

        if ($student->hasCompletedProfile()) {
            return redirect()->route('student.dashboard');
        }

        $studentSy = StudentSchoolYear::where('student_id', $student->student_id)->first();

        return view('student.personal-info', [
            'student' => $student,
            'isEditing' => false,
            'hasTeam' => false,
            'autoFillFirstName' => $student->f_name,
            'autoFillLastName' => $student->l_name,
            'studentSy' => $studentSy,
        ]);
    }

    public function edit(): View|RedirectResponse
    {
        $student = auth()->user();

        if (! $student->hasCompletedProfile()) {
            return redirect()->route('student.personal-info');
        }

        $hasTeam = StudentSchoolYear::where('student_id', $student->student_id)->whereNotNull('team_id')->exists();
        $studentSy = StudentSchoolYear::where('student_id', $student->student_id)->first();

        return view('student.personal-info', [
            'student' => $student,
            'isEditing' => true,
            'hasTeam' => $hasTeam,
            'autoFillFirstName' => $student->f_name,
            'autoFillLastName' => $student->l_name,
            'studentSy' => $studentSy,
        ]);
    }

    public function store(StorePersonalInfoRequest $request): RedirectResponse
    {
        $student = $request->user();
        $validated = $request->validated();

        $hasTeam = $student->hasCompletedProfile()
            && StudentSchoolYear::where('student_id', $student->student_id)->whereNotNull('team_id')->exists();

        if ($hasTeam) {
            // Every field is locked while in a team (avatar included — it's
            // synced automatically from Google at login), so there's
            // nothing left for this submission to do.
            return redirect()
                ->route('student.dashboard')
                ->with('status', 'Your profile is locked while you\'re in a team.');
        }

        $student->f_name = $validated['first_name'];
        $student->m_name = $validated['middle_name'] ?? null;
        $student->l_name = $validated['last_name'];
        $student->suffix = $validated['suffix'] ?? null;
        $student->student_number = $validated['student_id'];
        $student->course = $validated['course'];
        $student->year_section = $validated['year'].' · '.strtoupper(substr($validated['section'], 0, 1));

        $student->save();

        $program = Student::mapCourseToProgram($validated['course']);

        if ($program) {
            $schoolYear = now()->month >= 8
                ? now()->year.'-'.(now()->year + 1)
                : (now()->year - 1).'-'.now()->year;

            StudentSchoolYear::updateOrCreate(
                ['student_id' => $student->student_id],
                [
                    'program' => $program,
                    'school_year' => $schoolYear,
                    'student_status' => 'Active',
                ]
            );
        }

        return redirect()
            ->route('student.dashboard')
            ->with('status', 'Your profile has been saved. Welcome to CapsTrack!');
    }
}
