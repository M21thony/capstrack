<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** How many teams' panels an expert can sit on at once; null is no limit. */
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_panels')->nullable()->after('panel_strengths');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn('max_panels');
        });
    }
};
