<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a student finished or skipped the welcome tour of the portal.
     *
     * Kept on the account rather than in the browser so a student is shown
     * the tour once, not once per phone and laptop they sign in from.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('tutorial_completed_at')->nullable()->after('last_seen_at');
        });

        // The tour is for new accounts. Students already here found their
        // way around without it, and meeting it mid-semester would only be
        // in the way.
        DB::table('students')->update(['tutorial_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('tutorial_completed_at');
        });
    }
};
