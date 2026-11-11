<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A CaPSA/TSA's checklist for one program. Every team in the sections of that
 * program they handle follows it - there is no per-team copy.
 */
class Checklist extends Model
{
    use SoftDeletes;

    protected $fillable = ['capsa_id', 'program', 'title'];

    public function milestones()
    {
        return $this->hasMany(ChecklistMilestone::class)->orderBy('position')->orderBy('id');
    }

    public function deliverables()
    {
        return $this->hasManyThrough(Deliverable::class, ChecklistMilestone::class);
    }

    public function capsa()
    {
        return $this->belongsTo(CapstoneExpert::class, 'capsa_id', 'expert_id');
    }
}
