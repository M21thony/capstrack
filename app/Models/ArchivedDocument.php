<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivedDocument extends Model
{
    public const PENDING = 'pending';

    public const PUBLISHED = 'published';

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
        'submitted_by',
        'submitted_at',
        'submission_status',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    /** The team member who handed the manuscript in, if a team submitted it. */
    public function submitter()
    {
        return $this->belongsTo(Student::class, 'submitted_by', 'student_id');
    }

    /** The team whose capstone this is. */
    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    /** Submissions a team made that the admin has not decided on yet. */
    public function scopePending($query)
    {
        return $query->where('submission_status', self::PENDING);
    }

    /** What the archive shows the public: staff uploads and published submissions. */
    public function scopePublished($query)
    {
        return $query->where(function ($query) {
            $query->where('submission_status', self::PUBLISHED)
                ->orWhereNull('submission_status');
        });
    }

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
