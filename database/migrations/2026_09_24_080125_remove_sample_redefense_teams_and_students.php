<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Take out the sample data RedefenseSampleSeeder put on the live system: the
 * teams "Sample Redefense" and "Sample New Title" and their six sample
 * students, with everything that belongs to them. A sample team that a real
 * student has joined is kept; only its sample students leave it.
 */
return new class extends Migration
{
    private const TEAMS = ['Sample Redefense', 'Sample New Title'];

    /** The seeder's addresses: sample.redefense.1@… and sample.newtitle.1@… */
    private const EMAILS = ['sample.redefense.%@my.cspc.edu.ph', 'sample.newtitle.%@my.cspc.edu.ph'];

    /** Tables that point at a team without a foreign key. */
    private const TEAM_LEFTOVERS = [
        'member_leave_requests', 'expert_removal_requests', 'team_leave_requests',
        'leave_approvals', 'deliverable_checks', 'capstone_expert_application',
    ];

    public function up(): void
    {
        $studentIds = DB::table('students')
            ->where(fn ($q) => collect(self::EMAILS)->each(fn ($email) => $q->orWhere('email', 'like', $email)))
            ->pluck('student_id');

        $teamIds = DB::table('team')
            ->whereIn('team_name', self::TEAMS)
            ->whereNotExists(fn ($q) => $q->from('student_s_y')
                ->whereColumn('student_s_y.team_id', 'team.team_id')
                ->whereNotIn('student_s_y.student_id', $studentIds->all()))
            ->pluck('team_id');

        if ($studentIds->isEmpty() && $teamIds->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($studentIds, $teamIds) {
            if ($teamIds->isNotEmpty()) {
                foreach (self::TEAM_LEFTOVERS as $table) {
                    DB::table($table)->whereIn('team_id', $teamIds)->delete();
                }

                DB::table('archived_documents')->whereIn('team_id', $teamIds)->update(['team_id' => null]);

                // What the panel and experts were told about them.
                DB::table('notifications')->where(fn ($q) => collect(self::TEAMS)
                    ->each(fn ($name) => $q->orWhere('message', 'like', '%'.$name.'%')))->delete();

                // Titles, defenses, panels, grades and assignments go with it (cascade).
                DB::table('team')->whereIn('team_id', $teamIds)->delete();
            }

            if ($studentIds->isNotEmpty()) {
                // Everything the sample students own, in every table that records a student.
                foreach ($this->tablesWithStudents() as $table) {
                    DB::table($table)->whereIn('student_id', $studentIds)->delete();
                }

                DB::table('students')->whereIn('student_id', $studentIds)->delete();
            }
        });
    }

    /** A deleted sample is not brought back; the seeder can make it again. */
    public function down(): void {}

    /** @return list<string> */
    private function tablesWithStudents(): array
    {
        // Only this database's tables: on MySQL, no schema means every database on the server.
        $schema = DB::getDriverName() === 'sqlite' ? null : DB::getDatabaseName();

        return collect(Schema::getTableListing($schema, false))
            ->reject(fn ($table) => $table === 'students')
            ->filter(fn ($table) => Schema::hasColumn($table, 'student_id'))
            ->values()
            ->all();
    }
};
