<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// The engaged role lives on the assignment, in expert_role_id.
//
// This reverses the earlier removal of role_id (see the
// 2026_07_08_113731_remove_role_id_column migration), which assumed the role
// could be derived by joining expert_roles on expert_id. It can't:
// expert_roles lists what an expert is QUALIFIED for, so an expert who is
// both an adviser and a consultant gives the same answer for every team,
// and code that reached for it fell back to roles->first() - a guess.
// Whether a team engaged them as adviser or as consultant is a fact about
// the assignment, and nothing but the assignment can hold it.
//
// The old comment warned that a required role_id would break the apply flow
// because the frontend never collects one. The apply modal now does: the
// leader picks which of the expert's roles they are applying for.
class TeamExpertAssignment extends Model
{
    protected $table = 'capstone_expert_application';

    protected $primaryKey = 'assignment_id';

    protected $fillable = [
        'team_id',
        'expert_id',
        'expert_role_id',
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

    /**
     * Every role the expert is qualified for - NOT the one this team engaged
     * them as. Reading ->role->first() to label an assignment is the guess
     * this class's opening comment describes; use $assignment->engaged_role_name
     * instead. Kept because "what else does this expert do?" is still a fair
     * question to ask of a loaded assignment.
     */
    public function role()
    {
        return $this->belongsToMany(Role::class, 'expert_roles', 'expert_id', 'role_id');
    }

    /**
     * The single role this team engaged the expert as.
     */
    public function expertRole()
    {
        return $this->belongsTo(ExpertRole::class, 'expert_role_id', 'expert_role_id');
    }

    /**
     * The engaged role's name, or null when it isn't known.
     *
     * Assignments made before expert_role_id existed have nothing recorded,
     * so they fall back to the old single-role lookup - accurate for the
     * experts who only ever offered one role, which is most of them, and no
     * worse than what was displayed before. Null means genuinely unknown
     * (an expert with several roles and a pre-existing assignment); callers
     * should show nothing rather than invent a label.
     */
    public function getEngagedRoleNameAttribute(): ?string
    {
        if ($this->expert_role_id) {
            return $this->relationLoaded('expertRole')
                ? $this->expertRole?->role?->role_name
                : ExpertRole::with('role')->find($this->expert_role_id)?->role?->role_name;
        }

        $qualified = $this->relationLoaded('role') ? $this->role : $this->role()->get();

        return $qualified->count() === 1 ? $qualified->first()->role_name : null;
    }
}
