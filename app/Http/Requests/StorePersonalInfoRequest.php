<?php

namespace App\Http\Requests;

use App\Models\StudentSchoolYear;
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

        // Must match PersonalInfoController::store()'s lock check exactly -
        // a student who already has a team_id but hasn't finished their
        // profile yet (e.g. added to a team before filling this out) still
        // needs to fill it in; only an already-completed profile locks.
        $hasTeam = auth()->user()?->hasCompletedProfile()
            && StudentSchoolYear::where('student_id', $userId)->whereNotNull('team_id')->exists();

        if ($hasTeam) {
            // Nothing is editable once a student has a team — every field
            // on the form is locked, including the avatar (now synced
            // automatically from their Google account at login).
            return [];
        }

        return [
            'first_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'student_id' => [
                'required',
                'string',
                'regex:/^\d{9}$/',
                Rule::unique('students', 'student_number')->ignore($userId, 'student_id'),
            ],
            'course' => ['required', 'string', 'max:100'],
            'year' => ['required', 'string', 'max:20'],
            'section' => ['required', 'string', 'max:1', 'alpha'],
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
