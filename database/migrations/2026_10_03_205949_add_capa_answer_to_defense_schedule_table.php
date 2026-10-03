<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Title, Pre Oral and Final Oral Defense go ahead only when the team's
 * adviser (CaPA) says the team is ready. "Go" is kept here; "not ready"
 * cancels the date (defense_cancellations) and clears this again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->string('capa_answer', 8)->nullable();
            $table->timestamp('capa_answered_at')->nullable();
            $table->unsignedBigInteger('capa_answered_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->dropColumn(['capa_answer', 'capa_answered_at', 'capa_answered_by']);
        });
    }
};
