<?php

namespace Tests\Feature;

use App\Models\ConsultantProfile;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'salutation'            => 'Frau',
            'first_name'            => 'Jane',
            'last_name'             => 'Doe',
            'email'                 => 'jane.doe@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'language'              => 'de',
        ], $overrides);
    }

    public function test_registering_a_consultant_creates_an_active_consultant_profile(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertCreated();

        $user = User::where('email', 'jane.doe@example.com')->firstOrFail();
        $this->assertTrue($user->isConsultant());
        $this->assertNull($user->email_verified_at, 'account is not active until the email link is clicked');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password123', $user->password));

        $profile = ConsultantProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Frau', $profile->salutation);
        $this->assertSame('de', $profile->language);
        $this->assertSame('Jane', $profile->first_name);
        $this->assertSame('Doe', $profile->last_name);
    }

    public function test_registering_a_consultant_sends_the_german_verification_email_by_default(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertCreated();
        $user = User::where('email', 'jane.doe@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
            $mail = $notification->toMail($user);

            return str_contains($mail->subject, 'E-Mail-Adresse bestätigen');
        });
    }

    public function test_registering_a_consultant_in_french_sends_a_french_verification_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/register', $this->validPayload(['language' => 'fr']));

        $response->assertCreated();
        $user = User::where('email', 'jane.doe@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
            $mail = $notification->toMail($user);

            return str_contains($mail->subject, 'Confirmer votre adresse e-mail');
        });
    }

    public function test_registering_with_an_already_used_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'jane.doe@example.com', 'role' => User::ROLE_CONSULTANT]);

        $response = $this->postJson('/api/auth/register', $this->validPayload());

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_registering_with_a_password_confirmation_mismatch_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload([
            'password_confirmation' => 'something-else',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('password');
    }

    public function test_registering_with_an_invalid_language_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload(['language' => 'en']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('language');
    }

    public function test_registering_with_an_invalid_salutation_is_rejected(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload(['salutation' => 'Mister']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('salutation');
    }
}
