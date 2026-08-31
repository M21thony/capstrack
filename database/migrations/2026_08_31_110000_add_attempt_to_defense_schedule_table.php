<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let a team defend the same stage more than once.
     *
     * defense_schedule carried UNIQUE(team_id, defense_type), so a team could
     * only ever have one "Title Defense" row. A redefense therefore had
     * nowhere to go, and reusing the existing row is destructive: panel_grades
     * is keyed on (defense_id, expert_id) and written with updateOrCreate, so
     * the second attempt's scores, verdict and remarks overwrite the first
     * attempt's in place. The failed attempt - the very thing a redefense
     * exists to supersede - disappears, along with the panel's reasons.
     *
     * An attempt number makes each try its own row, with its own panel,
     * schedule and grades. Nothing else needs to change to keep the history:
     * panel_grades already hangs off defense_id, which is now per-attempt.
     *
     * Every existing row is attempt 1.
     */
    public function up(): void
    {
        if (! Schema::hasTable('defense_schedule')) {
            return;
        }

        if (! Schema::hasColumn('defense_schedule', 'attempt')) {
            Schema::table('defense_schedule', function (Blueprint $table) {
                $table->unsignedTinyInteger('attempt')->default(1)->after('defense_type');
            });
        }

        // The old key has to go before the new one can exist, or the second
        // attempt is refused before it is ever inserted.
        $this->dropUnique('defense_schedule_team_id_defense_type_unique');

        if (! $this->hasIndex('defense_schedule_team_id_defense_type_attempt_unique')) {
            Schema::table('defense_schedule', function (Blueprint $table) {
                $table->unique(['team_id', 'defense_type', 'attempt']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('defense_schedule')) {
            return;
        }

        // Collapsing back to one row per stage would have to discard every
        // attempt past the first, so refuse rather than delete a team's
        // defence history behind their back.
        $extraAttempts = DB::table('defense_schedule')->where('attempt', '>', 1)->count();

        if ($extraAttempts > 0) {
            throw new RuntimeException(
                "Cannot roll back: {$extraAttempts} redefense record(s) exist, and the old schema has no room for them. "
                .'Remove them deliberately first if that is really what you want.'
            );
        }

        $this->dropUnique('defense_schedule_team_id_defense_type_attempt_unique');

        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->dropColumn('attempt');
            $table->unique(['team_id', 'defense_type']);
        });
    }

    private function hasIndex(string $name): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::selectOne('SHOW INDEX FROM defense_schedule WHERE Key_name = ?', [$name]) !== null;
        }

        return collect(DB::select("PRAGMA index_list('defense_schedule')"))
            ->contains(fn ($i) => $i->name === $name);
    }

    private function dropUnique(string $name): void
    {
        if (! $this->hasIndex($name)) {
            return;
        }

        Schema::table('defense_schedule', function (Blueprint $table) use ($name) {
            $table->dropUnique($name);
        });
    }
};
