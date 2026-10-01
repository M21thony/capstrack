<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checking a revision: when the team sends the next version, the reviewer
 * goes through the remarks they left on the last one and says whether each
 * was fixed. That answer is kept on the remark it is about.
 *
 * A remark still not fixed is carried into the new review as a remark of its
 * own, so the team sees it again; carried_from_id points back at the remark
 * it came from, so changing the answer to Fixed takes it away again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdf_remarks', function (Blueprint $table) {
            $table->string('revision_status', 12)->nullable()->after('diagram_type');
            $table->timestamp('revision_checked_at')->nullable()->after('revision_status');
            $table->unsignedBigInteger('carried_from_id')->nullable()->after('revision_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('pdf_remarks', function (Blueprint $table) {
            $table->dropColumn(['revision_status', 'revision_checked_at', 'carried_from_id']);
        });
    }
};
