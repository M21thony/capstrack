<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RequestStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $expert,
        public string $status,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->status === 'approved'
                ? 'CapsTRACK Account Approved'
                : 'CapsTRACK Account Rejection',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.request_status',
            with: array_filter([
                'reason' => $this->reason,
            ]),
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
