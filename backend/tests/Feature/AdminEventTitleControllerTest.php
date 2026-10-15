<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEventTitleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_config_returns_the_seeded_event_title_defaults(): void
    {
        $response = $this->getJson('/api/config');

        $response->assertOk();
        $response->assertJson([
            'event_title' => [
                'de' => 'Forum der Berufe',
                'fr' => 'Forum des Métiers',
            ],
        ]);
    }

    public function test_admin_can_update_the_event_title_for_all_languages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/event-title', [
            'de' => 'Berufstag',
            'fr' => 'Journée des Métiers',
        ]);

        $response->assertOk();
        $this->assertSame('Berufstag', AppSetting::get('event_title_de'));
        $this->assertSame('Journée des Métiers', AppSetting::get('event_title_fr'));

        $configResponse = $this->getJson('/api/config');
        $configResponse->assertJson([
            'event_title' => [
                'de' => 'Berufstag',
                'fr' => 'Journée des Métiers',
            ],
        ]);
    }

    public function test_updating_the_event_title_requires_both_languages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/event-title', [
            'de' => 'Berufstag',
        ]);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_update_the_event_title(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($student, 'sanctum')->postJson('/api/admin/event-title', [
            'de' => 'Berufstag',
            'fr' => 'Journée des Métiers',
        ]);

        $response->assertForbidden();
        $this->assertSame('Forum der Berufe', AppSetting::get('event_title_de'));
    }
}
