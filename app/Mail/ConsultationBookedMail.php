<?php

namespace App\Mail;

use App\Models\CapstoneExpert;
use App\Models\ConsultationBooking;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConsultationBookedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ConsultationBooking $booking,
        public CapstoneExpert $expert,
        public Team $team,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Consultation Booked — {$this->team->team_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consultation_booked',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
