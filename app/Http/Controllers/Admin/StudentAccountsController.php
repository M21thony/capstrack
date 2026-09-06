<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
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
}
