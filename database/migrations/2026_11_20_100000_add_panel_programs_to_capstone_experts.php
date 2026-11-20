<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The courses an expert sits panels for, e.g. ["BSIT","BSIS"]; null or empty is every course. */
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->json('panel_programs')->nullable()->after('max_panels');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn('panel_programs');
        });
    }
};
