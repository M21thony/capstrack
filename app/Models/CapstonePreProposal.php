<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapstonePreProposal extends Model
{
    protected $fillable = [
        'retired_at',
        'adviser_status',
        'adviser_note',
        'adviser_decided_at',
        'team_id',
        'duplication_check_id',
        'slot',
        'title',
        'sdg_goals',
        'research_agenda',
        'problem_statement',
        'proposed_solution',
        'scope',
        'limitations',
        'sdm',
        'clients',
        'client_address',
        'client_representative',
        'client_position',
        'tags',
        'duplication_status',
        'approved_status',
        'rejection_reason',
        'capsa_note',
        'submitted_at',
    ];

    /** A few thousand numbers no page needs. */
    protected $hidden = ['embedding'];

    protected $casts = [
        'research_agenda' => 'array',
        'tags' => 'array',
        'approved_status' => 'boolean',
        'submitted_at' => 'datetime',
        'retired_at' => 'datetime',
        'adviser_decided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The duplication check's stored embedding is of these three; a
        // revised title must be read again, not compared as it was.
        static::saving(function (self $proposal) {
            if ($proposal->isDirty(['title', 'problem_statement', 'proposed_solution'])) {
                $proposal->embedding = null;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function duplicationCheck(): BelongsTo
    {
        return $this->belongsTo(DuplicationCheck::class);
    }

    // Same derived-status convention as CapstoneExpert in AdminApprovalController:
    // two columns collapsed into one of three states at read time.
    public function getStatusAttribute(): string
    {
        if ($this->approved_status) {
            return 'approved';
        }

        return $this->rejection_reason ? 'rejected' : 'pending';
    }
}
