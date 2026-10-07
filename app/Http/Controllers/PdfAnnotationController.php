<?php

namespace App\Http\Controllers;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\PdfRemark;
use App\Services\GoogleDriveService;
use App\Services\Notifier;
use App\Support\StudentTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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

    /**
     * GET /reviews/{review}/versions/{document}/file - an earlier upload of
     * the same requirement, so the reviewer can see what the team changed.
     */
    public function versionFile(CapstoneDocumentReview $review, CapstoneDocument $document)
    {
        $this->authorizeVersion($review, $document);

        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response($document->file_path, null, ['Content-Type' => 'application/pdf']);
    }

    /** GET /reviews/{review}/versions/{document}/remarks - what this reviewer asked for on that version. */
    public function versionRemarks(CapstoneDocumentReview $review, CapstoneDocument $document)
    {
        $this->authorizeVersion($review, $document);

        $earlier = CapstoneDocumentReview::where('file_id', $document->file_id)->where('expert_id', $review->expert_id)->first();

        return $earlier ? $earlier->pdfRemarks()->where('is_general', false)->get() : collect();
    }

    /**
     * POST /reviews/{review}/versions/{document}/remarks/{remark}/revision -
     * whether a remark left on an earlier version was fixed in this one.
     * Only the reviewer's own remarks on that version, and only from the
     * review of the version that came after it.
     */
    public function updateRevisionStatus(Request $request, CapstoneDocumentReview $review, CapstoneDocument $document, PdfRemark $remark)
    {
        $this->authorizeVersion($review, $document);

        $earlier = CapstoneDocumentReview::where('file_id', $document->file_id)->where('expert_id', $review->expert_id)->first();
        abort_unless($earlier && (int) $remark->review_id === (int) $earlier->review_id && ! $remark->is_general, 404);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(PdfRemark::REVISION_STATUSES)],
        ]);

        $status = $validated['status'] ?? null;

        $remark->update([
            'revision_status' => $status,
            'revision_checked_at' => $status ? now() : null,
        ]);

        return response()->json([
            'id' => $remark->id,
            'revision_status' => $remark->revision_status,
        ]);
    }

    /** GET /reviews/{review}/diagrams - the .drawio files sent with the document under review: [{name, xml}]. */
    public function diagrams(CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        return $review->document?->diagrams() ?? [];
    }

    /** GET /reviews/{review}/versions/{document}/diagrams - the same for an earlier version. */
    public function versionDiagrams(CapstoneDocumentReview $review, CapstoneDocument $document)
    {
        $this->authorizeVersion($review, $document);

        return $document->diagrams();
    }

    /** Only the reviewer of this review, and only the team's earlier uploads of this requirement. */
    private function authorizeVersion(CapstoneDocumentReview $review, CapstoneDocument $document): void
    {
        $this->authorizeReview($review);

        abort_unless($review->document && $review->document->earlierVersions()->contains('file_id', $document->file_id), 404);
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
            'comments.*.type' => ['nullable', Rule::in(PdfRemark::MARK_TYPES)],
            'comments.*.figure_label' => 'nullable|string|max:40',
            'comments.*.diagram_type' => ['nullable', Rule::in(PdfRemark::DIAGRAM_TYPES)],
            'comments.*.shape_ref' => 'nullable|array',
            'comments.*.shape_ref.file' => 'nullable|string|max:255',
            'comments.*.shape_ref.page' => 'nullable|string|max:255',
            'comments.*.shape_ref.cells' => 'required_with:comments.*.shape_ref|array|min:1',
            'comments.*.shape_ref.cells.*.id' => 'required|string|max:255',
            'comments.*.shape_ref.cells.*.kind' => 'required|in:vertex,edge',
            'comments.*.shape_ref.cells.*.text' => 'nullable|string|max:500',
            'comments.*.shape_ref.cells.*.from' => 'nullable|string|max:500',
            'comments.*.shape_ref.cells.*.to' => 'nullable|string|max:500',
            'comments.*.shape_ref.cells.*.role' => 'nullable|string|max:40',
            'comments.*.highlightRects' => 'nullable|array',
            'comments.*.highlightRects.*.top_position' => 'nullable|numeric',
            'comments.*.highlightRects.*.left_position' => 'nullable|numeric',
            'comments.*.highlightRects.*.highlight_width' => 'nullable|numeric',
            'comments.*.highlightRects.*.highlight_height' => 'nullable|numeric',
            'comments.*.y_position' => 'nullable|numeric',
            'comments.*.x_position' => 'nullable|numeric',
            'comments.*.id' => 'nullable',
            'comments.*.carried_from_id' => 'nullable|integer',
        ]);

        // Remarks are updated where they are, not deleted and written again:
        // a remark keeps its id from one save to the next, which is what lets
        // a later round record whether it was fixed. Whatever the screen no
        // longer has is deleted; whatever it has that is new is created, and
        // the screen is told the id each new one got.
        $existing = $review->pdfRemarks()->where('is_general', false)->get()->keyBy('id');
        $kept = [];
        $ids = [];

        foreach ($validated['comments'] as $comment) {
            $rects = $comment['highlightRects'] ?? [];
            $type = $comment['type'] ?? 'text';
            $onFigure = $type !== 'text';

            $remark = [
                'page_number' => $comment['page_number'],
                'color' => $comment['color'] ?? 'yellow',
                'comment' => $comment['text'] ?? $comment['comment'] ?? '',
                'selected_text' => $onFigure ? null : ($comment['selectedText'] ?? null),
                'section' => $comment['section'] ?? null,
                'mark_type' => $type,
                // A figure number only means something on a box or a pin.
                'figure_label' => $onFigure ? ($comment['figure_label'] ?? null) : null,
                'diagram_type' => $onFigure ? ($comment['diagram_type'] ?? null) : null,
                'carried_from_id' => $comment['carried_from_id'] ?? null,
                // Which diagram cells a shape remark is about, so it can find them in the next version.
                'shape_ref' => $type === 'shape' ? ($comment['shape_ref'] ?? null) : null,
            ];

            if ($type !== 'pin' && ! empty($rects)) {
                // The box around all of them, which is what everything that
                // reads a remark's position already uses.
                $minTop = min(array_column($rects, 'top_position'));
                $minLeft = min(array_column($rects, 'left_position'));
                $maxBottom = max(array_map(fn ($r) => $r['top_position'] + ($r['highlight_height'] ?? 0), $rects));
                $maxRight = max(array_map(fn ($r) => $r['left_position'] + ($r['highlight_width'] ?? 0), $rects));

                $remark += [
                    'top_position' => $minTop,
                    'left_position' => $minLeft,
                    'highlight_width' => $maxRight - $minLeft,
                    'highlight_height' => $maxBottom - $minTop,
                    // Each box of a figure remark, so two boxes on one remark
                    // come back as two boxes instead of one around both.
                    'rects' => in_array($type, ['box', 'shape'], true) ? array_values($rects) : null,
                ];
            } else {
                // A pin, or a remark with no highlight: a point on the page.
                $remark += [
                    'top_position' => $comment['y_position'] ?? 0,
                    'left_position' => $comment['x_position'] ?? 0,
                    'highlight_width' => 0,
                    'highlight_height' => 0,
                    'rects' => null,
                ];
            }

            $clientId = $comment['id'] ?? null;
            $saved = is_numeric($clientId) ? $existing->get((int) $clientId) : null;

            // The same remark twice in one request is saved once.
            if ($saved && ! in_array($saved->id, $kept, true)) {
                $saved->update($remark);
            } else {
                $saved = $review->pdfRemarks()->create($remark);
            }

            $kept[] = $saved->id;

            if ($clientId !== null && (string) $clientId !== (string) $saved->id) {
                $ids[(string) $clientId] = $saved->id;
            }
        }

        $review->pdfRemarks()->where('is_general', false)->whereNotIn('id', $kept)->delete();

        return response()->json([
            'success' => true,
            'counts' => ['remarks' => count($validated['comments'])],
            'ids' => (object) $ids,
        ]);
    }

    /**
     * POST /reviews/{review}/figures-checked - the figures the reviewer looked
     * at and had nothing to say about, so the Figures list counts them as
     * reviewed. A figure with a remark on it counts already.
     */
    public function updateFiguresChecked(Request $request, CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        $validated = $request->validate([
            'figures' => 'present|array|max:300',
            'figures.*' => 'string|max:40',
        ]);

        $review->update(['figures_checked' => array_values(array_unique($validated['figures']))]);

        return response()->json(['figures_checked' => $review->figures_checked]);
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

        return response()->json([
            'review_status' => $review->review_status,
            'verdict' => $review->verdict,
            'figures_checked' => $review->figures_checked ?? [],
        ]);
    }

    public function updateStatus(Request $request, CapstoneDocumentReview $review)
    {
        $this->authorizeReview($review);

        $status = $request->input('review_status');
        $verdict = $request->input('verdict');

        if (! in_array($status, CapstoneDocumentReview::STATUSES, true)) {
            return response()->json([
                'message' => 'The selected review status is invalid.',
            ], 422);
        }

        $finishing = $status === CapstoneDocumentReview::REVIEWED;

        // Turning a review in is the moment the reviewer decides, so a
        // verdict is required exactly then - that is the whole point of
        // recording one. Before this, a review could be finished with the
        // decision left in a typed remark that nothing could query.
        if ($finishing && ! in_array($verdict, CapstoneDocumentReview::VERDICTS, true)) {
            return response()->json([
                'message' => 'Say whether the document is approved or needs revision.',
            ], 422);
        }

        // reviewed_at was only ever read, never written, so every row held
        // null - including the ones marked Reviewed. Reopening a review
        // clears both stamps and the verdict, so neither ever claims a
        // decision that was withdrawn.
        $review->update([
            'review_status' => $status,
            'verdict' => $finishing ? $verdict : null,
            'reviewed_at' => $finishing ? now() : null,
            'verdict_at' => $finishing ? now() : null,
        ]);

        // The team had no way of knowing a review had come back other than
        // opening Submission and checking. Only on finishing - reopening a
        // review is the reviewer's own housekeeping. It opens Documents on
        // that file with this reviewer's remarks.
        if ($finishing) {
            $document = $review->document;
            $team = StudentTeam::for($document?->student_id);

            Notifier::toStudents(
                $team?->members ?? [],
                'review_finished',
                'Review finished',
                'Your '.e(str_replace('_', ' ', $review->role)).' finished reviewing <strong>'
                    .e($document?->file_name ?? 'your document').'</strong> - '.e($review->verdict).'.',
                $document ? route('student.documents', ['file' => $document->file_id, 'review' => $review->review_id]) : route('student.documents'),
                ['review_id' => $review->review_id],
            );
        }

        return response()->json([
            'success' => true,
            'review_status' => $review->review_status,
            'verdict' => $review->verdict,
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

        // The Drive copy of a diagram is the team's own draw.io or Visio file; a PDF written over it would break it.
        if ($review->document?->isDiagram()) {
            return response()->json(['message' => 'A diagram\'s Google Drive copy is the team\'s own file and is left as it is.'], 422);
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
