<?php

namespace App\Http\Controllers;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\PdfRemark;
use App\Support\StudentTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StudentReviewController extends Controller
{
    /**
     * Ensure this review is on a document belonging to the student's team.
     *
     * It used to be the uploader's own document and nobody else's, which
     * meant a teammate could not open a review of work their team submitted -
     * not the annotated copy, not the remarks, nothing. A capstone document
     * is the team's; who happened to click Upload is not who it belongs to.
     */
    private function authorizeReview(CapstoneDocumentReview $review): void
    {
        abort_unless(
            $review->document
            && in_array((int) $review->document->student_id, StudentTeam::memberIdsFor(Auth::id()), true),
            403
        );
    }

    public function index(Request $request)
    {
        $studentId = Auth::id();

        // Every review that's actually been turned in (status = Reviewed) for
        // documents anybody on this team uploaded, newest first — a document
        // can have more than one, since it may go to several reviewer roles.
        $turnedIn = CapstoneDocument::whereIn('student_id', StudentTeam::memberIdsFor($studentId))
            ->with(['reviews' => function ($q) {
                $q->where('review_status', 'Reviewed')
                    ->with('expert')
                    ->orderByDesc('reviewed_at');
            }])
            ->orderByDesc('created_at')
            ->get()
            ->flatMap(fn ($document) => $document->reviews->map(fn ($review) => [
                'document' => $document,
                'review' => $review,
            ]))
            ->values();

        $selectedReviewId = (int) $request->query('review_id', 0);
        $selected = $turnedIn->firstWhere('review.review_id', $selectedReviewId) ?? $turnedIn->first();

        $grouped = $selected
            ? PdfRemark::groupedForReview($selected['review']->review_id)
            : ['bySection' => collect(), 'remarks' => collect(), 'general' => collect()];

        $totalAnnotations = $grouped['bySection']->flatten(1)->count() + $grouped['remarks']->count() + $grouped['general']->count();

        return view('student.review', [
            'turnedIn' => $turnedIn,
            'selected' => $selected,
            'remarksBySection' => $grouped['bySection'],
            'remarks' => $grouped['remarks'],
            'generalRemarks' => $grouped['general'],
            'totalAnnotations' => $totalAnnotations,
        ]);
    }

    /**
     * Stream the clean, unmodified copy of the document behind a review.
     *
     * The Drive copy synced by the annotator has every annotation permanently
     * drawn onto the page pixels — fine as a one-off downloadable export, but
     * not something to preview inline. Comments are shown from pdf_remarks
     * instead (see index() above), same as the annotator itself always reads
     * the clean local file rather than the baked Drive copy.
     */
    public function file(CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        $document = $review->document;

        abort_unless($document->file_path, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, null, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Page-anchored remarks for the read-only viewer's highlight overlays.
     * General/sectioned comments are already rendered server-side in
     * index() — this only needs to carry per-page geometry.
     */
    public function remarks(CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        return $review->pdfRemarks()->where('is_general', false)->get([
            'id', 'page_number', 'top_position', 'left_position',
            'highlight_width', 'highlight_height', 'color', 'comment', 'selected_text',
        ]);
    }
}
