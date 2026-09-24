<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An abstention is now the whole sheet (panel_grades.status = abstained, the
 * reason in remarks), so the per-criterion list this column held for a day
 * is not read by anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('panel_grades', 'abstained')) {
            Schema::table('panel_grades', function (Blueprint $table) {
                $table->dropColumn('abstained');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('panel_grades', 'abstained')) {
            Schema::table('panel_grades', function (Blueprint $table) {
                $table->json('abstained')->nullable()->after('scores');
            });
        }
    }
};
