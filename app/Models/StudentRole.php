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

    // Picks whichever non-leader role (UI Designer, Document Writer/QA
    // Tester) isn't already held by someone on the team, for a new member
    // joining without having chosen a specific role yet.
    public static function nextAvailableMemberRoleId(int $teamId): ?int
    {
        $takenRoleIds = StudentSchoolYear::where('team_id', $teamId)
            ->whereNotNull('s_role_id')
            ->pluck('s_role_id');

        return static::where('s_role_name', '!=', self::LEADER_ROLE_NAME)
            ->whereNotIn('s_role_id', $takenRoleIds)
            ->value('s_role_id');
    }

    public function studentSchoolYears(): HasMany
    {
        return $this->hasMany(StudentSchoolYear::class, 's_role_id', 's_role_id');
    }
}
