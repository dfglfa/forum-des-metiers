<?php

namespace App\Mail;

use App\Models\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SpeakerBroadcast extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $mailSubject,
        public readonly string $body,
        public readonly string $language = 'de',
        public readonly ?string $replyToEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
            replyTo: $this->replyToEmail ? [$this->replyToEmail] : [],
        );
    }

    public function content(): Content
    {
        $eventTitle = $this->language === 'fr'
            ? AppSetting::get('event_title_fr', 'Forum des Métiers')
            : AppSetting::get('event_title_de', 'Forum der Berufe');

        $logoPath = AppSetting::get('event_logo_path');

        return new Content(
            view: 'emails.speaker-broadcast',
            with: [
                'eventTitle' => $eventTitle,
                'logoUrl'    => $logoPath ? url('/storage/' . $logoPath) : null,
            ],
        );
    }
}
