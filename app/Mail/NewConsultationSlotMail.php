<?php

namespace App\Mail;

use App\Models\CapstoneExpert;
use App\Models\ConsultationSchedule;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewConsultationSlotMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public ConsultationSchedule $slot,
        public CapstoneExpert $expert,
        public Team $team,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Consultation Slot Available — '.trim("{$this->expert->first_name} {$this->expert->last_name}"),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consultation_slot_available',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
