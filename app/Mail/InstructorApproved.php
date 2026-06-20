<?php

namespace App\Mail;

use App\Models\CapstoneExpert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InstructorApproved extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CapstoneExpert $expert) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'CapsTRACK Account Approved',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.instructor-status',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
