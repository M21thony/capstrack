<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapstoneDocumentReview extends Model
{
    protected $table = 'capstone_document_review';

    protected $primaryKey = 'review_id';

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
}
