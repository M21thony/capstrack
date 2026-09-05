<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One section a resource is aimed at, as a label - "BSIT-4A".
 *
 * The label is whole rather than split into program and section, because
 * these rows are the only place the targeting is written down. A resource
 * with rows here leaves resources.resource_for null.
 */
class ResourceSection extends Model
{
    protected $table = 'resource_sections';

    public $timestamps = false;

    protected $fillable = ['resource_id', 'section'];

    /** @return BelongsTo<resource, ResourceSection> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id', 'resource_id');
    }
}
