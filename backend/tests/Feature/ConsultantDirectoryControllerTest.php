<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultantDirectoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_participating_consultants_with_limited_fields(): void
    {
        $viewer = User::factory()->create(['role' => User::ROLE_CONSULTANT]);
        $absent = User::factory()->create(['role' => User::ROLE_CONSULTANT, 'is_participating' => false, 'name' => 'Absent']);
        $viewer->consultantProfile()->create(['first_name' => 'A', 'last_name' => 'B', 'graduation_year' => 2005, 'career_path' => 'secret']);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/consultant-directory');

        $response->assertOk()->assertJsonCount(1);
        $this->assertSame(['id', 'name', 'graduation_year', 'profile_picture_url'], array_keys($response->json(0)));
        $this->assertSame(2005, $response->json('0.graduation_year'));
        $this->assertNotContains($absent->id, array_column($response->json(), 'id'));
    }

    public function test_sorted_by_last_then_first_name_and_open_to_students(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $medicine = Tag::create(['name' => 'Medicine', 'slug' => 'medicine']);
        $art = Tag::create(['name' => 'Art', 'slug' => 'art']);

        foreach ([['Zed', 'Adams', $medicine, 'Frau'], ['Amy', 'Brown', $medicine, 'Herr'], ['Bob', 'Brown', $medicine, 'Herr'], ['Cy', 'Zulu', $art, '(ohne)']] as [$first, $last, $tag, $sal]) {
            $u = User::factory()->create(['role' => User::ROLE_CONSULTANT]);
            $u->consultantProfile()->create(['first_name' => $first, 'last_name' => $last, 'salutation' => $sal]);
            Topic::create(['title' => 'T', 'consultant_id' => $u->id, 'tag_id' => $tag->id, 'selected_slots' => []]);
        }

        $response = $this->actingAs($student, 'sanctum')->getJson('/api/consultant-directory');

        $response->assertOk();
        $this->assertSame(['Zed Adams', 'Amy Brown', 'Bob Brown', 'Cy Zulu'], array_column($response->json(), 'name'));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/consultant-directory')->assertUnauthorized();
    }
}
