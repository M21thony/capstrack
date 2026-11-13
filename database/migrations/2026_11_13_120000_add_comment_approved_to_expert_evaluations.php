<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A leader's comment waits for the CaPSA/TSA: null is waiting, true reaches
 * the expert, false was removed. Comments sent before this stay visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expert_evaluations', fn (Blueprint $table) => $table->boolean('comment_approved')->nullable());

        DB::table('expert_evaluations')->whereNotNull('comment')->update(['comment_approved' => true]);
    }

    public function down(): void
    {
        Schema::table('expert_evaluations', fn (Blueprint $table) => $table->dropColumn('comment_approved'));
    }
};
