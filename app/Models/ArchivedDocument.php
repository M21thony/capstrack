<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedDocument extends Model
{
    protected $primaryKey = 'archived_id';

    public $timestamps = false;

    protected $fillable = [
        'team_id',
        'title',
        'abstract',
        'authors',
        'academic_year',
        'program',
        'file_name',
        'file_path',
        'file_size',
    ];

    public function tags()
    {
        return $this->belongsToMany(
            ArchivedDocTag::class,
            'archived_tag_relation',
            'archive_id',
            'tag_id'
        );
    }
}
