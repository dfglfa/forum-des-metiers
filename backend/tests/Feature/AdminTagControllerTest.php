<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTagControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_tags(): void
    {
        Tag::create(['name' => 'Medicine', 'slug' => 'medicine']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/tags');

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Medicine']);
    }

    public function test_admin_can_create_a_tag_with_an_auto_generated_slug(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/tags', ['name' => 'Computer Science']);

        $response->assertCreated();
        $response->assertJsonFragment(['name' => 'Computer Science', 'slug' => 'computer-science']);
        $this->assertDatabaseHas('tags', ['name' => 'Computer Science', 'slug' => 'computer-science']);
    }

    public function test_creating_a_duplicate_tag_name_fails_validation(): void
    {
        Tag::create(['name' => 'Law', 'slug' => 'law']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/tags', ['name' => 'Law']);

        $response->assertStatus(422);
    }

    public function test_admin_can_rename_a_tag(): void
    {
        $tag = Tag::create(['name' => 'Law', 'slug' => 'law']);
        $other = Tag::create(['name' => 'Medicine', 'slug' => 'medicine']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/tags/{$tag->id}", ['name' => 'Law'])
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/tags/{$tag->id}", ['name' => 'Medicine'])
            ->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/tags/{$tag->id}", ['name' => 'Legal Studies'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Legal Studies']);
        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'Legal Studies']);
    }

    public function test_admin_can_delete_an_unused_tag(): void
    {
        $tag = Tag::create(['name' => 'Law', 'slug' => 'law']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/tags/{$tag->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_admin_cannot_delete_a_tag_assigned_to_a_topic(): void
    {
        $tag = Tag::create(['name' => 'Law', 'slug' => 'law']);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);
        Topic::create([
            'title' => 'Corporate Law',
            'consultant_id' => $consultant->id,
            'tag_id' => $tag->id,
        ]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/tags/{$tag->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }

    public function test_non_admin_cannot_create_a_tag(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/admin/tags', ['name' => 'Law']);

        $response->assertForbidden();
        $this->assertDatabaseMissing('tags', ['name' => 'Law']);
    }

    public function test_non_admin_cannot_delete_a_tag(): void
    {
        $tag = Tag::create(['name' => 'Law', 'slug' => 'law']);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($student, 'sanctum')
            ->deleteJson("/api/admin/tags/{$tag->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }
}
