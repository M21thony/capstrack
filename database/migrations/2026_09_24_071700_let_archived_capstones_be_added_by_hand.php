<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An archived capstone added by hand has no team in this system and, often,
 * no PDF. Both columns were required, so "Add Archived Capstone" - which
 * sends neither - failed on every save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archived_documents', function (Blueprint $table) {
            $table->unsignedInteger('team_id')->nullable()->change();
            $table->string('file_path', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Left nullable: rows added by hand since would break a NOT NULL.
    }
};
