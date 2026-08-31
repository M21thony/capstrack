<?php

namespace App\Http\Controllers;

use App\Models\CapstoneDocumentReview;
use App\Models\PdfRemark;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfAnnotationController extends Controller
{
    /**
     * Ensure the authenticated expert owns the given review.
     */
    private function authorizeReview(CapstoneDocumentReview $review): void
    {
        abort_if($review->expert_id !== Auth::guard('expert')->id(), 403, 'You do not have access to this review.');
    }

    public function file(CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        $document = $review->document;

        // Always serve the clean, unmodified local copy here. The Google Drive
        // copy is a baked (flattened) export produced by syncDrive() for
        // viewing outside the annotator — it must never be read back into the
        // editor, or annotations get permanently drawn onto the PDF pixels
        // and then re-drawn again as an overlay on top of themselves every
        // time the page is saved and reloaded.
        abort_unless($document && $document->file_path, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, null, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function index(CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        return $review->pdfRemarks()->where('is_general', false)->get();
    }

    public function store(Request $request, CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        $validated = $request->validate([
            'comments' => 'present|array',
            'comments.*.page_number' => 'required|integer|min:1',
            'comments.*.text' => 'nullable|string',
            'comments.*.comment' => 'nullable|string',
            'comments.*.selectedText' => 'nullable|string',
            'comments.*.section' => 'nullable|string|max:150',
            'comments.*.color' => 'nullable|string',
            'comments.*.highlightRects' => 'nullable|array',
            'comments.*.highlightRects.*.top_position' => 'nullable|numeric',
            'comments.*.highlightRects.*.left_position' => 'nullable|numeric',
            'comments.*.highlightRects.*.highlight_width' => 'nullable|numeric',
            'comments.*.highlightRects.*.highlight_height' => 'nullable|numeric',
            'comments.*.y_position' => 'nullable|numeric',
            'comments.*.x_position' => 'nullable|numeric',
        ]);

        $review->pdfRemarks()->where('is_general', false)->delete();

        foreach ($validated['comments'] as $comment) {
            $rects = $comment['highlightRects'] ?? [];

            if (! empty($rects)) {
                $minTop = min(array_column($rects, 'top_position'));
                $minLeft = min(array_column($rects, 'left_position'));
                $maxBottom = max(array_map(fn ($r) => $r['top_position'] + ($r['highlight_height'] ?? 0), $rects));
                $maxRight = max(array_map(fn ($r) => $r['left_position'] + ($r['highlight_width'] ?? 0), $rects));

                $review->pdfRemarks()->create([
                    'page_number' => $comment['page_number'],
                    'top_position' => $minTop,
                    'left_position' => $minLeft,
                    'highlight_width' => $maxRight - $minLeft,
                    'highlight_height' => $maxBottom - $minTop,
                    'color' => $comment['color'] ?? 'yellow',
                    'comment' => $comment['text'] ?? $comment['comment'] ?? '',
                    'selected_text' => $comment['selectedText'] ?? null,
                    'section' => $comment['section'] ?? null,
                ]);
            } else {
                $review->pdfRemarks()->create([
                    'page_number' => $comment['page_number'],
                    'top_position' => $comment['y_position'] ?? 0,
                    'left_position' => $comment['x_position'] ?? 0,
                    'highlight_width' => 0,
                    'highlight_height' => 0,
                    'color' => $comment['color'] ?? 'yellow',
                    'comment' => $comment['text'] ?? $comment['comment'] ?? '',
                    'selected_text' => $comment['selectedText'] ?? null,
                    'section' => $comment['section'] ?? null,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'counts' => ['remarks' => count($validated['comments'])],
        ]);
    }

    /**
     * General (paper-level) comments — not tied to a page or highlight.
     */
    public function generalIndex(CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        return $review->pdfRemarks()
            ->where('is_general', true)
            ->orderBy('created_at')
            ->get();
    }

    public function generalStore(Request $request, CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        $validated = $request->validate([
            'comment' => 'required|string',
            'section' => 'nullable|string|max:150',
        ]);

        $remark = $review->pdfRemarks()->create([
            'is_general' => true,
            'page_number' => 0,
            'comment' => $validated['comment'],
            'section' => $validated['section'] ?? null,
        ]);

        return response()->json($remark, 201);
    }

    public function generalDestroy(CapstoneDocumentReview $review, PdfRemark $remark)
    {
        $this->authorizeReview($review);

        abort_unless(
            $remark->review_id === $review->review_id && $remark->is_general,
            404
        );

        $remark->delete();

        return response()->json(['success' => true]);
    }

    public function showStatus(CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        return response()->json(['review_status' => $review->review_status]);
    }

    public function updateStatus(Request $request, CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        $status = $request->input('review_status');

        if (! in_array($status, CapstoneDocumentReview::STATUSES, true)) {
            return response()->json([
                'message' => 'The selected review status is invalid.',
            ], 422);
        }

        // Stamp when the review was finished. Nothing has ever written
        // reviewed_at - it was only read - so every row held null, including
        // the ones marked Reviewed. That left the instructor dashboard's
        // "Oldest: ..." label blank and made two orderings meaningless.
        // Moving a review back off Reviewed clears it again, so the column
        // never claims a finish that was undone.
        $review->update([
            'review_status' => $status,
            'reviewed_at' => $status === CapstoneDocumentReview::REVIEWED ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'review_status' => $review->review_status,
        ]);
    }

    /**
     * Replace the Google Drive copy of the PDF with the annotated version.
     *
     * This is a one-way export for viewing outside the annotator (e.g. a
     * student or committee member opening the file directly in Drive). The
     * annotator itself must never read this file back in — see file() above.
     */
    public function syncDrive(Request $request, CapstoneDocumentReview $review, GoogleDriveService $drive)
    {
        $this->authorizeReview($review);

        $contents = $request->getContent();

        if (! $contents) {
            return response()->json(['message' => 'No PDF content received.'], 422);
        }

        if (! $review->drive_file_id) {
            return response()->json(['message' => 'This review has no Google Drive file to update.'], 422);
        }

        try {
            $drive->updateFile($review->expert_id, $review->drive_file_id, $contents, 'application/pdf');
        } catch (\Exception $e) {
            Log::error('Drive sync failed', [
                'review_id' => $review->review_id,
                'expert_id' => $review->expert_id,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true]);
    }
}