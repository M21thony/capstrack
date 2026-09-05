<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a resource be aimed at one section rather than a whole program.
 *
 * It holds the year and letter only - "4A" - because resource_for already
 * holds the program. Storing the full "BSIT-4A" would put the program in two
 * columns, and the two would eventually disagree; the pair is the label.
 *
 * Null means every section of that program, which is what all existing rows
 * mean and why the column is nullable rather than backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('resources', 'section')) {
            return;
        }

        Schema::table('resources', function (Blueprint $table) {
            $table->string('section', 8)->nullable()->after('resource_for');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('resources', 'section')) {
            return;
        }

        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn('section');
        });
    }
};
