<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapstoneDocument extends Model
{
    protected $table = 'capstone_documents';

    protected $primaryKey = 'file_id';

    protected $fillable = [
        'student_id',
        'file_name',
        'file_path',
        'file_version',
        'document_status',
        'chapter',
        'deliverable_id',
    ];

    public function reviews()
    {
        return $this->hasMany(CapstoneDocumentReview::class, 'file_id', 'file_id');
    }

    /** The checklist deliverable this file was sent for, if any. */
    public function deliverable()
    {
        return $this->belongsTo(Deliverable::class);
    }

    /**
     * Which round this upload is: the first submission, then the 1st, 2nd...
     * revision. Read from the version, which counts uploads of the same
     * requirement (or the same chapter, for files that name none).
     */
    public function roundLabel(): string
    {
        return self::round(max(1, (int) $this->file_version) - 1);
    }

    /** What the team is asked for if this upload is sent back. */
    public function nextRoundLabel(): string
    {
        return self::round(max(1, (int) $this->file_version));
    }

    private static function round(int $revision): string
    {
        if ($revision <= 0) {
            return 'First submission';
        }

        $suffix = in_array($revision % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$revision % 10] ?? 'th');

        return $revision.$suffix.' revision';
    }
}
