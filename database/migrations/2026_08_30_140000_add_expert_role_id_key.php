<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give expert_roles its own surrogate key and point assignments at it.
     *
     * capstone_expert_application recorded THAT a team engaged an expert but
     * never AS WHAT. Deriving it from expert_id can't work: expert_roles says
     * what an expert is qualified for (a set), so the same expert advising
     * Team A and consulting for Team B produces identical lookups. The role
     * belongs to the assignment, so it is stored there.
     *
     * expert_roles keeps a UNIQUE(expert_id, role_id) - that pair is still
     * the real identity, the new column is only a stable handle to reference.
     *
     * Note: these tables are MyISAM, which silently ignores FOREIGN KEY
     * constraints, so the reference is enforced in application code (see
     * Api\StudentExpertController::store) rather than by the database.
     */
    public function up(): void
    {
        if (! Schema::hasTable('expert_roles') || ! Schema::hasTable('capstone_expert_application')) {
            return;
        }

        if (! Schema::hasColumn('expert_roles', 'expert_role_id')) {
            if (DB::getDriverName() === 'mysql') {
                // The composite PK has to go before an AUTO_INCREMENT key can
                // be added - a table may only have one primary key.
                if (DB::selectOne("SHOW KEYS FROM expert_roles WHERE Key_name = 'PRIMARY'")) {
                    DB::statement('ALTER TABLE expert_roles DROP PRIMARY KEY');
                }

                DB::statement('ALTER TABLE expert_roles ADD COLUMN expert_role_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
                DB::statement('ALTER TABLE expert_roles ADD UNIQUE KEY expert_roles_expert_id_role_id_unique (expert_id, role_id)');
            } else {
                // SQLite (the test database) can neither drop a primary key
                // nor add an autoincrement column in place, so the table is
                // rebuilt and its rows copied across.
                Schema::create('expert_roles_rebuild', function (Blueprint $table) {
                    $table->id('expert_role_id');
                    $table->unsignedBigInteger('expert_id');
                    $table->unsignedBigInteger('role_id');
                    $table->unique(['expert_id', 'role_id']);
                });

                DB::table('expert_roles')->orderBy('expert_id')->orderBy('role_id')->chunk(500, function ($rows) {
                    DB::table('expert_roles_rebuild')->insert(
                        collect($rows)->map(fn ($r) => ['expert_id' => $r->expert_id, 'role_id' => $r->role_id])->all()
                    );
                });

                Schema::drop('expert_roles');
                Schema::rename('expert_roles_rebuild', 'expert_roles');
            }
        }

        if (! Schema::hasColumn('capstone_expert_application', 'expert_role_id')) {
            Schema::table('capstone_expert_application', function (Blueprint $table) {
                $table->unsignedBigInteger('expert_role_id')->nullable()->after('expert_id');
                $table->index('expert_role_id');
            });
        }

        // Backfill only where it is unambiguous: the expert holds exactly one
        // student-facing role. Anyone holding two (the very case this column
        // exists for) is left null rather than guessed at - a wrong role on a
        // real assignment is worse than a blank one.
        $studentFacing = DB::table('roles')
            ->whereIn('role_name', ['adviser', 'consultant', 'grammarian'])
            ->pluck('role_id');

        $rows = DB::table('capstone_expert_application')->whereNull('expert_role_id')->get(['assignment_id', 'expert_id']);

        foreach ($rows as $row) {
            $candidates = DB::table('expert_roles')
                ->where('expert_id', $row->expert_id)
                ->whereIn('role_id', $studentFacing)
                ->pluck('expert_role_id');

            if ($candidates->count() === 1) {
                DB::table('capstone_expert_application')
                    ->where('assignment_id', $row->assignment_id)
                    ->update(['expert_role_id' => $candidates->first()]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('expert_roles') || ! Schema::hasTable('capstone_expert_application')) {
            return;
        }

        if (Schema::hasColumn('capstone_expert_application', 'expert_role_id')) {
            Schema::table('capstone_expert_application', function (Blueprint $table) {
                $table->dropIndex(['expert_role_id']);
                $table->dropColumn('expert_role_id');
            });
        }

        if (Schema::hasColumn('expert_roles', 'expert_role_id') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE expert_roles DROP PRIMARY KEY');
            DB::statement('ALTER TABLE expert_roles DROP INDEX expert_roles_expert_id_role_id_unique');
            DB::statement('ALTER TABLE expert_roles DROP COLUMN expert_role_id');
            DB::statement('ALTER TABLE expert_roles ADD PRIMARY KEY (expert_id, role_id)');
        }
    }
};
