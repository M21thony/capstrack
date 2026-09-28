<?php

use App\Models\DefenseSchedule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Clear the defenses left behind by teams that were deleted.
 *
 * Deleting a team used to leave its defense schedule, panels and grades in
 * place, and the scheduling board showed each one as an unknown team.
 * Removing a team's panel deleted the defense but left its grades. Both
 * take everything with them now; this clears what was already left over.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphanTeamIds = DB::table('defense_schedule')
            ->whereNotIn('team_id', DB::table('team')->select('team_id'))
            ->distinct()
            ->pluck('team_id')
            ->all();

        DefenseSchedule::removeForTeams($orphanTeamIds);

        foreach (['panel_grades', 'defense_panels'] as $table) {
            DB::table($table)->whereNotIn('defense_id', DB::table('defense_schedule')->select('defense_id'))->delete();
        }
    }

    public function down(): void
    {
        // Defenses of deleted teams are not brought back.
    }
};
