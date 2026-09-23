<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Team Torchic was left with no members. Remove it, and anything still
 * waiting on it, so it stops showing up in Teams, Reports and Analytics.
 * A Torchic that has members again is left alone.
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

            // Assignments, titles, defenses, milestones and invitations go with it (cascade).
            DB::table('team')->whereIn('team_id', $teamIds)->delete();
        });
    }

    /** A deleted team is not brought back. */
    public function down(): void {}
};
