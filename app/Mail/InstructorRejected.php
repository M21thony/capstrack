<?php

namespace App\Mail;

use App\Models\CapstoneExpert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstructorRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CapstoneExpert $expert, public ?string $reason = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'CapsTRACK Account Rejection',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.instructor-status',
            with: ['reason' => $this->reason],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
