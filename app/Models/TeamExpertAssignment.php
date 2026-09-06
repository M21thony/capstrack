<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Do NOT add a role_id column/fillable entry back to this table or model.
// It was intentionally dropped (see the
// 2026_07_08_113731_remove_role_id_column migration) - an expert's role is
// derived by joining through the expert_roles pivot table instead of being
// duplicated per-assignment. Re-adding it breaks StudentExpertController's
// apply flow (the frontend never collects a role_id, so a required
// validation rule on it fails immediately).
class TeamExpertAssignment extends Model
{
    protected $table = 'capstone_expert_application';

    protected $primaryKey = 'assignment_id';

    protected $fillable = [
        'team_id',
        'expert_id',
        'status',
        'rejection_reason',
        'proposal_file',
        'message',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class, 'team_id', 'team_id');
    }

    public function expert()
    {
        return $this->belongsTo(CapstoneExpert::class, 'expert_id', 'expert_id');
    }

    public function role()
    {
        return $this->belongsToMany(Role::class, 'expert_roles', 'expert_id', 'role_id');
    }
}
