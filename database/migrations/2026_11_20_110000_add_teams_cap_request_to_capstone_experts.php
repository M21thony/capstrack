<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Program => teams an expert asked for above 10 in all, waiting for their CaPSA/TSA; null is no request. */
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->json('teams_cap_request')->nullable()->after('program_slots');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn('teams_cap_request');
        });
    }
};
