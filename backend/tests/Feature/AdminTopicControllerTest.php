<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTopicControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createTopic(?Tag $tag = null): Topic
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        return Topic::create([
            'title' => 'Corporate Law',
            'consultant_id' => $consultant->id,
            'tag_id' => $tag?->id,
        ]);
    }

    public function test_admin_can_set_the_tag_of_a_topic(): void
    {
        $topic = $this->createTopic();
        $tag = Tag::create(['name' => 'Law', 'slug' => 'law']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/topics/{$topic->id}/tag", ['tag_id' => $tag->id]);

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Law']);
        $this->assertDatabaseHas('topics', ['id' => $topic->id, 'tag_id' => $tag->id]);
    }

    public function test_admin_can_remove_the_tag_of_a_topic(): void
    {
        $tag = Tag::create(['name' => 'Law', 'slug' => 'law']);
        $topic = $this->createTopic($tag);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/topics/{$topic->id}/tag", ['tag_id' => null]);

        $response->assertOk();
        $this->assertDatabaseHas('topics', ['id' => $topic->id, 'tag_id' => null]);
    }

    public function test_admin_can_change_an_existing_tag(): void
    {
        $oldTag = Tag::create(['name' => 'Law', 'slug' => 'law']);
        $newTag = Tag::create(['name' => 'Medicine', 'slug' => 'medicine']);
        $topic = $this->createTopic($oldTag);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/topics/{$topic->id}/tag", ['tag_id' => $newTag->id]);

        $response->assertOk();
        $this->assertDatabaseHas('topics', ['id' => $topic->id, 'tag_id' => $newTag->id]);
    }

    public function test_updating_with_a_nonexistent_tag_id_fails_validation(): void
    {
        $topic = $this->createTopic();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/topics/{$topic->id}/tag", ['tag_id' => 999999]);

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_change_a_topic_tag(): void
    {
        $topic = $this->createTopic();
        $tag = Tag::create(['name' => 'Law', 'slug' => 'law']);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson("/api/admin/topics/{$topic->id}/tag", ['tag_id' => $tag->id]);

        $response->assertForbidden();
        $this->assertDatabaseHas('topics', ['id' => $topic->id, 'tag_id' => null]);
    }
}
