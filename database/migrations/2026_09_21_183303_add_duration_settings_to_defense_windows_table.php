<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How long each defense in a stage runs.
     *
     * Until now every defense was a fixed two-hour block, written into the
     * scheduler. The default here is exactly that, so a window that was saved
     * before this existed keeps behaving the way it always did until somebody
     * changes it.
     *
     * Stored on the window rather than in one global setting because the
     * stages are not the same length: a Pre-proposal Defense is a short panel
     * conversation, a Final Oral Defense is not.
     */
    public function up(): void
    {
        Schema::table('defense_windows', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->default(120)->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('defense_windows', function (Blueprint $table) {
            $table->dropColumn('duration_minutes');
        });
    }
};
