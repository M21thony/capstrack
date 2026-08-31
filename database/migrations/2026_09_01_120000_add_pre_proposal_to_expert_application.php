<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which approved pre-proposal a team applied to an expert with.
 *
 * A team had to attach a PDF to every application - the same document their
 * CaPSA had already read and approved. Re-uploading it is busywork, and
 * nothing stopped a team attaching a different, unapproved version instead.
 * Pointing at the approved pre-proposal means the expert is looking at the
 * one that got through, and can see that it did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_expert_application', function (Blueprint $table) {
            $table->unsignedBigInteger('pre_proposal_id')->nullable()->after('expert_role_id');
            $table->index('pre_proposal_id');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_expert_application', function (Blueprint $table) {
            $table->dropIndex(['pre_proposal_id']);
            $table->dropColumn('pre_proposal_id');
        });
    }
};
