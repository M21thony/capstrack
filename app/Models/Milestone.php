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
}
