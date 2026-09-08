<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who wrote the archived study.
 *
 * A similarity report named a title, a year and a programme and stopped
 * there, so a team told their topic was close to something already done had
 * no way to find out whose work it was - which is the first thing anybody
 * asks and the first thing an adviser asks for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archived_documents', function (Blueprint $table) {
            $table->string('authors', 500)->nullable()->after('abstract');
        });
    }

    public function down(): void
    {
        Schema::table('archived_documents', function (Blueprint $table) {
            $table->dropColumn('authors');
        });
    }
};
