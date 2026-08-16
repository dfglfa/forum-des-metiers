<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSelectionControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeTags(int $count): array
    {
        static $sequence = 0;

        return collect(range(1, $count))->map(function () use (&$sequence) {
            $sequence++;

            return Tag::create(['name' => "Tag {$sequence}", 'slug' => "tag-{$sequence}"])->id;
        })->all();
    }

    public function test_student_can_save_a_selection_of_one_to_six_tags(): void
    {
        AppSetting::set('current_phase', 'selection');
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $tagIds = $this->makeTags(5);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/student/selection', ['tag_ids' => $tagIds]);

        $response->assertOk();
        $response->assertJson(['tag_ids' => $tagIds]);
        foreach ($tagIds as $index => $tagId) {
            $this->assertDatabaseHas('student_tag_preferences', [
                'student_id' => $student->id,
                'tag_id' => $tagId,
                'priority' => $index + 1,
            ]);
        }
    }

    public function test_selecting_zero_tags_fails_validation(): void
    {
        AppSetting::set('current_phase', 'selection');
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/student/selection', ['tag_ids' => []]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tag_ids']);
    }

    public function test_a_single_tag_selection_is_allowed(): void
    {
        AppSetting::set('current_phase', 'selection');
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $tagIds = $this->makeTags(1);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/student/selection', ['tag_ids' => $tagIds]);

        $response->assertOk();
        $response->assertJson(['tag_ids' => $tagIds]);
    }

    public function test_selecting_more_than_six_tags_fails_validation(): void
    {
        AppSetting::set('current_phase', 'selection');
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $tagIds = $this->makeTags(7);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/student/selection', ['tag_ids' => $tagIds]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tag_ids']);
    }

    public function test_selection_cannot_be_saved_outside_the_selection_phase(): void
    {
        AppSetting::set('current_phase', 'preparation');
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $tagIds = $this->makeTags(4);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/student/selection', ['tag_ids' => $tagIds]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('student_tag_preferences', ['student_id' => $student->id]);
    }

    public function test_saving_a_new_selection_replaces_the_previous_one(): void
    {
        AppSetting::set('current_phase', 'selection');
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $firstBatch = $this->makeTags(4);
        $this->actingAs($student, 'sanctum')->postJson('/api/student/selection', ['tag_ids' => $firstBatch]);

        $secondBatch = $this->makeTags(4);
        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/student/selection', ['tag_ids' => $secondBatch]);

        $response->assertOk();
        $this->assertSame(4, $student->tagPreferences()->count());
        foreach ($firstBatch as $tagId) {
            $this->assertDatabaseMissing('student_tag_preferences', ['student_id' => $student->id, 'tag_id' => $tagId]);
        }
    }

    public function test_student_can_view_their_current_selection(): void
    {
        AppSetting::set('current_phase', 'selection');
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $tagIds = $this->makeTags(4);
        $this->actingAs($student, 'sanctum')->postJson('/api/student/selection', ['tag_ids' => $tagIds]);

        $response = $this->actingAs($student, 'sanctum')->getJson('/api/student/selection');

        $response->assertOk();
        $this->assertEqualsCanonicalizing($tagIds, $response->json('tag_ids'));
    }

    public function test_selection_order_reflects_the_saved_priority(): void
    {
        AppSetting::set('current_phase', 'selection');
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $tagIds = $this->makeTags(4);
        $prioritized = array_reverse($tagIds);
        $this->actingAs($student, 'sanctum')->postJson('/api/student/selection', ['tag_ids' => $prioritized]);

        $response = $this->actingAs($student, 'sanctum')->getJson('/api/student/selection');

        $response->assertOk();
        $this->assertSame($prioritized, $response->json('tag_ids'));
    }

    public function test_non_students_cannot_save_a_selection(): void
    {
        AppSetting::set('current_phase', 'selection');
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);
        $tagIds = $this->makeTags(4);

        $response = $this->actingAs($consultant, 'sanctum')
            ->postJson('/api/student/selection', ['tag_ids' => $tagIds]);

        $response->assertForbidden();
    }

    public function test_a_nonexistent_tag_id_fails_validation(): void
    {
        AppSetting::set('current_phase', 'selection');
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $tagIds = $this->makeTags(3);
        $tagIds[] = 999999;

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/student/selection', ['tag_ids' => $tagIds]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tag_ids.3']);
    }
}
