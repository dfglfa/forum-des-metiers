<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewRegistrationAdminNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $speakerEmail,
        public readonly int $totalSpeakers,
        public readonly int $confirmedSpeakers,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Neue Registrierung');
    }

    public function content(): Content
    {
        return new Content(text: 'emails.new-registration-admin-notice');
    }
}
