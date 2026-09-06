<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Switching an expert account off without throwing it away.
 *
 * Removing someone was a real DELETE, so a lecturer who went on study leave
 * had to be added again from scratch when they came back - and everything
 * hanging off their expert_id went with them the first time.
 *
 * The column is deactivated_at rather than deleted_at because that is what it
 * means here. Laravel's SoftDeletes does the work and takes the column name
 * from CapstoneExpert::DELETED_AT.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('capstone_experts', 'deactivated_at')) {
            return;
        }

        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dateTime('deactivated_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('capstone_experts', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};
