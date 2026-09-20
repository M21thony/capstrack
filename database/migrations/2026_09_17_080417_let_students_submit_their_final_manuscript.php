<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The manuscript a team hands in when their journey is done.
 *
 * A team that has passed the Final Oral Defense submits their Capstone
 * Manuscript on the archive page. It lands here as a pending row the admin
 * publishes or declines - an archived capstone is the public record, so an
 * unreviewed PDF does not appear in it on its own. Submitted_by and
 * submitted_at say who handed it in and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archived_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('submitted_by')->nullable()->after('file_size');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->string('submission_status', 12)->default('published')->after('submitted_at');

            $table->index(['submission_status', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('archived_documents', function (Blueprint $table) {
            $table->dropIndex(['submission_status', 'submitted_at']);
            $table->dropColumn(['submitted_by', 'submitted_at', 'submission_status']);
        });
    }
};
