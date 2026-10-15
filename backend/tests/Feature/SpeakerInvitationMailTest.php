<?php

namespace Tests\Feature;

use App\Mail\SpeakerInvitation;
use App\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpeakerInvitationMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_german_email_has_a_language_sensitive_heading_with_the_event_title_and_date_and_no_salutation(): void
    {
        AppSetting::set('event_title_de', 'Forum der Berufe');
        AppSetting::set('event_datetime', '2026-09-04 09:00:00');

        $html = (new SpeakerInvitation('Jane', 'Please join us.', 'https://example.com/set-password', 'de'))->render();

        $this->assertStringContainsString('Einladung zum Forum der Berufe am 04.09.2026', $html);
        $this->assertStringNotContainsString('Hallo Jane,', $html);
    }

    public function test_french_email_has_a_language_sensitive_heading_with_the_event_title_and_date_and_no_salutation(): void
    {
        AppSetting::set('event_title_fr', 'Forum des Métiers');
        AppSetting::set('event_datetime', '2026-09-04 09:00:00');

        $html = (new SpeakerInvitation('Jane', 'Merci de nous rejoindre.', 'https://example.com/set-password', 'fr'))->render();

        $this->assertStringContainsString('Invitation au Forum des Métiers du 04.09.2026', $html);
        $this->assertStringNotContainsString('Bonjour Jane,', $html);
    }

    public function test_heading_omits_the_date_when_no_event_datetime_is_configured(): void
    {
        AppSetting::set('event_title_de', 'Forum der Berufe');

        $html = (new SpeakerInvitation('Jane', 'Please join us.', 'https://example.com/set-password', 'de'))->render();

        $this->assertStringContainsString('Einladung zum Forum der Berufe', $html);
        $this->assertStringNotContainsString('Einladung zum Forum der Berufe am', $html);
    }

    public function test_email_includes_the_configured_event_logo_as_an_absolute_url(): void
    {
        AppSetting::set('event_logo_path', 'event-logo/logo.png');

        $html = (new SpeakerInvitation('Jane', 'Please join us.', 'https://example.com/set-password', 'de'))->render();

        $this->assertStringContainsString('<img src="' . url('/storage/event-logo/logo.png') . '"', $html);
    }

    public function test_email_omits_the_logo_image_when_none_is_configured(): void
    {
        $html = (new SpeakerInvitation('Jane', 'Please join us.', 'https://example.com/set-password', 'de'))->render();

        $this->assertStringNotContainsString('<img', $html);
    }
}
