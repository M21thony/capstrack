<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The email copy of a bell notification.
 *
 * One generic mail for every important notification: the bell already holds a
 * title, a message and a link, so the email is just those, dressed up. Events
 * with a richer email of their own (a consultation, a defense schedule) keep
 * theirs - see NotificationTypes::OWN_EMAIL.
 */
class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $headline,
        public ?string $body,
        public ?string $url,
    ) {
        // Held until the surrounding transaction commits, so a notification
        // that was rolled back with the action it described is never emailed.
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'CapsTrack: '.$this->headline);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.notification');
    }

    public function attachments(): array
    {
        return [];
    }
}
