<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A resource can be aimed at several of a CaPSA/TSA's sections, not just one.
 *
 * The single `section` column added earlier could only say "4A". A CaPSA/TSA
 * handing out material usually means "these two of my four sections", so the
 * targets live in their own table - one row per section, the full label,
 * because a set of labels is not a column.
 *
 * When a resource has rows here, resource_for stays null: the labels already
 * carry the program, and holding it in both places is how the two come to
 * disagree. No rows here means the resource is scoped the older way - by
 * program, or by nothing at all, which reaches the uploader's own students.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resource_sections')) {
            Schema::create('resource_sections', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('resource_id');
                $table->string('section', 24);

                $table->unique(['resource_id', 'section']);
                $table->index('section');
            });
        }

        // Nothing used the single column - it was added today and no row set
        // it - so there is nothing to carry across.
        if (Schema::hasColumn('resources', 'section')) {
            Schema::table('resources', function (Blueprint $table) {
                $table->dropColumn('section');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_sections');

        if (! Schema::hasColumn('resources', 'section')) {
            Schema::table('resources', function (Blueprint $table) {
                $table->string('section', 8)->nullable()->after('resource_for');
            });
        }
    }
};
