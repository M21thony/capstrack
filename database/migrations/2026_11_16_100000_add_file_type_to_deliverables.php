<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The file type the CaPSA/TSA picked for a requirement (Deliverable::FILE_TYPES).
 * Empty keeps the usual files, so every line already there stays as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->string('file_type', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('deliverables', function (Blueprint $table) {
            $table->dropColumn('file_type');
        });
    }
};
