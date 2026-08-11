<?php

namespace App\Console\Commands;

use App\Mail\ConsultationReminderMail;
use App\Models\ConsultationBooking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails every student on the team plus the assigned expert for each
 * consultation booking scheduled for today. Meant to run once daily
 * (see routes/console.php) so it lands as a same-day reminder.
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

            $recipients = $team->members->pluck('email')->filter()->push($expert->email)->unique();

            foreach ($recipients as $email) {
                try {
                    Mail::to($email)->queue(new ConsultationReminderMail($booking, $expert, $team));
                    $sent++;
                } catch (\Exception $e) {
                    Log::warning('Consultation reminder email failed', ['error' => $e->getMessage()]);
                }
            }
        }

        $this->info("Queued {$sent} consultation reminder email(s) for ".$bookings->count().' booking(s) today.');

        return self::SUCCESS;
    }
}
