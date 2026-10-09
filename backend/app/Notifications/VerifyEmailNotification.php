<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Replaces Laravel's default English verification email with a German or
 * French one, depending on the language the speaker chose while
 * self-registering (see RegisterController). Falls back to German when no
 * language is known (e.g. an older account without a consultant profile).
 */
class VerifyEmailNotification extends BaseVerifyEmail
{
    public function __construct(private readonly string $language = 'de')
    {
    }

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        if ($this->language === 'fr') {
            return (new MailMessage)
                ->subject('Confirmer votre adresse e-mail — ' . config('app.name'))
                ->greeting('Bonjour,')
                ->line('Veuillez cliquer sur le bouton ci-dessous pour confirmer votre adresse e-mail.')
                ->action('Confirmer l\'adresse e-mail', $verificationUrl)
                ->line('Si vous n\'avez pas créé de compte, vous pouvez ignorer cet e-mail.');
        }

        return (new MailMessage)
            ->subject('E-Mail-Adresse bestätigen — ' . config('app.name'))
            ->greeting('Hallo,')
            ->line('Bitte klicke auf die Schaltfläche unten, um deine E-Mail-Adresse zu bestätigen.')
            ->action('E-Mail-Adresse bestätigen', $verificationUrl)
            ->line('Falls du kein Konto erstellt hast, kannst du diese E-Mail ignorieren.');
    }
}
