<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The .drawio files a team may send along with a document: the source of its
 * diagrams, so a reviewer comparing two versions sees exactly which shapes,
 * lines and words changed instead of a guess from the PDF's pictures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_documents', function (Blueprint $table) {
            $table->text('diagram_files')->nullable()->after('file_path');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_documents', function (Blueprint $table) {
            $table->dropColumn('diagram_files');
        });
    }
};
