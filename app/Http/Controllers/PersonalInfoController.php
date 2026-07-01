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

        if (StudentSchoolYear::where('student_id', $student->student_id)->whereNotNull('team_id')->exists()) {
            return redirect()->route('student.team')
                ->with('error', 'You must leave your team first before editing your profile.');
        }

        return view('student.personal-info', [
            'student' => $student,
            'presets' => config('avatars.presets', []),
            'isEditing' => true,
            'autoFillFirstName' => $student->f_name,
            'autoFillLastName' => $student->l_name,
        ]);
    }

    public function store(StorePersonalInfoRequest $request): RedirectResponse
    {
        $student = $request->user();

        if ($student->hasCompletedProfile()
            && StudentSchoolYear::where('student_id', $student->student_id)->whereNotNull('team_id')->exists()) {
            return redirect()->route('student.team')
                ->with('error', 'You must leave your team first before editing your profile.');
        }

        $validated = $request->validated();

        $student->f_name = $validated['first_name'];
        $student->m_name = $validated['middle_name'] ?? null;
        $student->l_name = $validated['last_name'];
        $student->student_number = $validated['student_id'];
        $student->course = $validated['course'];
        $student->year_section = $validated['year'].' · '.strtoupper(substr($validated['section'], 0, 1));
        $student->p_picture = $validated['avatar'];

        $student->save();

        $program = Student::mapCourseToProgram($validated['course']);

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
