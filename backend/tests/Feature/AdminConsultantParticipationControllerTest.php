<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConsultantParticipationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_consultant_defaults_to_participating(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        // The attribute isn't set on the in-memory model from create() since it relies on the
        // column's DB default, so re-fetch to check what was actually persisted.
        $this->assertTrue($consultant->fresh()->is_participating);
        $this->assertDatabaseHas('users', ['id' => $consultant->id, 'is_participating' => true]);
    }

    public function test_admin_can_mark_a_consultant_as_not_participating(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/consultants/{$consultant->id}/participation", ['is_participating' => false]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $consultant->id, 'is_participating' => false]);
    }

    public function test_admin_can_mark_a_consultant_as_participating_again(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT, 'is_participating' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/consultants/{$consultant->id}/participation", ['is_participating' => true]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $consultant->id, 'is_participating' => true]);
    }

    public function test_marking_as_not_participating_does_not_delete_profile_or_topic(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);
        $profile = $consultant->consultantProfile()->create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        $topic = $consultant->topics()->create(['title' => 'Becoming a Doctor']);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/consultants/{$consultant->id}/participation", ['is_participating' => false]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $consultant->id]);
        $this->assertDatabaseHas('consultant_profiles', ['id' => $profile->id]);
        $this->assertDatabaseHas('topics', ['id' => $topic->id]);
    }

    public function test_non_admin_cannot_change_participation_status(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson("/api/admin/consultants/{$consultant->id}/participation", ['is_participating' => false]);

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $consultant->id, 'is_participating' => true]);
    }

    public function test_is_participating_is_required_and_must_be_boolean(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/consultants/{$consultant->id}/participation", []);

        $response->assertStatus(422);
    }

    public function test_updating_a_non_consultant_user_via_this_endpoint_returns_404(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/consultants/{$student->id}/participation", ['is_participating' => false]);

        $response->assertNotFound();
    }
}
