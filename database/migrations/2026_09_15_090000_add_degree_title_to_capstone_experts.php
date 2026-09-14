<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The degree as it is written after a name - MIT, PhD. highest_degree keeps
 * the level (master, doctorate) the Panel Chair rule reads; choosing a title
 * sets both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            if (! Schema::hasColumn('capstone_experts', 'degree_title')) {
                $table->string('degree_title', 20)->nullable()->after('highest_degree');
            }
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn('degree_title');
        });
    }
};
