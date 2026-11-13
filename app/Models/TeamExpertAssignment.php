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
        'pre_proposal_id',
        'status',
        'rejection_reason',
        'proposal_file',
        'message',
        'meet_note',
        'meet_asked_at',
    ];

    protected $casts = ['meet_asked_at' => 'datetime'];

    /**
     * An engaged expert who leaves is remembered, so the leader still
     * evaluates them for a defense they were there for
     * (ExpertEvaluations::engagedExperts).
     */
    protected static function booted(): void
    {
        static::deleting(function (self $assignment) {
            if ($assignment->status === 'approved' && ($role = $assignment->engaged_role_name)) {
                \Illuminate\Support\Facades\DB::table('team_expert_departures')->insert([
                    'team_id' => $assignment->team_id,
                    'expert_id' => $assignment->expert_id,
                    'role' => strtolower(trim($role)),
                    'left_at' => now(),
                ]);
            }
        });
    }

    /**
     * The consultation the team has with this expert after they asked to
     * meet first: one still coming up (even if booked before the ask - the
     * team can hold only one per expert), or one held since. A cancelled one
     * puts the request back to waiting for the team to book.
     */
    public function meeting(): ?ConsultationBooking
    {
        if (! $this->meet_asked_at) {
            return null;
        }

        return ConsultationBooking::where('team_id', $this->team_id)
            ->where(fn ($q) => $q->where('booking_status', 'scheduled')
                ->orWhere(fn ($q) => $q->where('booking_status', 'done')->where('created_at', '>=', $this->meet_asked_at)))
            ->whereHas('schedule', fn ($q) => $q->where('expert_id', $this->expert_id))
            ->with('schedule')
            ->latest('booking_id')
            ->first();
    }

    /** "Mon Oct 19 · 1:00 PM" - one booked or open time, as both sides read it. */
    public static function timeLabel(string $date, ?string $start): string
    {
        return \Illuminate\Support\Carbon::parse($date.' '.($start ?: '00:00'))->format('D M j · g:i A');
    }

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
    /**
     * The approved pre-proposal this application was made with, when the team
     * had one - so an expert can see the CaPSA/TSA already passed it rather than
     * take a re-uploaded PDF on trust.
     */
    public function preProposal()
    {
        return $this->belongsTo(CapstonePreProposal::class, 'pre_proposal_id');
    }

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
        // Loaded once and kept on the model: pages read this several times
        // for the same assignment, and each read used to query again.
        if ($this->expert_role_id) {
            if (! $this->relationLoaded('expertRole') || (int) $this->expertRole?->expert_role_id !== (int) $this->expert_role_id) {
                $this->load('expertRole.role');
            }

            return $this->expertRole?->role?->role_name;
        }

        $this->loadMissing('role');

        return $this->role->count() === 1 ? $this->role->first()->role_name : null;
    }
}
