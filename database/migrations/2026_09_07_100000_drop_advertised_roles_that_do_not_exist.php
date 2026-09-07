<?php

use App\Models\Recruitment;
use App\Models\StudentRole;
use Illuminate\Database\Migrations\Migration;

/**
 * Clear roles from recruitment posts that are not capstone team roles.
 *
 * The post picker used to offer eight invented options - "App Developer",
 * "Backend Dev", "Fullstack Dev", "Project Manager" and so on - none of which
 * exists in student_roles. A post asking for those advertises something the
 * system cannot deliver: whoever joins can never be given the role, because
 * there is no such role to give.
 *
 * The picker offers the real three now and the controller refuses anything
 * else, but posts written before that are still sitting in the table saying
 * the old thing. Rather than guess that "App Developer" meant Software
 * Engineer/Programmer - which is the leader's intent to state, not this
 * migration's to invent - the unknown entries are dropped. A post left with
 * none advertises no particular role, which the joining student's dialog
 * already handles by offering every seat that is free.
 */
return new class extends Migration
{
    public function up(): void
    {
        $real = StudentRole::memberRoles()->pluck('s_role_name')->all();

        if ($real === []) {
            return;
        }

        foreach (Recruitment::all() as $post) {
            $kept = array_values(array_intersect((array) $post->roles, $real));

            if ($kept !== (array) $post->roles) {
                $post->update(['roles' => $kept]);
            }
        }
    }

    /** Nothing to restore: what is removed here never named a real role. */
    public function down(): void {}
};
