<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'updated_by',
        'updated_at',
    ];

    protected $attributes = ['duration_minutes' => 120];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'duration_minutes' => 'integer',
        'updated_at' => 'datetime',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(CapstoneExpert::class, 'updated_by', 'expert_id');
    }
}
