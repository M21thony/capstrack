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
        'milestone_item_id',
    ];

    public function reviews()
    {
        return $this->hasMany(CapstoneDocumentReview::class, 'file_id', 'file_id');
    }
}
