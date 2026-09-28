<?php

use App\Models\ConsultationBooking;
use App\Models\Notification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Settle the consultations the first pass at team_id left behind.
 *
 * A booking whose team could not be worked out stayed with the student who
 * made it, so a student who left their team still found their old team's
 * session on their new team's page. It is shown to no student now; this
 * gives more of them their team (an expert's only team counts too) and
 * calls off the upcoming ones whose team no longer exists - a leader who
 * leaves alone ends the team, and nobody is left to attend.
 */
return new class extends Migration
{
    public function up(): void
    {
        ConsultationBooking::fillMissingTeams();

        $noticed = Notification::where('type', 'consultation_booked')
            ->whereNotNull('data')
            ->get(['data'])
            ->mapWithKeys(fn ($n) => isset($n->data['booking_id'], $n->data['team_id'])
                ? [(int) $n->data['booking_id'] => (int) $n->data['team_id']]
                : [])
            ->all();

        $teams = DB::table('team')->pluck('team_id')->map(fn ($id) => (int) $id)->flip();

        $orphans = DB::table('consultation_booking')
            ->where('booking_status', 'scheduled')
            ->get(['booking_id', 'team_id', 'schedule_id'])
            ->filter(function ($booking) use ($noticed, $teams) {
                $teamId = $booking->team_id ?? $noticed[$booking->booking_id] ?? null;

                return $teamId !== null && ! $teams->has((int) $teamId);
            });

        foreach ($orphans as $booking) {
            DB::table('consultation_booking')->where('booking_id', $booking->booking_id)->update([
                'booking_status' => 'cancelled',
                'cancellation_requested_at' => null,
                'cancellation_reason' => ConsultationBooking::TEAM_REMOVED,
            ]);

            DB::table('consultation_schedule')->where('schedule_id', $booking->schedule_id)->update(['is_available' => true]);
        }
    }

    public function down(): void
    {
        // Cancelled consultations are not brought back.
    }
};
