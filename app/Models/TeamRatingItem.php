<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/** One activity a team rating covers: a checklist milestone, one deliverable, or the CaPSA/TSA's own. */
class TeamRatingItem extends Model
{
    protected $fillable = ['form_id', 'label', 'checklist_milestone_id', 'deliverable_id'];

    public function form()
    {
        return $this->belongsTo(TeamRatingForm::class, 'form_id');
    }

    public function milestone()
    {
        return $this->belongsTo(ChecklistMilestone::class, 'checklist_milestone_id');
    }

    public function deliverable()
    {
        return $this->belongsTo(Deliverable::class);
    }

    /** Its checklist date - a milestone without one of its own takes its latest deliverable's - or null. */
    public function dueOn(): ?Carbon
    {
        if ($this->deliverable_id) {
            return $this->deliverable?->due_date;
        }

        if ($this->checklist_milestone_id && $this->milestone) {
            return $this->milestone->due_on ?? $this->milestone->deliverables->pluck('due_date')->filter()->max();
        }

        return null;
    }
}
