<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A team asks to cancel; the expert approves or declines. Until then the
 * booking stays on and the slot stays held.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_booking', function (Blueprint $table) {
            $table->timestamp('cancellation_requested_at')->nullable()->after('cancellation_reason');
            $table->string('cancellation_requested_by', 150)->nullable()->after('cancellation_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_booking', function (Blueprint $table) {
            $table->dropColumn(['cancellation_requested_at', 'cancellation_requested_by']);
        });
    }
};
