<?php

namespace App\Console\Commands;

use App\Mail\ConsultationReminderMail;
use App\Models\ConsultationBooking;
use App\Services\Notifier;
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

            $when = $booking->session_date->format('D, j M Y');

            Notifier::toExpert(
                $expert,
                'consultation_reminder',
                'Consultation today',
                'You are seeing <strong>'.e($team->team_name).'</strong> today, '.$when.'.',
                route('instructor.schedule'),
                ['booking_id' => $booking->booking_id],
            );

            Notifier::toStudents(
                $team->members,
                'consultation_reminder',
                'Consultation today',
                'Your consultation with <strong>'.e(trim($expert->first_name.' '.$expert->last_name))
                    .'</strong> is today, '.$when.'.',
                route('student.consultation'),
                ['booking_id' => $booking->booking_id],
            );

            // One email per session - to the expert, copied to the team. It used
            // to send a separate copy to every member, which read as spam.
            // Anyone who has turned this email off is left out of the copy
            // list; they still get the desktop notification above.
            $members = $team->members
                ->filter(fn ($m) => $m->wants('consultation_reminder', 'email'))
                ->pluck('email')->filter()->reject(fn ($e) => $e === $expert->email)->unique()->values();

            try {
                $mail = new ConsultationReminderMail($booking, $expert, $team);

                if ($expert->wants('consultation_reminder', 'email')) {
                    Mail::to($expert->email)->cc($members->all())->queue($mail);
                } elseif ($members->isNotEmpty()) {
                    Mail::to($members->all())->queue($mail);
                } else {
                    continue;
                }
                $sent++;
            } catch (\Exception $e) {
                Log::warning('Consultation reminder email failed', ['error' => $e->getMessage()]);
            }
        }

        $this->info("Queued {$sent} consultation reminder email(s) for ".$bookings->count().' booking(s) today.');

        return self::SUCCESS;
    }
}
