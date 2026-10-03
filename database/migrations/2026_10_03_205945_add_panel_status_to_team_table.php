<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A team's panel is a draft while the CaPSAs are still working it out - a
 * panelist can move to another team - and final once the CaPSA says so. A
 * final panel sits every defense through Final Oral; only a panelist who can
 * no longer sit is replaced (panel_replacements).
 *
 * Teams whose panel has already sat or been given a date are final: that
 * panel is the one they have. A panel nobody has been scheduled with yet is
 * a draft, for the CaPSA to look over and finalize.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team', function (Blueprint $table) {
            $table->string('panel_status', 8)->nullable();
            $table->timestamp('panel_finalized_at')->nullable();
            $table->unsignedBigInteger('panel_finalized_by')->nullable();
        });

        $withPanel = DB::table('defense_schedule')
            ->join('defense_panels', 'defense_panels.defense_id', '=', 'defense_schedule.defense_id')
            ->distinct()
            ->pluck('defense_schedule.team_id');

        $held = DB::table('defense_schedule')
            ->whereIn('team_id', $withPanel)
            ->where(fn ($q) => $q->whereNotNull('schedule_date')->orWhereNotNull('outcome'))
            ->distinct()
            ->pluck('team_id');

        DB::table('team')->whereIn('team_id', $held)->update(['panel_status' => 'final', 'panel_finalized_at' => now()]);
        DB::table('team')->whereIn('team_id', $withPanel->diff($held))->update(['panel_status' => 'draft']);
    }

    public function down(): void
    {
        Schema::table('team', function (Blueprint $table) {
            $table->dropColumn(['panel_status', 'panel_finalized_at', 'panel_finalized_by']);
        });
    }
};
