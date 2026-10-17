<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One student's scores for one teammate on one activity: seven rubric
 * scores and their total out of 70. A draft until submitted_at is set.
 */
class TeamRating extends Model
{
    protected $fillable = ['form_id', 'item_id', 'team_id', 'rater_id', 'ratee_id', 'scores', 'total', 'submitted_at'];

    protected $casts = [
        'scores' => 'array',
        'total' => 'integer',
        'submitted_at' => 'datetime',
    ];
}
