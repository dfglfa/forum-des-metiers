<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTagControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_list_tags_with_topic_count_and_topics(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $tag = Tag::create(['name' => 'Medicine', 'slug' => 'medicine']);
        Tag::create(['name' => 'Law', 'slug' => 'law']);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT, 'name' => 'Jane Doe']);
        Topic::create([
            'title' => 'Becoming a Doctor',
            'consultant_id' => $consultant->id,
            'tag_id' => $tag->id,
            'selected_slots' => [],
        ]);

        $response = $this->actingAs($student, 'sanctum')->getJson('/api/student/tags');

        $response->assertOk();
        $medicine = collect($response->json())->firstWhere('name', 'Medicine');
        $this->assertSame(1, $medicine['topics_count']);
        $this->assertSame('Becoming a Doctor', $medicine['topics'][0]['title']);
        $this->assertSame('Jane Doe', $medicine['topics'][0]['consultant']['name']);

        $law = collect($response->json())->firstWhere('name', 'Law');
        $this->assertSame(0, $law['topics_count']);
    }

    public function test_non_students_cannot_list_tags(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')->getJson('/api/student/tags');

        $response->assertForbidden();
    }
}
