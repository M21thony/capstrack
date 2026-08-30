<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapstonePreProposal extends Model
{
    protected $fillable = [
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
        'duplication_status',
        'approved_status',
        'rejection_reason',
        'submitted_at',
    ];

    protected $casts = [
        'research_agenda' => 'array',
        'approved_status' => 'boolean',
        'submitted_at' => 'datetime',
    ];

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
