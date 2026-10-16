<?php

namespace App\Support;

use App\Models\CapstoneDocument;
use App\Models\CapstoneDocumentReview;
use App\Models\PdfRemark;
use Illuminate\Support\Str;

/**
 * What a reviewer asked for when they sent a version back: who, when, how
 * many remarks, and the first of them. Shown on the Documents card that
 * needs the revision, and at the top of the pop-up that sends it, so the team
 * reads what to fix where they act on it.
 */
class RevisionRemark
{
    /**
     * @return array{who: string, role: string, reviewer: ?string, date: ?string, count: int, first: ?string, url: string}|null
     */
    public static function on(CapstoneDocument $document, int $length = 90): ?array
    {
        $review = CapstoneDocumentReview::where('file_id', $document->file_id)
            ->where('verdict', CapstoneDocumentReview::NEEDS_REVISION)
            ->with('expert')
            ->orderByDesc('verdict_at')
            ->first();

        if (! $review) {
            return null;
        }

        // The general remarks say what the reviewer wants overall, so one of
        // them is the one quoted; the pinned ones follow in the order made.
        $remarks = PdfRemark::where('review_id', $review->review_id)
            ->whereNotNull('comment')
            ->orderByDesc('is_general')
            ->orderBy('created_at')
            ->pluck('comment')
            ->filter(fn ($comment) => trim($comment) !== '');

        $role = SubmissionRecipients::LABELS[$review->role] ?? 'reviewer';

        return [
            'who' => 'Your '.$role,
            'role' => $role,
            'reviewer' => $review->expert?->name,
            'date' => ($review->verdict_at ?? $review->reviewed_at)?->format('M j'),
            'count' => $remarks->count(),
            'first' => $remarks->first() ? Str::limit(trim($remarks->first()), $length) : null,
            'url' => route('student.documents', ['file' => $review->file_id, 'review' => $review->review_id]),
        ];
    }
}
