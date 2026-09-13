<?php

namespace App\Http\Controllers\Capsa;

use App\Http\Controllers\Controller;
use App\Models\CapstoneDocument;
use App\Support\SectionScope;
use App\Support\StudentTeam;
use Illuminate\Support\Facades\Storage;

/**
 * A team's uploaded file, for the CaPSA to look at before a signed paper or
 * an approval counts on the checklist.
 *
 * Read-only, and separate from the annotator: the annotator is the assigned
 * reviewer's, and the CaPSA is not reviewing the document - she is checking
 * that the signed copy exists and is what it says.
 */
class DocumentFileController extends Controller
{
    public function __construct(private SectionScope $sections) {}

    public function show(CapstoneDocument $document)
    {
        $team = StudentTeam::for($document->student_id);
        $capsa = auth('expert')->user();

        abort_unless($team && $this->sections->covers($capsa->sections ?? [], $this->sections->sectionFor($team)), 403);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        $type = match (strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'application/pdf',
        };

        return Storage::disk('local')->response($document->file_path, null, ['Content-Type' => $type]);
    }
}
