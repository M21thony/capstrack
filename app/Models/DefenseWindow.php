<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefenseWindow extends Model
{
    protected $table = 'defense_windows';

    protected $primaryKey = 'defense_window_id';

    public $timestamps = false;

    protected $fillable = [
        'defense_type',
        'start_date',
        'end_date',
        'duration_minutes',
    ];

    protected $attributes = ['duration_minutes' => 120];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_minutes' => 'integer',
    ];
}
