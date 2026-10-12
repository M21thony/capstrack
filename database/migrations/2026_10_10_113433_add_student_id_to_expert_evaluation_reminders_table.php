<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who was told about an owed evaluation, not only which team. The leader can
 * change while an evaluation waits, and the new leader has to hear about it
 * too - once, like the first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expert_evaluation_reminders', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id')->nullable()->after('defense_id');
        });

        // Who each earlier reminder went to: the leader whose bell carries it,
        // else whoever leads the team now.
        foreach (DB::table('expert_evaluation_reminders')->whereNotNull('defense_id')->get() as $row) {
            $told = DB::table('notifications')->where('type', 'evaluation_due')
                ->where('data', 'like', '%evaluation:defense:'.$row->defense_id.'"%')
                ->value('student_id')
                ?? DB::table('teams')->where('team_id', $row->team_id)->value('leader_id');

            DB::table('expert_evaluation_reminders')->where('id', $row->id)->update(['student_id' => $told]);
        }

        Schema::table('expert_evaluation_reminders', function (Blueprint $table) {
            $table->dropUnique('expert_evaluation_reminders_per_defense');
            $table->unique(['team_id', 'defense_id', 'student_id'], 'expert_evaluation_reminders_per_leader');
        });
    }

    public function down(): void
    {
        Schema::table('expert_evaluation_reminders', function (Blueprint $table) {
            $table->dropUnique('expert_evaluation_reminders_per_leader');
        });

        // Back to one row per team and defense.
        $keep = DB::table('expert_evaluation_reminders')->selectRaw('MIN(id) as id')->groupBy('team_id', 'defense_id')->pluck('id');
        DB::table('expert_evaluation_reminders')->whereNotIn('id', $keep)->delete();

        Schema::table('expert_evaluation_reminders', function (Blueprint $table) {
            $table->unique(['team_id', 'defense_id'], 'expert_evaluation_reminders_per_defense');
            $table->dropColumn('student_id');
        });
    }
};
