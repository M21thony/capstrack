<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a CaPSA/TSA, Admin or Expert finished (or skipped) their portal's guided
 * tour - the same thing students.tutorial_completed_at records for students.
 * Empty until then, so the tour opens by itself once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->timestamp('tour_completed_at')->nullable()->after('first_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn('tour_completed_at');
        });
    }
};
