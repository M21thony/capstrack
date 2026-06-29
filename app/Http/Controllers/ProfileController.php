<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentSchoolYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $student = $request->user();

        if (StudentSchoolYear::where('student_id', $student->student_id)->whereNotNull('team_id')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You must leave your team first before editing your profile.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'student_id' => [
                'nullable',
                'string',
                'regex:/^\d{9}$/',
                Rule::unique('students', 'student_number')->ignore($student->student_id, 'student_id'),
            ],
            'course' => ['nullable', 'string', Rule::in([
                'Bachelor of Science in Computer Science',
                'Bachelor of Science in Information Technology',
                'Bachelor of Science in Information Systems',
                'Bachelor of Library and Information Science',
            ])],
            'year_section' => ['nullable', 'string', 'max:50'],
            'avatar' => ['nullable', 'string', Rule::in(Student::allowedAvatarKeys())],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $student->f_name = $request->input('first_name');
        $student->l_name = $request->input('last_name');

        if ($request->filled('student_id')) {
            $student->student_number = $request->input('student_id');
        }

        if ($request->filled('year_section')) {
            $student->year_section = $request->input('year_section');
        }

        if ($request->filled('avatar')) {
            $student->p_picture = $request->input('avatar');
        }

        if ($request->filled('course')) {
            $student->course = $request->input('course');
        }

        $student->save();

        if ($request->filled('course')) {
            $program = Student::mapCourseToProgram($request->input('course'));

            if ($program) {
                StudentSchoolYear::updateOrCreate(
                    ['student_id' => $student->student_id],
                    ['program' => $program, 'student_status' => 'Active']
                );
            }
        }

        return response()->json([
            'success' => true,
            'avatar_url' => $student->avatarUrl(),
            'initials' => $student->avatarInitials(),
        ]);
    }
}
