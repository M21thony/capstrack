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
     * The role everybody on the team holds a share of.
     *
     * The course is explicit that writing and testing the system "must be
     * played partly by each team member", so this one is not a seat anybody
     * can take from anybody else. It never fills, and it is never shown as
     * full.
     */
    public const SHARED_ROLE_NAME = 'Software Engineer/Programmer';

    /** How many of a team may hold one role. */
    public const SEATS = 2;

    /**
     * The number of people who may hold a role, or null when there is no
     * limit.
     *
     * Project Head seats one, and that one is the leader's - it is never
     * offered to anybody else. Programmer has no limit, for the reason
     * above. The remaining two seat a pair each.
     */
    public static function seatsFor(string $roleName): ?int
    {
        return match ($roleName) {
            self::LEADER_ROLE_NAME => 1,
            self::SHARED_ROLE_NAME => null,
            default => self::SEATS,
        };
    }

    /** The three roles a member may choose between. */
    public static function memberRoles()
    {
        return static::where('s_role_name', '!=', self::LEADER_ROLE_NAME)
            ->orderBy('s_role_id')
            ->get();
    }

    /**
     * The member roles this team still has room for, with who holds each.
     *
     * @return array<int, array{role: StudentRole, taken: int, seats: ?int, held_by: array<int, string>}>
     */
    public static function seatingFor(int $teamId): array
    {
        $holders = StudentSchoolYear::where('team_id', $teamId)
            ->whereNotNull('s_role_id')
            ->with('student')
            ->get()
            ->groupBy('s_role_id');

        return static::memberRoles()->map(function ($role) use ($holders) {
            $held = $holders->get($role->s_role_id, collect());

            return [
                'role' => $role,
                'taken' => $held->count(),
                'seats' => self::seatsFor($role->s_role_name),
                'held_by' => $held
                    ->map(fn ($row) => trim(($row->student->f_name ?? '').' '.($row->student->l_name ?? '')))
                    ->filter()
                    ->values()
                    ->all(),
            ];
        })->all();
    }

    /** Can one more person take this role on this team? */
    public static function hasSeat(int $teamId, int $roleId): bool
    {
        $role = static::find($roleId);

        if (! $role || $role->s_role_name === self::LEADER_ROLE_NAME) {
            return false;
        }

        $seats = self::seatsFor($role->s_role_name);

        // No limit: everybody programs, so there is always room.
        if ($seats === null) {
            return true;
        }

        return StudentSchoolYear::where('team_id', $teamId)
            ->where('s_role_id', $roleId)
            ->count() < $seats;
    }

    public function studentSchoolYears(): HasMany
    {
        return $this->hasMany(StudentSchoolYear::class, 's_role_id', 's_role_id');
    }
}
