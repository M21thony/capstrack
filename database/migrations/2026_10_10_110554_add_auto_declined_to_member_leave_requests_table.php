<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A request to leave or remove a member that the CaPSA/TSA did not decide within
 * MemberLeaveRequest::DECIDE_WITHIN_DAYS is declined on its own; this says
 * which declines were those, so the team is not told the CaPSA/TSA said no.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_leave_requests', function (Blueprint $table) {
            $table->boolean('auto_declined')->default(false)->after('decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('member_leave_requests', function (Blueprint $table) {
            $table->dropColumn('auto_declined');
        });
    }
};
