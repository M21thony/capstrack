<?php

namespace App\Http\Controllers;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\PdfRemark;
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

    public function index(Request $request)
    {
        $studentId = Auth::id();

        // Every document anybody on this team uploaded, with all its reviews -
        // turned in or not, since the picker also says who is still reviewing.
        $documents = CapstoneDocument::forTeamOf($studentId)
            ->with([
                'deliverable',
                'reviews' => fn ($q) => $q->with('expert')->orderByDesc('reviewed_at'),
            ])
            ->orderByDesc('created_at')
            ->get();

        // The reviews actually turned in, newest document first - a document
        // can have more than one, since it may go to several reviewer roles.
        $turnedIn = $documents
            ->flatMap(fn ($document) => $document->reviews
                ->where('review_status', CapstoneDocumentReview::REVIEWED)
                ->map(fn ($review) => ['document' => $document, 'review' => $review]))
            ->values();

        $selectedReviewId = (int) $request->query('review_id', 0);
        $selected = $turnedIn->firstWhere('review.review_id', $selectedReviewId) ?? $turnedIn->first();

        return view('student.review', [
            'turnedIn' => $turnedIn,
            'selected' => $selected,
            'documents' => $documents->filter(fn ($d) => $d->reviews->isNotEmpty())->values(),
            'general' => $selected ? $this->generalRemarks($selected['review']) : [],
            'decision' => $selected ? $this->decision($selected['document'], $selected['review']) : null,
            'others' => $selected ? $this->otherReviews($selected['document'], $selected['review']) : [],
        ]);
    }

    public static function roleLabel(?string $role): string
    {
        return match ($role) {
            null, '' => 'Reviewer',
            'subject_adviser' => 'CaPSA',
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

    /** @return array<int, array{id: int, text: string, section: ?string}> */
    private function generalRemarks(CapstoneDocumentReview $review): array
    {
        return PdfRemark::where('review_id', $review->review_id)
            ->where('is_general', true)
            ->orderBy('created_at')
            ->get(['id', 'comment', 'section'])
            ->filter(fn ($r) => filled($r->comment))
            ->map(fn ($r) => ['id' => $r->id, 'text' => $r->comment, 'section' => $r->section])
            ->values()
            ->all();
    }

    /** What the reviewer decided, and what the team does next. */
    private function decision(CapstoneDocument $document, CapstoneDocumentReview $review): array
    {
        $item = $document->deliverable;

        return [
            'verdict' => $review->verdict,
            'reviewer' => $review->expert?->name ?? 'your reviewer',
            'role' => self::roleLabel($review->role),
            'date' => $review->reviewed_at?->format('M j'),
            'round' => $document->roundLabel(),
            'next' => $document->nextRoundLabel(),
            'due' => $item?->due_date?->format('M j'),
            'requirement' => $item?->title,
            // The submit panel opens on this page; it needs the line, not a URL.
            'itemId' => $item?->id,
            // With no line, the file is still this upload's next version, and
            // is checked against it.
            'fileId' => $document->file_id,
        ];
    }

    /** The same document's other reviewers, one click away once they have turned theirs in. */
    private function otherReviews(CapstoneDocument $document, CapstoneDocumentReview $selected): array
    {
        return $document->reviews
            ->reject(fn ($r) => $r->review_id === $selected->review_id)
            ->map(fn ($r) => [
                'label' => self::roleLabel($r->role).' · '.mb_strtolower(self::verdictLabel($r)),
                'url' => $r->review_status === CapstoneDocumentReview::REVIEWED
                    ? route('student.review', ['review_id' => $r->review_id])
                    : null,
            ])
            ->values()
            ->all();
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
            'mark_type', 'rects', 'figure_label', 'diagram_type',
        ]);
    }

    /** The draw.io files sent with the reviewed document, shown after its pages. */
    public function diagrams(CapstoneDocumentReview $review): array
    {
        $this->authorizeReview($review);

        return $review->document->diagrams();
    }
}
