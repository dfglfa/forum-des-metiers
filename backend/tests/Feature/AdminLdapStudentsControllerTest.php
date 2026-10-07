<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLdapStudentsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_config_reports_ldap_students_enabled_by_default(): void
    {
        $response = $this->getJson('/api/config');

        $response->assertOk();
        $response->assertJson(['ldap_students' => true]);
    }

    public function test_admin_can_disable_ldap_for_students(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/ldap-students', [
            'ldap_students' => false,
        ]);

        $response->assertOk();
        $response->assertJson(['ldap_students' => false]);
        $this->assertFalse(AppSetting::getBool('ldap_students', true));

        $configResponse = $this->getJson('/api/config');
        $configResponse->assertJson(['ldap_students' => false]);
    }

    public function test_admin_can_re_enable_ldap_for_students(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        AppSetting::set('ldap_students', 'false');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/ldap-students', [
            'ldap_students' => true,
        ]);

        $response->assertOk();
        $this->assertTrue(AppSetting::getBool('ldap_students', false));
    }

    public function test_non_admin_cannot_change_the_ldap_students_setting(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $response = $this->actingAs($student, 'sanctum')->postJson('/api/admin/ldap-students', [
            'ldap_students' => false,
        ]);

        $response->assertForbidden();
        $this->assertTrue(AppSetting::getBool('ldap_students', true));
    }
}
