<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An expert's research areas, kept per side: what they advise on, what they
 * consult on, what they check as a grammarian, and what they sit on panels
 * for. An adviser and consultant in one person is two different offers.
 *
 * Null is the list from before sides existed, or one a CaPSA entered: it
 * stands for every side until the expert saves their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expert_expertise', function (Blueprint $table) {
            $table->string('side', 20)->nullable()->after('expertise_field');
            $table->index(['expert_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::table('expert_expertise', function (Blueprint $table) {
            $table->dropIndex(['expert_id', 'side']);
            $table->dropColumn('side');
        });
    }
};
