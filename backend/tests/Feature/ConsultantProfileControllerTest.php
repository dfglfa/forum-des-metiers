<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultantProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_graduation_year_is_validated_against_the_default_range(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')->postJson('/api/consultant/profile', [
            'graduation_year' => 1980,
        ]);

        $response->assertStatus(422);
    }

    public function test_graduation_year_cannot_be_later_than_the_current_year(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')->postJson('/api/consultant/profile', [
            'graduation_year' => now()->year + 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_graduation_year_is_validated_against_the_admin_configured_range(): void
    {
        AppSetting::set('graduation_year_min', '2000');
        AppSetting::set('graduation_year_max', '2010');
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $tooEarly = $this->actingAs($consultant, 'sanctum')->postJson('/api/consultant/profile', [
            'graduation_year' => 1999,
        ]);
        $tooEarly->assertStatus(422);

        $withinRange = $this->actingAs($consultant, 'sanctum')->postJson('/api/consultant/profile', [
            'graduation_year' => 2005,
        ]);
        $withinRange->assertOk();
        $this->assertDatabaseHas('consultant_profiles', [
            'user_id' => $consultant->id,
            'graduation_year' => 2005,
        ]);
    }

    public function test_consultant_can_set_their_preferred_language(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')->postJson('/api/consultant/profile', [
            'language' => 'fr',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('consultant_profiles', [
            'user_id' => $consultant->id,
            'language' => 'fr',
        ]);
    }

    public function test_updating_the_profile_with_an_invalid_language_fails_validation(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')->postJson('/api/consultant/profile', [
            'language' => 'en',
        ]);

        $response->assertStatus(422);
    }

    public function test_phone_with_plus_and_spaces_and_linkedin_without_scheme_are_accepted(): void
    {
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $response = $this->actingAs($consultant, 'sanctum')->postJson('/api/consultant/profile', [
            'phone' => ' +49  170 123 4567 ',
            'linkedin_url' => 'www.linkedin.com/in/jörg-müller-123/',
        ]);

        $response->assertOk()
            ->assertJsonPath('phone', '+49 170 123 4567')
            ->assertJsonPath('linkedin_url', 'https://www.linkedin.com/in/jörg-müller-123/');
    }

    public function test_validation_failures_are_logged(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $this->actingAs($consultant, 'sanctum')->postJson('/api/consultant/profile', [
            'phone' => 'abc',
        ])->assertStatus(422);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once();
    }

    public function test_series_with_a_long_name_can_be_saved(): void
    {
        \Illuminate\Support\Facades\DB::table('series')->insert(['name' => 'sonstige / autre', 'created_at' => now(), 'updated_at' => now()]);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $this->actingAs($consultant, 'sanctum')->postJson('/api/consultant/profile', [
            'serie' => 'sonstige / autre',
        ])->assertOk()->assertJsonPath('serie', 'sonstige / autre');
    }
}
