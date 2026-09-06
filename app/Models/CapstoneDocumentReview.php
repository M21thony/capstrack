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
     * the document. REVIEWED means they finished looking at it. What they
     * decided lives in the verdict below.
     */
    public const AWAITING = 'To Review';

    public const IN_PROGRESS = 'Reviewing';

    public const REVIEWED = 'Reviewed';

    public const STATUSES = [self::AWAITING, self::IN_PROGRESS, self::REVIEWED];

    /**
     * What the reviewer decided, which is a different question from how far
     * along they are.
     *
     * Null means undecided, and that is every row written before this
     * existed - no attempt is made to read approval out of the old remarks.
     * A verdict only ever accompanies REVIEWED: it is recorded when the
     * review is turned in, and cleared if it is reopened.
     */
    public const APPROVED = 'approved';

    public const NEEDS_REVISION = 'needs_revision';

    public const VERDICTS = [self::APPROVED, self::NEEDS_REVISION];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'verdict_at' => 'datetime',
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

    /** The reviewer accepted the document. */
    public function scopeApproved($query)
    {
        return $query->where('verdict', self::APPROVED);
    }

    /** The reviewer wants it changed. */
    public function scopeNeedsRevision($query)
    {
        return $query->where('verdict', self::NEEDS_REVISION);
    }

    /**
     * Approved by the expert engaged in this role - 'adviser' or
     * 'consultant'. The Capstone 1 checklist asks for both separately, and
     * the role is already on the row, so no join is needed to tell them
     * apart.
     */
    public function scopeApprovedAs($query, string $role)
    {
        return $query->approved()->where('role', $role);
    }

    protected $fillable = [
        'file_id',
        'expert_id',
        'role',
        'review_status',
        'verdict',
        'reviewed_at',
        'verdict_at',
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
