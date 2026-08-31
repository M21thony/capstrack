<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Milestone extends Model
{
    protected $fillable = [
        'team_id',
        'title',
        'description',
        'due_date',
        'status',
        'order',
    ];

    protected $casts = [
        'due_date' => 'date',
        'order' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(MilestoneItem::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    /**
     * How far along this team is, worked out from the items.
     *
     * Deliberately not read from the status column: that was written once as
     * 'todo' when the milestone was created and never moved again, so
     * 'completed' and 'overdue' were unreachable and every count of them came
     * back zero.
     */
    public function progress(): object
    {
        return \App\Support\MilestoneProgress::for($this);
    }
}
