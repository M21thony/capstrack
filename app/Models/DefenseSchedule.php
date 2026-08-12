<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefenseSchedule extends Model
{
    protected $table = 'defense_schedule';

    protected $primaryKey = 'defense_id';

    public $timestamps = false;

    protected $fillable = [
        'team_id',
        'defense_type',
        'schedule_date',
        'venue',
    ];

    protected $casts = [
        'schedule_date' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function panels()
    {
        return $this->hasMany(DefensePanel::class, 'defense_id', 'defense_id');
    }
}
