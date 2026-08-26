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
        Schema::table('defense_panels', function (Blueprint $table) {
            // Each panel row is already a 1:1 (schedule, expert) pairing -
            // this stores that expert's own Google Calendar copy of the
            // defense event, so a connected panelist's calendar entry can
            // be found/cancelled/recreated on reschedule.
            $table->string('google_event_id')->nullable()->after('panel_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('defense_panels', function (Blueprint $table) {
            $table->dropColumn('google_event_id');
        });
    }
};
