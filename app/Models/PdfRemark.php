<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PdfRemark extends Model
{
    protected $fillable = [
        'review_id', 'page_number', 'top_position', 'left_position',
        'highlight_width', 'highlight_height', 'color', 'comment',
    ];
}
