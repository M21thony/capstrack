<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfRemark extends Model
{
    protected $fillable = [
        'review_id', 'page_number', 'top_position', 'left_position',
        'highlight_width', 'highlight_height', 'color', 'comment',
        'selected_text', 'is_general', 'section',
    ];

    protected $casts = [
        'is_general' => 'boolean',
    ];

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
            ->get(['id', 'page_number', 'comment', 'section', 'is_general'])
            ->filter(fn ($r) => filled($r->comment));

        return [
            'bySection' => $all->filter(fn ($r) => filled($r->section))->groupBy('section'),
            'remarks' => $all->filter(fn ($r) => ! $r->is_general && blank($r->section))->values(),
            'general' => $all->filter(fn ($r) => $r->is_general && blank($r->section))->pluck('comment')->values(),
        ];
    }
}
