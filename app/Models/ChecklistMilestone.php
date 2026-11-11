<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A numbered group inside a checklist - Getting started, Chapter 1, Title Defense. */
class ChecklistMilestone extends Model
{
    use SoftDeletes;

    protected $fillable = ['checklist_id', 'name', 'description', 'due_on', 'position', 'requires_previous', 'opened_at', 'closed_at', 'archived_at'];

    protected $casts = [
        'position' => 'integer',
        'due_on' => 'date',
        'requires_previous' => 'boolean',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
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

    /** Not closed by the CaPSA/TSA: still on the open list and still sending reminders. */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    /** Opened by the CaPSA/TSA for the whole section. A finished one was open. */
    public function isOpened(): bool
    {
        return $this->opened_at !== null || $this->closed_at !== null;
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }
}
