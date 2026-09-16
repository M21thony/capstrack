<?php

namespace App\Mail;

use App\Models\CapstoneExpert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** The checklist documents still waiting for one reviewer, most urgent first. */
class ReviewReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public CapstoneExpert $expert,
        public Collection $waiting,
    ) {}

    public function envelope(): Envelope
    {
        $n = $this->waiting->count();

        return new Envelope(subject: $n.' '.($n === 1 ? 'document is' : 'documents are').' waiting for your review — CapsTrack');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.review_reminder');
    }
}
