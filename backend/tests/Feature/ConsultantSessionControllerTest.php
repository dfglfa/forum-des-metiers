<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\SlotOption;
use App\Models\TimeSlot;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultantSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        // Seeded by the create_slot_options_table migration itself.
        $slot = SlotOption::where('kind', SlotOption::KIND_PRESENTATION)->firstOrFail();

        return array_merge([
            'title' => 'Corporate Law',
            'description' => 'A short description of the session.',
            'selected_slots' => ["in_person_{$slot->id}"],
        ], $overrides);
    }

    public function test_consultant_can_save_a_description_up_to_200_characters(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')
            ->postJson('/api/consultant/session', $this->payload(['description' => str_repeat('a', 200)]));

        $response->assertOk();
        $this->assertDatabaseHas('topics', [
            'consultant_id' => $consultant->id,
            'description' => str_repeat('a', 200),
        ]);
    }

    public function test_description_longer_than_200_characters_fails_validation(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')
            ->postJson('/api/consultant/session', $this->payload(['description' => str_repeat('a', 201)]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['description']);
    }

    public function test_session_cannot_be_updated_during_the_conference_phase(): void
    {
        AppSetting::set('current_phase', 'conference');
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')
            ->postJson('/api/consultant/session', $this->payload());

        $response->assertStatus(403);
        $this->assertDatabaseMissing('topics', ['consultant_id' => $consultant->id]);
    }

    public function test_session_can_be_updated_during_the_selection_phase(): void
    {
        AppSetting::set('current_phase', 'selection');
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')
            ->postJson('/api/consultant/session', $this->payload());

        $response->assertOk();
        $this->assertDatabaseHas('topics', ['consultant_id' => $consultant->id]);
    }

    public function test_video_participation_slots_are_no_longer_accepted(): void
    {
        $slot = SlotOption::where('kind', SlotOption::KIND_PRESENTATION)->firstOrFail();
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')
            ->postJson('/api/consultant/session', $this->payload(['selected_slots' => ["video_{$slot->id}"]]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['selected_slots.0']);
        $this->assertDatabaseMissing('topics', ['consultant_id' => $consultant->id]);
    }

    public function test_session_show_includes_the_assigned_time_slots_with_room(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);
        $topic = Topic::create([
            'title' => 'Corporate Law',
            'consultant_id' => $consultant->id,
            'selected_slots' => [],
        ]);
        TimeSlot::create([
            'topic_id' => $topic->id,
            'consultant_id' => $consultant->id,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'room' => 'R101',
            'capacity' => 20,
        ]);

        $response = $this->actingAs($consultant, 'sanctum')->getJson('/api/consultant/session');

        $response->assertOk();
        $response->assertJsonFragment(['room' => 'R101']);
    }
}
