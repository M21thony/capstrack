<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfRemark extends Model
{
    /** Selected words, a box (or several) drawn over a figure, or a pin on one spot. */
    public const MARK_TYPES = ['text', 'box', 'pin'];

    /** The kinds of diagram a figure remark can be about; the quick remarks follow them. */
    public const DIAGRAM_TYPES = ['ERD', 'DFD', 'UseCase', 'Flowchart', 'Architecture', 'UI'];

    /** What the reviewer found when the team sent the next version. */
    public const FIXED = 'fixed';

    public const NOT_FIXED = 'not_fixed';

    public const REVISION_STATUSES = [self::FIXED, self::NOT_FIXED];

    protected $fillable = [
        'review_id', 'page_number', 'top_position', 'left_position',
        'highlight_width', 'highlight_height', 'color', 'comment',
        'selected_text', 'is_general', 'section',
        'mark_type', 'rects', 'figure_label', 'diagram_type',
        'revision_status', 'revision_checked_at', 'carried_from_id',
    ];

    protected $casts = [
        'is_general' => 'boolean',
        'rects' => 'array',
        'revision_checked_at' => 'datetime',
    ];

    /**
     * The words the CLF and the review page print for this remark: a remark
     * on a figure starts with the figure's number, since on paper there is
     * nothing else to say which figure it means.
     */
    public function printedComment(): string
    {
        return filled($this->figure_label) ? "{$this->figure_label}: {$this->comment}" : (string) $this->comment;
    }

    /**
     * Every comment left on a review, grouped the same way everywhere it's
     * displayed (the CLF PDF, the student review page): comments tagged with
     * a section — general or page-anchored — are pooled under that section
     * heading; untagged general comments stand alone under "General
     * Comment:"; untagged page remarks fall back to a plain bulleted list.
     */
    public static function groupedForReview(int $reviewId): array
    {
        $all = static::where('review_id', $reviewId)
            ->orderBy('created_at')
            ->get(['id', 'page_number', 'comment', 'section', 'is_general', 'figure_label'])
            ->filter(fn ($r) => filled($r->comment));

        return [
            'bySection' => $all->filter(fn ($r) => filled($r->section))->groupBy('section'),
            'remarks' => $all->filter(fn ($r) => ! $r->is_general && blank($r->section))->values(),
            'general' => $all->filter(fn ($r) => $r->is_general && blank($r->section))->pluck('comment')->values(),
        ];
    }
}
