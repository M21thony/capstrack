<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ArchivedDocTag;
use App\Models\ArchivedDocument;

class ArchiveController extends Controller
{
    public function index()
    {
        $archives = ArchivedDocument::with('tags')
            ->orderByDesc('academic_year')
            ->get();

        $tags = ArchivedDocTag::orderBy('tag_name')->get();

        return view('student.archive', [
            'archives' => $archives,
            'tags' => $tags,
        ]);
    }
}
