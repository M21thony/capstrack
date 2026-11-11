<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentRole extends Model
{
    protected $table = 'student_roles';

    protected $primaryKey = 's_role_id';

    // The table is a fixed list of roles with no created_at/updated_at, so
    // any write through this model asked for columns that are not there.
    public $timestamps = false;

    // s_role_description was missing here, so anything writing a role's
    // duties had them dropped without a word. It only appeared to work when
    // seeded through `artisan db:seed`, which unguards every model first.
    protected $fillable = [
        's_role_name',
        's_role_description',
    ];

    // There is no separate "Leader" role in student_roles. The Project
    // Head is always the team leader, so this is the role_id assigned
    // wherever the code needs "the leader's role". The name has to match
    // StudentRoleSeeder exactly or leaderRoleId() answers null, which is
    // silent - every leader created then gets no role at all.
    public const LEADER_ROLE_NAME = 'Project Head/Database Designer';

    public static function leaderRoleId(): ?int
    {
        return static::where('s_role_name', self::LEADER_ROLE_NAME)->value('s_role_id');
    }

    /**
     * The role every member already holds.
     *
     * The course says writing and testing the system "must be played partly
     * by each team member" - so it is not a designation anybody is given, it
     * is what everybody on the team is doing, the leader included. It is
     * never offered as a choice and no s_role_id ever points at it.
     *
     * The seats prove it: with only the other two to choose between, a team
     * divides exactly. Two members into two seats, four into four. Putting
     * this one on the list would have left those seats unfilled.
     */
    public const SHARED_ROLE_NAME = 'Software Engineer/Programmer';

    /**
     * The number of people who may hold a role, or null when there is no
     * limit.
     *
     * Project Head seats one, and that one is the leader's - it is never
     * offered to anybody else. Programmer has no limit, because the course
     * says every member plays it partly.
     *
     * The other two are the whole of the choice, and they scale with the
     * size of the team, which the CaPSA/TSA sets between three and five. A team
     * of N is the leader plus N-1 members split across exactly these two, so
     * each holds ceil((N-1)/2):
     *
     *   3 members  ->  2 members, 1 each
     *   4 members  ->  3 members, 2 each
     *   5 members  ->  4 members, 2 each
     *
     * Rounded up rather than down so the seats always cover the team; a
     * member who finds both full is not stuck, because Programmer is open.
     */
    public static function seatsFor(string $roleName, ?int $teamSize = null): ?int
    {
        if ($roleName === self::LEADER_ROLE_NAME) {
            return 1;
        }

        // Held by everybody, so counting it makes no sense either way.
        if ($roleName === self::SHARED_ROLE_NAME) {
            return null;
        }

        $size = max(Team::MIN_MEMBERS, min(Team::MAX_MEMBERS, $teamSize ?: self::teamSize()));

        return (int) ceil(($size - 1) / 2);
    }

    /**
     * The team size the CaPSA/TSA set.
     *
     * This used to read the team's own member_count column, which is a
     * stale copy - a CaPSA/TSA raising the limit from three to five left every
     * existing team still seating one designer and one tester. There is one
     * limit and the CaPSA/TSA sets it, so it is asked of the same place the
     * invite and join checks ask.
     */
    public static function teamSize(?int $teamId = null): int
    {
        $team = $teamId ? Team::find($teamId) : null;

        return $team ? $team->memberLimit() : Team::maxMembers();
    }

    /**
     * The roles a member chooses between: Network Designer/UI Designer and
     * QA Tester/Documentation Writer.
     *
     * Project Head is the leader's, and Programmer is everybody's, so
     * neither is on the list.
     */
    public static function memberRoles()
    {
        return static::whereNotIn('s_role_name', [self::LEADER_ROLE_NAME, self::SHARED_ROLE_NAME])
            ->orderBy('s_role_id')
            ->get();
    }

    /** Can one more person take this role on this team? */
    public static function hasSeat(int $teamId, int $roleId): bool
    {
        $role = static::find($roleId);

        // Neither the leader's seat nor the one everybody already holds is
        // something a member can be given.
        if (! $role || in_array($role->s_role_name, [self::LEADER_ROLE_NAME, self::SHARED_ROLE_NAME], true)) {
            return false;
        }

        return StudentSchoolYear::where('team_id', $teamId)
            ->where('s_role_id', $roleId)
            ->count() < self::seatsFor($role->s_role_name, self::teamSize($teamId));
    }

    public function studentSchoolYears(): HasMany
    {
        return $this->hasMany(StudentSchoolYear::class, 's_role_id', 's_role_id');
    }
}
