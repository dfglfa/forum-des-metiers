<?php

namespace Tests\Feature;

use App\Models\ConsultantProfile;
use App\Models\Tag;
use App\Models\TimeSlot;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultants_list_includes_each_speakers_topic_tag(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $tag = Tag::create(['name' => 'Medicine', 'slug' => 'medicine']);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);
        Topic::create([
            'title' => 'Becoming a Doctor',
            'consultant_id' => $consultant->id,
            'tag_id' => $tag->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/consultants');

        $response->assertOk();
        $entry = collect($response->json())->firstWhere('id', $consultant->id);
        $this->assertSame('Medicine', $entry['topics'][0]['tag']['name']);
    }

    public function test_consultants_list_sorts_pending_speakers_first_then_by_creation_date(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $oldActivated = User::factory()->create([
            'role' => User::ROLE_CONSULTANT,
            'name' => 'Old Activated',
            'created_at' => now()->subDays(5),
        ]);
        $newPending = User::factory()->unverified()->create([
            'role' => User::ROLE_CONSULTANT,
            'name' => 'New Pending',
            'created_at' => now()->subDays(1),
        ]);
        $oldPending = User::factory()->unverified()->create([
            'role' => User::ROLE_CONSULTANT,
            'name' => 'Old Pending',
            'created_at' => now()->subDays(3),
        ]);
        $newActivated = User::factory()->create([
            'role' => User::ROLE_CONSULTANT,
            'name' => 'New Activated',
            'created_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/consultants');

        $response->assertOk();
        $names = array_column($response->json(), 'name');

        $this->assertSame([
            $oldPending->name,
            $newPending->name,
            $oldActivated->name,
            $newActivated->name,
        ], $names);
    }

    public function test_non_admin_cannot_list_consultants(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($student, 'sanctum')->getJson('/api/admin/consultants');

        $response->assertForbidden();
    }

    public function test_admin_can_delete_a_consultant_and_its_profile_topic_and_time_slots_are_removed(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $tag = Tag::create(['name' => 'Medicine', 'slug' => 'medicine']);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);
        $profile = ConsultantProfile::create(['user_id' => $consultant->id, 'language' => 'de']);
        $topic = Topic::create([
            'title' => 'Becoming a Doctor',
            'consultant_id' => $consultant->id,
            'tag_id' => $tag->id,
        ]);
        $timeSlot = TimeSlot::create([
            'topic_id' => $topic->id,
            'consultant_id' => $consultant->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/consultants/{$consultant->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $consultant->id]);
        $this->assertDatabaseMissing('consultant_profiles', ['id' => $profile->id]);
        $this->assertDatabaseMissing('topics', ['id' => $topic->id]);
        $this->assertDatabaseMissing('time_slots', ['id' => $timeSlot->id]);
        // The tag itself is unrelated to the consultant and must survive the deletion.
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }

    public function test_deleting_a_consultant_without_a_profile_or_topic_still_succeeds(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/consultants/{$consultant->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $consultant->id]);
    }

    public function test_non_admin_cannot_delete_a_consultant(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($student, 'sanctum')->deleteJson("/api/admin/consultants/{$consultant->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $consultant->id]);
    }

    public function test_deleting_a_non_consultant_user_via_the_consultant_endpoint_returns_404(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/consultants/{$student->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $student->id]);
    }
}
