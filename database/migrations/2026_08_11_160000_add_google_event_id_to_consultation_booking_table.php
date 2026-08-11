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
        Schema::table('consultation_booking', function (Blueprint $table) {
            // Recurring slots share a single consultation_schedule row (and a
            // single placeholder Google Calendar event) across every future
            // weekday occurrence, so a booking needs its own event id to get
            // a calendar entry on the actual date it was booked for, instead
            // of every occurrence fighting over one event pinned to whichever
            // date the slot happened to be created on.
            $table->string('google_event_id')->nullable()->after('booking_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultation_booking', function (Blueprint $table) {
            $table->dropColumn('google_event_id');
        });
    }
};
