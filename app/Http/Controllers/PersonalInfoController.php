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

        return view('student.personal-info', [
            'student' => $student,
            'presets' => config('avatars.presets', []),
            'isEditing' => false,
            // Pre-split from Google's given_name / family_name stored at login
            'autoFillFirstName' => $student->f_name,
            'autoFillLastName' => $student->l_name,
        ]);
    }

    public function edit(): View|RedirectResponse
    {
        $student = auth()->user();

        if (! $student->hasCompletedProfile()) {
            return redirect()->route('student.personal-info');
        }

        $hasTeam = StudentSchoolYear::where('student_id', $student->student_id)
            ->whereNotNull('team_id')
            ->exists();

        return view('student.personal-info', [
            'student' => $student,
            'presets' => config('avatars.presets', []),
            'isEditing' => true,
            'hasTeam' => $hasTeam,
            'autoFillFirstName' => $student->f_name,
            'autoFillLastName' => $student->l_name,
        ]);
    }

    public function store(StorePersonalInfoRequest $request): RedirectResponse
    {
        $student = $request->user();
        $validated = $request->validated();

        $student->f_name = $validated['first_name'] ?? $student->f_name;
        $student->m_name = $validated['middle_name'] ?? $student->m_name;
        $student->l_name = $validated['last_name'] ?? $student->l_name;
        $student->student_number = $validated['student_id'] ?? $student->student_number;
        $student->course = $validated['course'] ?? $student->course;
        $student->year_section = isset($validated['year'], $validated['section'])
            ? $validated['year'].' · '.$validated['section']
            : $student->year_section;
        $student->p_picture = $validated['avatar'];

        $student->save();

        $program = Student::mapCourseToProgram($student->course);

        if ($program) {
            StudentSchoolYear::updateOrCreate(
                ['student_id' => $student->student_id],
                [
                    'program' => $program,
                    'student_status' => 'Active',
                ]
            );
        }

        return redirect()
            ->route('student.dashboard')
            ->with('status', 'Your profile has been saved. Welcome to CapsTrack!');
    }
}
