<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The criteria a panelist abstained from grading, beside the ones they scored. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel_grades', function (Blueprint $table) {
            $table->json('abstained')->nullable()->after('scores');
        });
    }

    public function down(): void
    {
        Schema::table('panel_grades', function (Blueprint $table) {
            $table->dropColumn('abstained');
        });
    }
};
