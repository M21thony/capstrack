<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            // capstone_experts.max_teams is a single integer, but the real
            // roster data sometimes gives per-program capacity instead (e.g.
            // "1 - BSIT, 1 - BSIS") - a genuinely separate change (auditing
            // every place max_teams is checked) that's deliberately deferred.
            // This just preserves the exact uploaded text so nothing is lost
            // in the meantime; max_teams itself gets a best-effort leading
            // number for now, kept working for existing capacity checks.
            $table->string('max_teams_raw', 100)->nullable()->after('max_teams');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn('max_teams_raw');
        });
    }
};
