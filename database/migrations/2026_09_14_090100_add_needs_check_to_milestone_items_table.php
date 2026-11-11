<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An approval the CaPSA/TSA still has to see for themselves.
 *
 * A CLF closes on the adviser's verdict. Some CaPSA/TSAs want to look at the
 * signed copy before believing it, so an approval line can ask for that: once
 * approved it waits on the CaPSA/TSA, and closes when they check it (written to
 * completed_at, the same column a checked form uses).
 *
 * Off by default, so every existing checklist behaves exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestone_items', function (Blueprint $table) {
            $table->boolean('needs_check')->default(false)->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('milestone_items', function (Blueprint $table) {
            $table->dropColumn('needs_check');
        });
    }
};
