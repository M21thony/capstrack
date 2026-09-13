<?php

namespace App\Mail;

use App\Models\DefenseSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A team's defense has a date - or a new one. Sent to the team's members and
 * to everyone on its panel.
 */
class DefenseScheduledMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public DefenseSchedule $schedule,
        public bool $rescheduled,
        public bool $forPanelist,
    ) {}

    public function envelope(): Envelope
    {
        $team = $this->schedule->team?->team_name ?? 'Your team';

        return new Envelope(
            subject: ($this->rescheduled ? 'Defense Rescheduled' : 'Defense Scheduled')
                ." — {$this->schedule->defense_type}, {$team}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.defense_scheduled');
    }
}
