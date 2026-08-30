<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Account management for Students - a genuinely new surface, distinct from
// the existing admin/sections page (StudentSectionController), which is
// CAPSA's "browse the students in my assigned section(s)" tool, not
// Admin's "manage the account list" one.
class StudentAccountsController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $students = Student::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('f_name', 'like', "%{$search}%")
                        ->orWhere('l_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('l_name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.students', ['students' => $students, 'search' => $search]);
    }

    /**
     * Manually add a single student account, bypassing the roster-upload +
     * Google sign-in gate (e.g. a late enrollee who needs access now).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'f_name' => ['required', 'string', 'max:100'],
            'l_name' => ['required', 'string', 'max:100'],
            'm_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:190', 'unique:students,email'],
            'student_number' => ['nullable', 'string', 'max:50'],
            'course' => ['nullable', 'string', 'max:150'],
            'year_section' => ['nullable', 'string', 'max:50'],
        ]);

        $student = Student::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Student account for '.trim($student->f_name.' '.$student->l_name).' has been added.',
            'student_id' => $student->student_id,
        ]);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $validated = $request->validate([
            'f_name' => ['required', 'string', 'max:100'],
            'l_name' => ['required', 'string', 'max:100'],
            'm_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:190', 'unique:students,email,'.$student->student_id.',student_id'],
            'student_number' => ['nullable', 'string', 'max:50'],
            'course' => ['nullable', 'string', 'max:150'],
            'year_section' => ['nullable', 'string', 'max:50'],
        ]);

        $student->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Student account for '.trim($student->f_name.' '.$student->l_name).' has been updated.',
        ]);
    }

    public function destroy(Student $student): JsonResponse
    {
        $name = trim($student->f_name.' '.$student->l_name);
        $student->delete();

        return response()->json(['success' => true, 'message' => "Student account for {$name} has been removed."]);
    }
}
