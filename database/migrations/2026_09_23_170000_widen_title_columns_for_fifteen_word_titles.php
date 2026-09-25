<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titles may now run to fifteen meaningful words, which can pass the old
 * 150-character columns. Widened on both the pre-proposal and the team,
 * since the team carries the chosen title.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->string('title', 255)->change();
        });

        Schema::table('team', function (Blueprint $table) {
            $table->string('project_title', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->string('title', 150)->change();
        });

        Schema::table('team', function (Blueprint $table) {
            $table->string('project_title', 150)->nullable()->change();
        });
    }
};
