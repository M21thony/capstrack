<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonalInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'first_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'student_id' => [
                'required',
                'string',
                'regex:/^\d{9}$/',
                Rule::unique('students', 'student_number')->ignore($userId, 'student_id'),
            ],
            'course' => ['required', 'string', 'max:100'],
            'year' => ['required', 'string', 'max:20'],
            'section' => ['required', 'string', 'max:50'],
            'avatar' => ['required', 'string', Rule::in(Student::allowedAvatarKeys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.regex' => 'Student ID must be exactly 9 digits (e.g. 221001135).',
            'student_id.unique' => 'This student ID is already registered.',
        ];
    }
}
