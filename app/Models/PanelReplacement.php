<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One panelist on a final panel replaced by another, and why. Defenses held
 * before it keep the panelist who sat them.
 */
class PanelReplacement extends Model
{
    protected $fillable = [
        'team_id',
        'panel_role',
        'old_expert_id',
        'new_expert_id',
        'reason',
        'replaced_by',
    ];

    public function oldExpert(): BelongsTo
    {
        return $this->belongsTo(CapstoneExpert::class, 'old_expert_id', 'expert_id');
    }

    public function newExpert(): BelongsTo
    {
        return $this->belongsTo(CapstoneExpert::class, 'new_expert_id', 'expert_id');
    }
}
