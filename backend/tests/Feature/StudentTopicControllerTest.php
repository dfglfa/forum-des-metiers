<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTopicControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_list_topics_with_tag_and_consultant_profile(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $tag = Tag::create(['name' => 'Medicine', 'slug' => 'medicine']);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);
        $consultant->consultantProfile()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        Topic::create([
            'title' => 'Becoming a Doctor',
            'consultant_id' => $consultant->id,
            'tag_id' => $tag->id,
            'selected_slots' => [],
        ]);

        $response = $this->actingAs($student, 'sanctum')->getJson('/api/student/topics');

        $response->assertOk();
        $topic = collect($response->json())->firstWhere('title', 'Becoming a Doctor');
        $this->assertSame('Medicine', $topic['tag']['name']);
        $this->assertSame('Jane', $topic['consultant']['consultant_profile']['first_name']);
    }

    public function test_a_non_participating_consultants_topic_is_excluded_from_the_listing(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $tag = Tag::create(['name' => 'Medicine', 'slug' => 'medicine']);

        $sickConsultant = User::factory()->create(['role' => User::ROLE_CONSULTANT, 'is_participating' => false]);
        Topic::create([
            'title' => 'Becoming a Doctor',
            'consultant_id' => $sickConsultant->id,
            'tag_id' => $tag->id,
            'selected_slots' => [],
        ]);

        $activeConsultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);
        Topic::create([
            'title' => 'Becoming a Nurse',
            'consultant_id' => $activeConsultant->id,
            'tag_id' => $tag->id,
            'selected_slots' => [],
        ]);

        $response = $this->actingAs($student, 'sanctum')->getJson('/api/student/topics');

        $response->assertOk();
        $titles = collect($response->json())->pluck('title')->all();
        $this->assertContains('Becoming a Nurse', $titles);
        $this->assertNotContains('Becoming a Doctor', $titles);
    }

    public function test_non_students_cannot_list_topics(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')->getJson('/api/student/topics');

        $response->assertForbidden();
    }
}
