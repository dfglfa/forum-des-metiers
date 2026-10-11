<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEventTextsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_config_returns_the_seeded_event_description_defaults(): void
    {
        $response = $this->getJson('/api/config');

        $response->assertOk();
        $response->assertJson([
            'event_description' => [
                'de' => '',
                'fr' => '',
            ],
        ]);
    }

    public function test_admin_can_update_the_event_description_for_all_languages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/event-texts', [
            'description_de' => 'Das Forum der Berufe bringt Schüler und Referenten zusammen.',
            'description_fr' => 'Le forum des métiers réunit élèves et intervenants.',
        ]);

        $response->assertOk();
        $this->assertSame('Das Forum der Berufe bringt Schüler und Referenten zusammen.', AppSetting::get('event_description_de'));
        $this->assertSame('Le forum des métiers réunit élèves et intervenants.', AppSetting::get('event_description_fr'));

        $configResponse = $this->getJson('/api/config');
        $configResponse->assertJson([
            'event_description' => [
                'de' => 'Das Forum der Berufe bringt Schüler und Referenten zusammen.',
                'fr' => 'Le forum des métiers réunit élèves et intervenants.',
            ],
        ]);
    }

    public function test_event_description_can_be_cleared(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        AppSetting::set('event_description_de', 'Alter Text');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/event-texts', [
            'description_de' => null,
            'description_fr' => null,
        ]);

        $response->assertOk();
        $this->assertSame('', AppSetting::get('event_description_de'));
        $this->assertSame('', AppSetting::get('event_description_fr'));
    }

    public function test_updating_the_event_description_rejects_overly_long_text(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/event-texts', [
            'description_de' => str_repeat('a', 5001),
            'description_fr' => 'Kurz.',
        ]);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_update_the_event_description(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($student, 'sanctum')->postJson('/api/admin/event-texts', [
            'description_de' => 'Text',
            'description_fr' => 'Texte',
        ]);

        $response->assertForbidden();
        $this->assertSame('', AppSetting::get('event_description_de'));
    }
}
