<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pre-proposal set aside when the panel asks for a new title. Kept as a
 * record of what was tried, but no longer counted as the team's titles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('capstone_pre_proposals', function (Blueprint $table) {
            $table->dropColumn('retired_at');
        });
    }
};
