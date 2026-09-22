<?php

namespace App\Jobs;

use App\Mail\ConsultationCancelledMail;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSchedule;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tell the expert, and tidy Google Calendar, after a consultation is cancelled.
 *
 * Both of these used to run inside the request. Sending the mail waits on an
 * SMTP round trip and the calendar call waits on Google, so cancelling sat
 * there for about three seconds before the page came back - for work the
 * student is not waiting on and cannot see the result of.
 *
 * The booking is already cancelled and the slot already freed by the time
 * this runs; nothing here decides anything, it only announces it.
 */
class SyncConsultationCancellation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $bookingId,
        public string $reason,
        public string $studentName,
    ) {}

    public function handle(GoogleCalendarService $calendar): void
    {
        $booking = ConsultationBooking::find($this->bookingId);

        if (! $booking) {
            return;
        }

        $slot = ConsultationSchedule::find($booking->schedule_id);
        $expert = $slot?->expert;

        if (! $expert) {
            return;
        }

        try {
            // The expert approved this cancellation themselves, so the only
            // thing left to do is send the copy for their records - and only
            // if they still want it.
            if ($expert->wants('consultation_cancelled', 'email')) {
                Mail::to($expert->email)->send(
                    new ConsultationCancelledMail($booking, $expert, $booking->team, $this->studentName, $this->reason)
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Consultation cancelled email failed', ['error' => $e->getMessage()]);
        }

        if (! $expert->google_token || ! $booking->google_event_id) {
            return;
        }

        try {
            // Every booking has an event of its own on the expert's calendar,
            // and open slots have none, so a cancelled booking's event is simply
            // removed - nothing is put back in its place.
            $calendar->cancelBookingEvent($slot->expert_id, $booking->google_event_id);
        } catch (\Throwable $e) {
            Log::warning('Google Calendar sync failed on cancel', ['error' => $e->getMessage()]);
        }
    }
}
