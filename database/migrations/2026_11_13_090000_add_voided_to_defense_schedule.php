<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A team sent back to a stage keeps its earlier defenses, but they stop
 * counting: voided holds the row's own id, 0 on a defense that counts.
 * It joins the unique key so a fresh attempt 1 can sit beside a voided one.
 */
return new class extends Migration
{
    private const OLD = 'defense_schedule_team_id_defense_type_attempt_unique';

    public function up(): void
    {
        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->unsignedBigInteger('voided')->default(0);
            $table->unique(['team_id', 'defense_type', 'attempt', 'voided'], 'defense_schedule_live_attempt_unique');
        });

        if (Schema::hasIndex('defense_schedule', self::OLD)) {
            Schema::table('defense_schedule', fn (Blueprint $table) => $table->dropUnique(self::OLD));
        }
    }

    public function down(): void
    {
        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->dropUnique('defense_schedule_live_attempt_unique');
            $table->dropColumn('voided');
        });
    }
};
