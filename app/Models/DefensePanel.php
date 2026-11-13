<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefensePanel extends Model
{
    protected $table = 'defense_panels';

    protected $primaryKey = 'defense_panel_id';

    public $timestamps = false;

    protected $fillable = [
        'defense_id',
        'expert_id',
        'panel_role',
        'google_event_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /** A seat on a defense that no longer counts is not a seat. */
    protected static function booted(): void
    {
        static::addGlobalScope('live', fn ($query) => $query->whereHas('schedule'));
    }

    public function schedule()
    {
        return $this->belongsTo(DefenseSchedule::class, 'defense_id', 'defense_id');
    }

    public function expert()
    {
        return $this->belongsTo(CapstoneExpert::class, 'expert_id', 'expert_id');
    }
}
