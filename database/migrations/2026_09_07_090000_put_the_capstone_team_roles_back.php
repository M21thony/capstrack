<?php

use App\Models\StudentRole;
use App\Models\StudentSchoolYear;
use Database\Seeders\StudentRoleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Restore the four capstone team roles, and give existing leaders theirs.
 *
 * student_roles was emptied. Nothing complained, because every piece of code
 * that wants the leader's role calls StudentRole::leaderRoleId(), which
 * answers null when the table has nothing in it - and null is a perfectly
 * acceptable s_role_id. So teams kept being created, leaders kept being
 * appointed, and not one of them was recorded as holding any role.
 *
 * A migration rather than only a seeder, because the repair has to reach
 * every copy of this database - including the one the system is defended on
 * - without anybody remembering to run a seed command. The role list itself
 * lives in StudentRoleSeeder and is called from here, so there is one place
 * the wording is written down.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new StudentRoleSeeder)->run();

        $leaderRoleId = StudentRole::leaderRoleId();

        if (! $leaderRoleId) {
            return;
        }

        // Only where nothing is recorded. Somebody who has been given a role
        // by hand keeps it - this is filling a hole, not imposing a rule.
        $leaderIds = DB::table((new App\Models\Team)->getTable())
            ->whereNotNull('leader_id')
            ->pluck('leader_id');

        if ($leaderIds->isEmpty()) {
            return;
        }

        StudentSchoolYear::withoutGlobalScopes()
            ->whereIn('student_id', $leaderIds)
            ->whereNull('s_role_id')
            ->update(['s_role_id' => $leaderRoleId]);
    }

    /**
     * Deliberately empty. Reversing this would mean deleting the roles the
     * system needs to function, which is the state being repaired.
     */
    public function down(): void {}
};
