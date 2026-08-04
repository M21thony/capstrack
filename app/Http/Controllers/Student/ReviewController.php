<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\Student;
use App\Models\StudentSchoolYear;

class ReviewController extends Controller
{
    public function index()
    {
        /** @var Student $student */
        $student = auth()->user();

        $studentSy = StudentSchoolYear::where('student_id', $student->student_id)->first();
        $teamId = $studentSy?->team_id;

        $documents = CapstoneDocument::where('student_id', $student->student_id)
            ->when($teamId, fn ($q) => $q->orWhereIn('student_id', function ($q) use ($teamId) {
                $q->select('student_id')->from('student_s_y')->where('team_id', $teamId);
            }))
            ->orderByDesc('created_at')
            ->get();

        $fileIds = $documents->pluck('file_id');

        $reviews = CapstoneDocumentReview::whereIn('file_id', $fileIds)
            ->with('expert')
            ->orderByDesc('reviewed_at')
            ->get()
            ->groupBy('file_id');

        return view('student.review', [
            'documents' => $documents,
            'reviews' => $reviews,
        ]);
    }
}
