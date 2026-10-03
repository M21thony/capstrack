<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A defense date called off because the team's CaPA said they were not
 * ready. The team waits for its CaPSA to give it a new date.
 */
class DefenseCancellation extends Model
{
    protected $fillable = [
        'defense_id',
        'team_id',
        'defense_type',
        'was_scheduled_at',
        'venue',
        'note',
        'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'was_scheduled_at' => 'datetime',
        ];
    }
}
