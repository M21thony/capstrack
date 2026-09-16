<?php

namespace App\Console\Commands;

use App\Mail\ConsultationReminderMail;
use App\Models\ConsultationBooking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * One reminder email per consultation booked for today - to the expert, with
 * the team copied in. Meant to run once daily (see routes/console.php) so it
 * lands as a same-day reminder.
 */
class SendConsultationReminders extends Command
{
    protected $signature = 'consultations:send-reminders';

    protected $description = 'Email a same-day reminder for every consultation booking scheduled for today';

    public function handle(): int
    {
        ConsultationBooking::markPastAsDone();

        $bookings = ConsultationBooking::where('booking_status', 'scheduled')
            ->whereDate('session_date', today())
            ->with(['schedule.expert'])
            ->get();

        $sent = 0;

        foreach ($bookings as $booking) {
            $expert = $booking->schedule?->expert;
            $team = $booking->team;

            if (! $expert || ! $team) {
                continue;
            }

            // One email per session - to the expert, copied to the team. It used
            // to send a separate copy to every member, which read as spam.
            $members = $team->members->pluck('email')->filter()->reject(fn ($e) => $e === $expert->email)->unique()->values();

            try {
                Mail::to($expert->email)->cc($members->all())->queue(new ConsultationReminderMail($booking, $expert, $team));
                $sent++;
            } catch (\Exception $e) {
                Log::warning('Consultation reminder email failed', ['error' => $e->getMessage()]);
            }
        }

        $this->info("Queued {$sent} consultation reminder email(s) for ".$bookings->count().' booking(s) today.');

        return self::SUCCESS;
    }
}
