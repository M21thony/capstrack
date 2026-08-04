<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AdminDriveFolder;
use App\Models\Resource;
use App\Models\Student;
use App\Models\StudentSchoolYear;

class ResourceController extends Controller
{
    public function index()
    {
        /** @var Student $student */
        $student = auth()->user();

        $studentSy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $program = $studentSy?->program;

        $resources = Resource::orderByDesc('uploaded_at')
            ->when($program, fn ($q) => $q->where(function ($q) use ($program) {
                $q->where('resource_for', $program)->orWhereNull('resource_for');
            }))
            ->get();

        $folders = AdminDriveFolder::orderBy('name')->get();

        return view('student.resources', [
            'resources' => $resources,
            'folders' => $folders,
        ]);
    }
}
