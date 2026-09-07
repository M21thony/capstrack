<?php

namespace Database\Seeders;

use App\Models\StudentRole;
use Illuminate\Database\Seeder;

/**
 * The four capstone team roles, as the course defines them.
 *
 * This used to create two rows called "Leader" and "Member" through a column
 * named role_name - which is neither the column on the table (s_role_name)
 * nor in the model's fillable list, so the names were silently dropped and
 * the rows came out blank. It was also never called from DatabaseSeeder, so
 * a fresh install got no roles at all.
 *
 * That is why leaderRoleId() has been returning null everywhere it is used,
 * and why every student in the database has s_role_id NULL: there has never
 * been a role for the code to find.
 *
 * Safe to run repeatedly - matched on the name, so it fills gaps and
 * refreshes wording without disturbing any s_role_id a student already holds.
 */
class StudentRoleSeeder extends Seeder
{
    /**
     * Order matters. nextAvailableMemberRoleId() hands out the first
     * non-leader role nobody on the team holds yet, so this is the order new
     * members are given roles in.
     *
     * @var array<string, string>
     */
    private const ROLES = [
        'Project Head/Database Designer' => 'PH/DD - Identifies the goals, objectives, solution, '
            .'scope, resources and context of the project. The team leader, accountable and '
            .'knowledgeable of the logical plan, and designer of the database back-end of the system.',

        'Network Designer/UI Designer' => 'ND/UID - Prepares the network and external design of the '
            .'system in terms that make sense to a person using it: the network plan, the screens '
            .'and reports, and the navigational map of the system. Coordinates well with the PH/DD.',

        'Software Engineer/Programmer' => 'SE/P - Writes and tests the system. This role is played '
            .'partly by every member of the team. Communicates well with the ND/UID.',

        'QA Tester/Documentation Writer' => 'QA/TW - Responsible for the documentation side, or '
            .'manuscript, of the system. Coordinates well with the SE/P.',
    ];

    public function run(): void
    {
        foreach (self::ROLES as $name => $duties) {
            StudentRole::updateOrCreate(
                ['s_role_name' => $name],
                ['s_role_description' => $duties],
            );
        }
    }
}
