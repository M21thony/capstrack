<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Team Torchic survived the first removal: a student who had been on it was
 * deleted, but their enrolment row still named the team, so it looked like it
 * had a member while every screen showed 0. Enrolment rows of students that no
 * longer exist are taken off their teams, and Torchic, now truly empty, goes.
 */
return new class extends Migration
{
    /** Tables that point at a team without a foreign key to clean up after it. */
    private const LEFTOVERS = [
        'member_leave_requests', 'expert_removal_requests', 'team_leave_requests',
        'leave_approvals', 'deliverable_checks',
    ];

    public function up(): void
    {
        // A deleted student is on no team.
        DB::table('student_s_y')
            ->whereNotNull('team_id')
            ->whereNotExists(fn ($q) => $q->from('students')->whereColumn('students.student_id', 'student_s_y.student_id'))
            ->update(['team_id' => null, 's_role_id' => null]);

        $teamIds = DB::table('team')
            ->whereIn(DB::raw('LOWER(TRIM(team_name))'), ['torchic', 'team torchic'])
            ->whereNotExists(fn ($q) => $q->from('student_s_y')->whereColumn('student_s_y.team_id', 'team.team_id'))
            ->pluck('team_id');

        if ($teamIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($teamIds) {
            foreach (self::LEFTOVERS as $table) {
                DB::table($table)->whereIn('team_id', $teamIds)->delete();
            }

            DB::table('archived_documents')->whereIn('team_id', $teamIds)->update(['team_id' => null]);
            DB::table('team')->whereIn('team_id', $teamIds)->delete();
        });
    }

    /** A deleted team is not brought back. */
    public function down(): void {}
};
