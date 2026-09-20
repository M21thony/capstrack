<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The suggestion a CaPSA may leave when she recommends a title.
     *
     * Its own column rather than adviser_note: that one is the team's own
     * adviser confirming the title, written before it ever reaches her.
     */
    public function up(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->text('capsa_note')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->dropColumn('capsa_note');
        });
    }
};
