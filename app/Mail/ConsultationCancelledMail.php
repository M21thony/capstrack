<?php

namespace App\Mail;

use App\Models\CapstoneExpert;
use App\Models\ConsultationBooking;
use App\Models\Team;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConsultationCancelledMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public ConsultationBooking $booking,
        public CapstoneExpert $expert,
        public ?Team $team,
        public string $studentName,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Consultation Cancelled — '.($this->team->team_name ?? $this->studentName),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consultation_cancelled',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
