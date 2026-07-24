<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'admin_id',
        'title',
        'body',
        'priority',
        'target_audience',
        'team_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'target_audience' => 'array',
        'created_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(CapstoneExpert::class, 'admin_id', 'expert_id');
    }

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }
}
