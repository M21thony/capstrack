<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pre-proposal may serve several SDGs now, kept on one line - longer than
 * the 255 characters the column held.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->text('sdg_goals')->change();
        });
    }

    public function down(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->string('sdg_goals')->change();
        });
    }
};
