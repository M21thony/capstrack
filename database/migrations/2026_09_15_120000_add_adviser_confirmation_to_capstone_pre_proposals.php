<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A team sent back for a new title already has its adviser, so there is no
 * application for the adviser to approve the title through. The adviser
 * confirms the new title here instead, after the CaPSA approves it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->string('adviser_status', 20)->nullable()->after('rejection_reason');
            $table->text('adviser_note')->nullable()->after('adviser_status');
            $table->timestamp('adviser_decided_at')->nullable()->after('adviser_note');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->dropColumn(['adviser_status', 'adviser_note', 'adviser_decided_at']);
        });
    }
};
