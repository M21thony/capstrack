<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expense_posts', function (Blueprint $table) {
            // Plain string, not a DB enum - payment_for on financial_records
            // was already loosened from an enum for the same reason (avoids
            // enum-alteration friction). Valid values are validated in the
            // app layer against AdminPanelAssignmentController::DEFENSE_TYPES.
            // Nullable: an expense post isn't required to be tied to a
            // defense stage (e.g. generic materials fees).
            $table->string('defense_type')->nullable()->after('year_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_posts', function (Blueprint $table) {
            $table->dropColumn('defense_type');
        });
    }
};
