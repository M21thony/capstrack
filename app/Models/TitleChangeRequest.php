<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A team leader asking to change part of a title that has already passed.
 * The team's adviser decides; only an approved change replaces the title.
 */
class TitleChangeRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    protected $fillable = [
        'team_id',
        'requested_by',
        'current_title',
        'new_title',
        'reason',
        'status',
        'adviser_id',
        'adviser_note',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'requested_by', 'student_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
