<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel comment 15: what an expert is strongest in as a panelist -
 * manuscript, system design, software development - so the CaPSA can seat a
 * panel that covers all three.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->json('panel_strengths')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn('panel_strengths');
        });
    }
};
