<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The panel's signed paper grade sheets, one PDF that CaPSA attaches. */
    public function up(): void
    {
        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->string('signed_sheets_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('defense_schedule', function (Blueprint $table) {
            $table->dropColumn('signed_sheets_path');
        });
    }
};
