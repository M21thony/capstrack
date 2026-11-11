<?php

namespace App\Http\Controllers;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use Illuminate\Http\RedirectResponse;
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
            && CapstoneDocument::forTeamOf(Auth::id())->whereKey($review->document->file_id)->exists(),
            403
        );
    }

    /**
     * The Review Viewer is part of Documents now. A link to a review - a
     * notification's, an email's - opens that file there with that
     * reviewer's remarks; anything else opens the list.
     */
    public function index(Request $request): RedirectResponse
    {
        $review = CapstoneDocumentReview::find($request->integer('review_id'));

        if ($review && CapstoneDocument::forTeamOf(Auth::id())->whereKey($review->file_id)->exists()) {
            return redirect()->route('student.documents', ['file' => $review->file_id, 'review' => $review->review_id]);
        }

        return redirect()->route('student.documents');
    }

    public static function roleLabel(?string $role): string
    {
        return match ($role) {
            null, '' => 'Reviewer',
            'subject_adviser' => 'CaPSA/TSA',
            'panel' => 'Panelist',
            default => ucwords(str_replace('_', ' ', $role)),
        };
    }

    public static function verdictLabel(CapstoneDocumentReview $review): string
    {
        return match (true) {
            $review->review_status !== CapstoneDocumentReview::REVIEWED => 'Still reviewing',
            $review->verdict === CapstoneDocumentReview::APPROVED => 'Approved',
            $review->verdict === CapstoneDocumentReview::NEEDS_REVISION => 'Needs revision',
            default => 'Reviewed',
        };
    }

    /**
     * Stream the clean, unmodified copy of the document behind a review.
     *
     * The Drive copy synced by the annotator has every annotation permanently
     * drawn onto the page pixels — fine as a one-off downloadable export, but
     * not something to preview inline. Comments are shown from pdf_remarks
     * instead, same as the annotator itself always reads the clean local file
     * rather than the baked Drive copy.
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
     * Page-anchored remarks for the viewer's highlights and remark cards.
     * General remarks come with the page itself - this only carries the ones
     * with a place on a page.
     */
    public function remarks(CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        return $review->pdfRemarks()->where('is_general', false)->get([
            'id', 'page_number', 'top_position', 'left_position',
            'highlight_width', 'highlight_height', 'color', 'comment', 'selected_text', 'section',
            'mark_type', 'rects', 'shape_ref', 'figure_label', 'diagram_type',
        ]);
    }

    /** The draw.io files sent with the reviewed document, shown after its pages. */
    public function diagrams(CapstoneDocumentReview $review): array
    {
        $this->authorizeReview($review);

        return $review->document->diagrams();
    }
}
