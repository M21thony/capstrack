<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two things the checklist grid needs from an item.
 *
 * The grid puts all nineteen deliverables across the page, and nineteen
 * columns only fit if a header stops repeating what the band above it already
 * says - "Chapter 1 / Draft" rather than "Chapter 1 Draft" three times over.
 * So an item carries a short label for the column and the phase it belongs
 * to, which is what groups the columns.
 *
 * Both are nullable. An item a CaPSA/TSA adds by hand has neither, and falls back
 * to its title under a trailing group.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestone_items', function (Blueprint $table) {
            $table->string('short_label', 40)->nullable()->after('title');
            $table->string('phase', 40)->nullable()->after('short_label');
            $table->index('phase');
        });
    }

    public function down(): void
    {
        Schema::table('milestone_items', function (Blueprint $table) {
            $table->dropIndex(['phase']);
            $table->dropColumn(['short_label', 'phase']);
        });
    }
};
