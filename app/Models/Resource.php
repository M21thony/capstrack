<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    protected $table = 'resources';

    protected $primaryKey = 'resource_id';

    // Table only has `uploaded_at`, not Laravel's default created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'resource_type',
        'resource_for',
        'category',
        'title',
        'description',
        'file_name',
        'file_path',
        'file_size',
        'file_type',
        'content_type',
        'external_url',
        'is_downloadable',
        'drive_file_id',
        'drive_folder_id',
        'drive_web_view_link',
        'downloads',
        'uploaded_at',
        'uploaded_by',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'is_downloadable' => 'boolean',
    ];

    // Maps the page's granular category tabs down to the resource_type enum
    // (template | form | lesson) required by the DB constraint.
    public static function typeForCategory(string $category): string
    {
        return match ($category) {
            'lesson' => 'lesson',
            'forms' => 'form',
            default => 'template', // pre-proposal, ch1-3, ch4-5, final, presentation, other
        };
    }
}
