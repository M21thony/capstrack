<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Older databases hold `role` as a fixed list without "panel", so a file sent
 * to a defense panel was refused. The create migration says plain text; this
 * brings those databases in line with it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_document_review', function (Blueprint $table) {
            $table->string('role')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Nothing to undo: going back to the fixed list would drop panel rows.
    }
};
