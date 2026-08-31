<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A review has never recorded whether the reviewer was happy with the
 * document.
 *
 * review_status only ever says how far along the reading is - To Review,
 * Reviewing, Reviewed - and "Reviewed" means they finished looking, not that
 * they approved. Advisers have been signalling approval by typing it into a
 * remark, which nothing can query. The Capstone 1 checklist has five lines
 * that are exactly this decision ("Signed CLF Approving Chapter 1", the two
 * recommending for Title Defense), so they cannot be tracked until it is
 * recorded as data.
 *
 * Who decided is already known: expert_id is the reviewer and role says
 * whether they were acting as the adviser or the consultant, so approval by
 * both needs nothing extra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_document_review', function (Blueprint $table) {
            // Null means no decision yet, which is every existing row - none
            // of the remarks are read or guessed at.
            $table->string('verdict', 32)->nullable()->after('review_status');
            $table->timestamp('verdict_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_document_review', function (Blueprint $table) {
            $table->dropColumn(['verdict', 'verdict_at']);
        });
    }
};
