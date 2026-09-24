<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A team hands in its title, abstract and tags, not a PDF.
 *
 * The manuscript upload is gone from the student's Submit to Archive form,
 * so an archived capstone no longer always has a file behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archived_documents', function (Blueprint $table) {
            $table->string('file_path', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows saved without a file would break NOT NULL, so they get an
        // empty path - the same "no file" every reader already checks for.
        DB::table('archived_documents')->whereNull('file_path')->update(['file_path' => '']);

        Schema::table('archived_documents', function (Blueprint $table) {
            $table->string('file_path', 500)->nullable(false)->change();
        });
    }
};
