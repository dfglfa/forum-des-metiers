<?php

namespace Tests\Feature;

use App\Mail\SpeakerBroadcast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminSpeakerMailControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'subject_de' => 'Hallo',
            'subject_fr' => 'Bonjour',
            'body_de'    => 'Guten Tag $NAME',
            'body_fr'    => 'Bonjour $NAME',
        ];
    }

    public function test_admin_sends_personalised_mail_in_each_speakers_language(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $de = User::factory()->create(['role' => User::ROLE_CONSULTANT, 'email' => 'de@example.com']);
        $de->consultantProfile()->create(['salutation' => 'Frau', 'first_name' => 'A', 'last_name' => 'Meier', 'language' => 'de']);
        $fr = User::factory()->create(['role' => User::ROLE_CONSULTANT, 'email' => 'fr@example.com']);
        $fr->consultantProfile()->create(['salutation' => 'Herr', 'first_name' => 'B', 'last_name' => 'Dupont', 'language' => 'fr']);
        User::factory()->create(['role' => User::ROLE_STUDENT]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/speaker-mail', $this->payload())
            ->assertOk()->assertJson(['sent_count' => 2]);

        Mail::assertSent(SpeakerBroadcast::class, fn ($m) => $m->hasTo('de@example.com')
            && $m->body === 'Guten Tag Frau Meier' && $m->mailSubject === 'Hallo');
        Mail::assertSent(SpeakerBroadcast::class, fn ($m) => $m->hasTo('fr@example.com')
            && $m->body === 'Bonjour Monsieur Dupont' && $m->mailSubject === 'Bonjour');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $this->actingAs($consultant, 'sanctum')->postJson('/api/admin/speaker-mail', $this->payload())
            ->assertForbidden();
    }
}
