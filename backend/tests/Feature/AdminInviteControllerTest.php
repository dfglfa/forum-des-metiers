<?php

namespace Tests\Feature;

use App\Mail\SpeakerInvitation;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminInviteControllerTest extends TestCase
{
    use RefreshDatabase;

    private function invitePayload(array $overrides = []): array
    {
        return array_merge([
            'salutation'      => 'Frau',
            'first_name'      => 'Jane',
            'last_name'       => 'Doe',
            'email'           => 'jane.doe@example.com',
            'language'        => 'de',
            'invitation_body' => 'Please join us as a speaker.',
        ], $overrides);
    }

    public function test_admin_can_invite_a_single_speaker(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/invite', $this->invitePayload());

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'email' => 'jane.doe@example.com',
            'role'  => User::ROLE_CONSULTANT,
        ]);
        $this->assertDatabaseHas('consultant_profiles', [
            'salutation' => 'Frau',
            'language'   => 'de',
            'first_name' => 'Jane',
            'last_name'  => 'Doe',
        ]);
        Mail::assertSent(SpeakerInvitation::class, 1);
    }

    public function test_single_invite_sends_the_body_with_name_placeholder_replaced_in_german(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/invite', $this->invitePayload([
            'language'        => 'de',
            'invitation_body' => 'Dear $NAME, please join us.',
        ]));

        $response->assertOk();
        Mail::assertSent(SpeakerInvitation::class, function (SpeakerInvitation $mail) {
            return $mail->body === 'Dear Frau Doe, please join us.'
                && $mail->language === 'de'
                && str_contains($mail->envelope()->subject, 'Du bist eingeladen')
                && str_contains($mail->link, '&lang=de');
        });
    }

    public function test_single_invite_sends_the_body_with_name_placeholder_replaced_in_french(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/invite', $this->invitePayload([
            'language'        => 'fr',
            'invitation_body' => 'Cher $NAME, merci de nous rejoindre.',
        ]));

        $response->assertOk();
        $this->assertDatabaseHas('consultant_profiles', ['language' => 'fr']);
        Mail::assertSent(SpeakerInvitation::class, function (SpeakerInvitation $mail) {
            return $mail->body === 'Cher Madame Doe, merci de nous rejoindre.'
                && $mail->language === 'fr'
                && str_contains($mail->envelope()->subject, 'Vous êtes invité')
                && str_contains($mail->link, '&lang=fr');
        });
    }

    public function test_single_invite_requires_a_salutation(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $payload = $this->invitePayload();
        unset($payload['salutation']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/invite', $payload);

        $response->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_single_invite_validation_errors_are_german_by_default(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $payload = $this->invitePayload();
        unset($payload['salutation']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/invite', $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment(['salutation' => ['Das Feld Anrede muss ausgefüllt werden.']]);
    }

    public function test_single_invite_validation_errors_are_french_when_requested_via_accept_language_header(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $payload = $this->invitePayload();
        unset($payload['salutation']);

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept-Language', 'fr')
            ->postJson('/api/admin/invite', $payload);

        $response->assertStatus(422);
        $response->assertJsonFragment(['salutation' => ['Le champ civilité est obligatoire.']]);
    }

    public function test_single_invite_rejects_a_salutation_not_in_the_allowed_list(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/invite', $this->invitePayload([
            'salutation' => 'Mister',
        ]));

        $response->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_single_invite_rejects_a_language_not_in_the_allowed_list(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/invite', $this->invitePayload([
            'language' => 'en',
        ]));

        $response->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_single_invite_uses_the_configured_event_manager_email_as_reply_to(): void
    {
        Mail::fake();
        AppSetting::set('event_manager_email', 'organizer@example.com');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/invite', $this->invitePayload());

        $response->assertOk();
        Mail::assertSent(SpeakerInvitation::class, function (SpeakerInvitation $mail) {
            return $mail->envelope()->hasReplyTo('organizer@example.com');
        });
    }

    public function test_single_invite_defaults_the_reply_to_when_no_event_manager_email_is_configured(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/invite', $this->invitePayload());

        $response->assertOk();
        Mail::assertSent(SpeakerInvitation::class, function (SpeakerInvitation $mail) {
            return $mail->envelope()->hasReplyTo('admin@example.com');
        });
    }

    public function test_single_invite_warns_instead_of_sending_when_the_email_was_already_invited(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'jane.doe@example.com']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/invite', $this->invitePayload());

        $response->assertOk();
        $response->assertJson(['warning' => 'Diese E-Mail-Adresse wurde bereits eingeladen.']);
        Mail::assertNothingSent();
    }

    public function test_single_invite_warning_is_returned_in_french_when_requested_via_accept_language_header(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'jane.doe@example.com']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeader('Accept-Language', 'fr')
            ->postJson('/api/admin/invite', $this->invitePayload());

        $response->assertOk();
        $response->assertJson(['warning' => 'Cette adresse e-mail a déjà été invitée.']);
        Mail::assertNothingSent();
    }

    public function test_non_admin_cannot_invite_a_speaker(): void
    {
        Mail::fake();
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($student, 'sanctum')->postJson('/api/admin/invite', $this->invitePayload());

        $response->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_admin_can_bulk_invite_speakers_from_a_csv(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "salutation,firstname,lastname,email,language\n"
            . "Frau,Jane,Doe,jane.doe@example.com,de\n"
            . "Herr,John,Smith,john.smith@example.com,fr\n";
        $file = UploadedFile::fake()->createWithContent('speakers.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/invite/bulk', [
            'csv'                => $file,
            'invitation_body_de' => 'Please join us as a speaker.',
            'invitation_body_fr' => 'Merci de nous rejoindre en tant que conférencier.',
        ]);

        $response->assertOk();
        $response->assertJson([
            'invited_count' => 2,
            'invited'       => ['jane.doe@example.com', 'john.smith@example.com'],
            'skipped'       => [],
        ]);
        $this->assertDatabaseHas('users', ['email' => 'jane.doe@example.com', 'role' => User::ROLE_CONSULTANT]);
        $this->assertDatabaseHas('consultant_profiles', ['salutation' => 'Frau', 'first_name' => 'Jane', 'language' => 'de']);
        $this->assertDatabaseHas('users', ['email' => 'john.smith@example.com', 'role' => User::ROLE_CONSULTANT]);
        $this->assertDatabaseHas('consultant_profiles', ['salutation' => 'Herr', 'first_name' => 'John', 'language' => 'fr']);
        Mail::assertSent(SpeakerInvitation::class, 2);
    }

    public function test_bulk_invite_defaults_the_language_to_de_when_the_column_is_missing_or_blank(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "salutation,firstname,lastname,email,language\n"
            . "Frau,Jane,Doe,jane.doe@example.com,\n"
            . "Herr,John,Smith,john.smith@example.com\n"; // row shorter than the header — no language cell at all
        $file = UploadedFile::fake()->createWithContent('speakers.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/invite/bulk', [
            'csv'                => $file,
            'invitation_body_de' => 'Please join us as a speaker.',
            'invitation_body_fr' => 'Merci de nous rejoindre en tant que conférencier.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('invited_count', 2);
        $this->assertDatabaseHas('consultant_profiles', ['first_name' => 'Jane', 'language' => 'de']);
        $this->assertDatabaseHas('consultant_profiles', ['first_name' => 'John', 'language' => 'de']);
    }

    public function test_bulk_invite_uses_the_template_matching_each_rows_language(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "salutation,firstname,lastname,email,language\n"
            . "Frau,Jane,Doe,jane.doe@example.com,de\n"
            . "Herr,John,Smith,john.smith@example.com,fr\n";
        $file = UploadedFile::fake()->createWithContent('speakers.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/invite/bulk', [
            'csv'                => $file,
            'invitation_body_de' => 'Dear $NAME, please join us.',
            'invitation_body_fr' => 'Cher $NAME, merci de nous rejoindre.',
        ]);

        $response->assertOk();
        Mail::assertSent(SpeakerInvitation::class, function (SpeakerInvitation $mail) {
            return $mail->firstName === 'Jane' && $mail->body === 'Dear Frau Doe, please join us.';
        });
        Mail::assertSent(SpeakerInvitation::class, function (SpeakerInvitation $mail) {
            return $mail->firstName === 'John' && $mail->body === 'Cher Monsieur Smith, merci de nous rejoindre.';
        });
    }

    public function test_bulk_invite_skips_rows_with_invalid_or_duplicate_emails(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'existing@example.com']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "salutation,firstname,lastname,email,language\n"
            . "Frau,Jane,Doe,jane.doe@example.com,de\n"
            . "Herr,Bad,Row,not-an-email,de\n"
            . "Herr,Already,Registered,existing@example.com,de\n"
            . "Frau,Jane,Doe,jane.doe@example.com,de\n"; // duplicate within the same file
        $file = UploadedFile::fake()->createWithContent('speakers.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/invite/bulk', [
            'csv'                => $file,
            'invitation_body_de' => 'Please join us as a speaker.',
            'invitation_body_fr' => 'Merci de nous rejoindre en tant que conférencier.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('invited_count', 1);
        $response->assertJsonPath('invited', ['jane.doe@example.com']);
        $this->assertCount(3, $response->json('skipped'));
        Mail::assertSent(SpeakerInvitation::class, 1);
    }

    public function test_bulk_invite_gives_a_warning_reason_for_already_invited_emails_instead_of_sending(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'existing@example.com']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "salutation,firstname,lastname,email,language\n"
            . "Frau,Jane,Doe,jane.doe@example.com,de\n"
            . "Herr,Already,Registered,existing@example.com,de\n"
            . "Frau,Jane,Doe,jane.doe@example.com,de\n"; // duplicate within the same file
        $file = UploadedFile::fake()->createWithContent('speakers.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/invite/bulk', [
            'csv'                => $file,
            'invitation_body_de' => 'Please join us as a speaker.',
            'invitation_body_fr' => 'Merci de nous rejoindre en tant que conférencier.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('invited_count', 1);
        $skipped = $response->json('skipped');
        $this->assertCount(2, $skipped);
        foreach ($skipped as $row) {
            $this->assertSame('Diese E-Mail-Adresse wurde bereits eingeladen.', $row['reason']);
        }
        Mail::assertSent(SpeakerInvitation::class, 1);
    }

    public function test_bulk_invite_skips_rows_missing_a_salutation(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "salutation,firstname,lastname,email,language\n"
            . ",Jane,Doe,jane.doe@example.com,de\n"
            . "Herr,John,Smith,john.smith@example.com,de\n";
        $file = UploadedFile::fake()->createWithContent('speakers.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/invite/bulk', [
            'csv'                => $file,
            'invitation_body_de' => 'Please join us as a speaker.',
            'invitation_body_fr' => 'Merci de nous rejoindre en tant que conférencier.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('invited_count', 1);
        $response->assertJsonPath('invited', ['john.smith@example.com']);
        $this->assertCount(1, $response->json('skipped'));
    }

    public function test_bulk_invite_replaces_the_name_placeholder_per_row(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "salutation,firstname,lastname,email,language\n"
            . "Frau,Jane,Doe,jane.doe@example.com,de\n"
            . "(ohne),John,Smith,john.smith@example.com,de\n";
        $file = UploadedFile::fake()->createWithContent('speakers.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/invite/bulk', [
            'csv'                => $file,
            'invitation_body_de' => 'Dear $NAME, please join us.',
            'invitation_body_fr' => 'Cher $NAME, merci de nous rejoindre.',
        ]);

        $response->assertOk();
        Mail::assertSent(SpeakerInvitation::class, function (SpeakerInvitation $mail) {
            return $mail->firstName === 'Jane' && $mail->body === 'Dear Frau Doe, please join us.';
        });
        Mail::assertSent(SpeakerInvitation::class, function (SpeakerInvitation $mail) {
            return $mail->firstName === 'John' && $mail->body === 'Dear Smith, please join us.';
        });
    }

    public function test_bulk_invite_skips_rows_with_a_salutation_not_in_the_allowed_list(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "salutation,firstname,lastname,email,language\n"
            . "Mister,Jane,Doe,jane.doe@example.com,de\n"
            . "Herr,John,Smith,john.smith@example.com,de\n";
        $file = UploadedFile::fake()->createWithContent('speakers.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/invite/bulk', [
            'csv'                => $file,
            'invitation_body_de' => 'Please join us as a speaker.',
            'invitation_body_fr' => 'Merci de nous rejoindre en tant que conférencier.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('invited_count', 1);
        $response->assertJsonPath('invited', ['john.smith@example.com']);
        $this->assertCount(1, $response->json('skipped'));
    }

    public function test_bulk_invite_skips_blank_lines(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "salutation,firstname,lastname,email,language\n"
            . "Frau,Jane,Doe,jane.doe@example.com,de\n"
            . "\n"
            . "Herr,John,Smith,john.smith@example.com,de\n";
        $file = UploadedFile::fake()->createWithContent('speakers.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/invite/bulk', [
            'csv'                => $file,
            'invitation_body_de' => 'Please join us as a speaker.',
            'invitation_body_fr' => 'Merci de nous rejoindre en tant que conférencier.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('invited_count', 2);
    }

    public function test_non_admin_cannot_bulk_invite_speakers(): void
    {
        Mail::fake();
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $csv = "salutation,firstname,lastname,email,language\nFrau,Jane,Doe,jane.doe@example.com,de\n";
        $file = UploadedFile::fake()->createWithContent('speakers.csv', $csv);

        $response = $this->actingAs($student, 'sanctum')->post('/api/admin/invite/bulk', [
            'csv'                => $file,
            'invitation_body_de' => 'Please join us as a speaker.',
            'invitation_body_fr' => 'Merci de nous rejoindre en tant que conférencier.',
        ]);

        $response->assertForbidden();
        Mail::assertNothingSent();
    }
}
