<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One role an expert is qualified for.
 *
 * The expert_roles pivot is usually reached through
 * CapstoneExpert::roles(), which is the right tool when the question is
 * "what can this expert do?". This model exists for the other question:
 * capstone_expert_application points at a single expert_role_id to record
 * what a team engaged the expert AS, and that reference needs a model of
 * its own to be loaded and read.
 */
class ExpertRole extends Model
{
    protected $table = 'expert_roles';

    protected $primaryKey = 'expert_role_id';

    public $timestamps = false;

    protected $fillable = ['expert_id', 'role_id'];

    public function expert()
    {
        return $this->belongsTo(CapstoneExpert::class, 'expert_id', 'expert_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }
}
