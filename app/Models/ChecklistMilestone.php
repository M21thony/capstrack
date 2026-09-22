<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A numbered group inside a checklist - Getting started, Chapter 1, Title Defense. */
class ChecklistMilestone extends Model
{
    use SoftDeletes;

    protected $fillable = ['checklist_id', 'name', 'position', 'requires_previous', 'archived_at'];

    protected $casts = [
        'position' => 'integer',
        'requires_previous' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function checklist()
    {
        return $this->belongsTo(Checklist::class);
    }

    public function deliverables()
    {
        return $this->hasMany(Deliverable::class)->orderBy('position')->orderBy('id');
    }

    /** What teams see: not archived. Removed rows are already left out by SoftDeletes. */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }
}
