<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('consultation_booking', function (Blueprint $table) {
            $table->date('session_date')->nullable()->after('schedule_id');
        });

        // Backfill existing bookings: without this column, the date shown for
        // a booking was recomputed on every page load as "the next occurrence
        // of that weekday from right now" — which drifts forward every week
        // and makes old bookings appear to be for whatever date is currently
        // upcoming. Reconstruct the date each booking was actually made for,
        // anchored to when it was booked (created_at) rather than today.
        $days = ['Sunday' => 0, 'Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6];

        $bookings = DB::table('consultation_booking as b')
            ->join('consultation_schedule as s', 's.schedule_id', '=', 'b.schedule_id')
            ->whereNull('b.session_date')
            ->select('b.booking_id', 'b.created_at', 's.available_date', 's.recur', 's.specific_date')
            ->get();

        foreach ($bookings as $booking) {
            if ($booking->recur === 'once' && $booking->specific_date) {
                $sessionDate = $booking->specific_date;
            } elseif (isset($days[$booking->available_date])) {
                $createdAt = \Illuminate\Support\Carbon::parse($booking->created_at);
                $target = $days[$booking->available_date];
                $diff = ($target - $createdAt->dayOfWeek + 7) % 7;
                $sessionDate = $createdAt->copy()->addDays($diff)->toDateString();
            } else {
                continue;
            }

            DB::table('consultation_booking')
                ->where('booking_id', $booking->booking_id)
                ->update(['session_date' => $sessionDate]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consultation_booking', function (Blueprint $table) {
            $table->dropColumn('session_date');
        });
    }
};
