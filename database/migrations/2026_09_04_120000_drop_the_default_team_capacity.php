<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An expert who has not said how many teams they take does not take four.
 *
 * The column defaulted to 4 and the model handed back 4 for a null, so every
 * account ever made - by the list upload, by Add CaPSA, by anything - was
 * credited with four teams' worth of slots that nobody had agreed to. That
 * number then fed the "slots left" and "full" readings on the expert lists
 * and the assignment checks, so it was not only cosmetic: it decided who
 * could still be assigned.
 *
 * Unset now stays unset. Existing rows keep whatever they hold - changing a
 * default does not rewrite stored values - so this only stops new accounts
 * being given a capacity out of the air.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_teams')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_teams')->nullable(false)->default(4)->change();
        });
    }
};
