<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The highest degree an expert holds: bachelor, master or doctorate.
 *
 * The department's rule is that a Panel Chair has a Master's degree or
 * higher. "credentials" is free text ("MIT, LPT") and cannot be checked, so
 * the rule needs a field of its own. Nullable - nobody's degree is on record
 * yet, and an unknown degree does not qualify anyone to chair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->string('highest_degree', 20)->nullable()->after('credentials');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn('highest_degree');
        });
    }
};
