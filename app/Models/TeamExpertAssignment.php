<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
