<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which checklist line an upload is for.
 *
 * Until now the checklist guessed, by reading "Chapter N" out of the file
 * name, so a file called final_na_final.pdf counted towards nothing and a
 * line with no chapter (Revised Proposal Manuscript, anything a CaPSA adds)
 * could never be closed by an upload at all.
 *
 * Nullable on purpose: an upload sent only for feedback - a grammarian's pass
 * - is not for any line, and must not move progress. Rows from before this
 * keep being matched by chapter, as they always were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('milestone_item_id')->nullable()->after('chapter');
            $table->index('milestone_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_documents', function (Blueprint $table) {
            $table->dropIndex(['milestone_item_id']);
            $table->dropColumn('milestone_item_id');
        });
    }
};
