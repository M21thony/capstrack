<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An expert on study leave - taking a master's or a doctorate - still takes
 * teams, but is slower to schedule. The CaPSA/TSA sets it; students see it as a
 * quiet tag on the expert's card.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->boolean('on_study_leave')->default(false)->after('degree_title');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn('on_study_leave');
        });
    }
};
