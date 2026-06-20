<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedDocTag extends Model
{
    protected $primaryKey = 'tag_id';
    public $timestamps    = false;

    protected $fillable = [
        'tag_name',
        'tag_color',
    ];
}