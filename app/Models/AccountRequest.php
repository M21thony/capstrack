<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountRequest extends Model
{
    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'middle_name',
        'credentials',
        'department',
        'status',
        'rejection_reason',
        'reject_attempts',
        'reject_cooldown_until',
        'max_teams',
        'specialization',
        'expertise',
    ];

    protected function casts(): array
    {
        return [
            'reject_cooldown_until' => 'datetime',
            'reject_attempts' => 'integer',
            'max_teams' => 'integer',
            'specialization' => 'array',
            'expertise' => 'array',
        ];
    }
}
