<?php

namespace App\Mail;

use App\Models\AppSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SpeakerInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $firstName,
        public readonly string $body,
        public readonly string $link,
        public readonly string $language = 'de',
        public readonly ?string $replyToEmail = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->language === 'fr'
            ? 'Vous êtes invité(e) — ' . config('app.name')
            : 'Du bist eingeladen — ' . config('app.name');

        return new Envelope(
            subject: $subject,
            replyTo: $this->replyToEmail ? [$this->replyToEmail] : [],
        );
    }

    public function content(): Content
    {
        $eventTitle = $this->language === 'fr'
            ? AppSetting::get('event_title_fr', 'Forum des Métiers')
            : AppSetting::get('event_title_de', 'Forum der Berufe');

        $eventDatetime = AppSetting::get('event_datetime');
        $eventDate = $eventDatetime ? \Illuminate\Support\Carbon::parse($eventDatetime)->format('d.m.Y') : null;

        $logoPath = AppSetting::get('event_logo_path');
        $logoUrl = $logoPath ? url('/storage/' . $logoPath) : null;

        return new Content(
            view: 'emails.speaker-invitation',
            with: [
                'eventTitle' => $eventTitle,
                'eventDate'  => $eventDate,
                'logoUrl'    => $logoUrl,
            ],
        );
    }
}
