<?php

namespace Tests\Feature;

use App\Models\SlotOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSlotOptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_option_list_is_publicly_readable_and_seeded_with_defaults(): void
    {
        // Seeded by the create_slot_options_table migration itself.
        $response = $this->getJson('/api/slot-options');

        $response->assertOk();
        $response->assertJsonCount(5);
        $response->assertJsonFragment(['kind' => 'presentation', 'start_time' => '13:30', 'end_time' => '14:20']);
        $response->assertJsonFragment(['kind' => 'reception', 'start_time' => '17:45', 'end_time' => '18:30']);
    }

    public function test_admin_can_create_a_slot_option(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/slot-options', [
            'kind' => 'presentation',
            'start_time' => '18:00',
            'end_time' => '18:50',
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['kind' => 'presentation', 'start_time' => '18:00', 'end_time' => '18:50']);
        $this->assertDatabaseHas('slot_options', ['start_time' => '18:00', 'end_time' => '18:50']);
    }

    public function test_creating_a_slot_option_rejects_an_end_time_before_the_start_time(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/slot-options', [
            'kind' => 'presentation',
            'start_time' => '14:00',
            'end_time' => '13:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_creating_a_slot_option_rejects_an_invalid_kind(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/slot-options', [
            'kind' => 'lunch',
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_update_a_slot_option(): void
    {
        $slot = SlotOption::create(['kind' => 'presentation', 'start_time' => '10:00', 'end_time' => '10:50']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/admin/slot-options/{$slot->id}", [
            'kind' => 'presentation',
            'start_time' => '11:00',
            'end_time' => '11:50',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('slot_options', ['id' => $slot->id, 'start_time' => '11:00', 'end_time' => '11:50']);
    }

    public function test_admin_can_delete_a_slot_option(): void
    {
        $slot = SlotOption::create(['kind' => 'presentation', 'start_time' => '10:00', 'end_time' => '10:50']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/slot-options/{$slot->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('slot_options', ['id' => $slot->id]);
    }

    public function test_non_admin_cannot_create_a_slot_option(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($student, 'sanctum')->postJson('/api/admin/slot-options', [
            'kind' => 'presentation',
            'start_time' => '18:00',
            'end_time' => '18:50',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('slot_options', ['start_time' => '18:00']);
    }

    public function test_non_admin_cannot_delete_a_slot_option(): void
    {
        $slot = SlotOption::create(['kind' => 'presentation', 'start_time' => '10:00', 'end_time' => '10:50']);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($student, 'sanctum')->deleteJson("/api/admin/slot-options/{$slot->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('slot_options', ['id' => $slot->id]);
    }

    public function test_valid_slot_ids_cover_presentation_slots_once_in_person_only_and_reception_slots_once(): void
    {
        SlotOption::query()->delete();
        $presentation = SlotOption::create(['kind' => 'presentation', 'start_time' => '10:00', 'end_time' => '10:50']);
        $reception = SlotOption::create(['kind' => 'reception', 'start_time' => '17:00', 'end_time' => '18:00']);

        $ids = SlotOption::validSlotIds();

        $this->assertContains("in_person_{$presentation->id}", $ids);
        $this->assertNotContains("video_{$presentation->id}", $ids);
        $this->assertContains("reception_{$reception->id}", $ids);
        $this->assertCount(2, $ids);
    }
}
