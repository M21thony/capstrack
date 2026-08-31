<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapstoneDocumentReview extends Model
{
    protected $table = 'capstone_document_review';

    protected $primaryKey = 'review_id';

    /**
     * The review states, named once.
     *
     * These three are the only values the review screen can set
     * (PdfAnnotationController::updateStatus) and the only three in the
     * table. Two other vocabularies were being queried for - 'pending' and
     * 'approved' - which nothing has ever written, so those queries could
     * never match a row. Anything filtering on review_status should go
     * through the scopes below rather than spell a string again.
     *
     * Note what these do NOT say: none of them means the reviewer approved
     * the document. REVIEWED means they finished looking at it. Recording an
     * actual verdict is separate work.
     */
    public const AWAITING = 'To Review';

    public const IN_PROGRESS = 'Reviewing';

    public const REVIEWED = 'Reviewed';

    public const STATUSES = [self::AWAITING, self::IN_PROGRESS, self::REVIEWED];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /** Still on someone's plate - not yet finished with. */
    public function scopeAwaitingReview($query)
    {
        return $query->whereIn('review_status', [self::AWAITING, self::IN_PROGRESS]);
    }

    /** Finished with. Says nothing about whether the reviewer was happy. */
    public function scopeReviewed($query)
    {
        return $query->where('review_status', self::REVIEWED);
    }

    protected $fillable = [
        'file_id',
        'expert_id',
        'role',
        'review_status',
        'reviewed_at',
        'version',
        'drive_file_id',
        'drive_web_view_link',
        'drive_status',
        'drive_error',
    ];

    public function document()
    {
        return $this->belongsTo(CapstoneDocument::class, 'file_id', 'file_id');
    }

    public function expert()
    {
        return $this->belongsTo(CapstoneExpert::class, 'expert_id', 'expert_id');
    }

    public function pdfRemarks()
    {
        return $this->hasMany(PdfRemark::class, 'review_id', 'review_id');
    }
}
