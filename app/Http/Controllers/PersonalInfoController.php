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
        $validated = $request->validated();

        $student->f_name = $validated['first_name'];
        $student->l_name = $validated['last_name'];
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
