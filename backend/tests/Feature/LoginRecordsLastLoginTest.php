<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use Tests\TestCase;

class LoginRecordsLastLoginTest extends TestCase
{
    use \Tests\Concerns\FakesLdapUsers;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DirectoryFake::tearDown();
        parent::tearDown();
    }

    public function test_student_ldap_login_records_last_login_at(): void
    {
        $fake = DirectoryFake::setup();
        $dn = $this->fakeLdapUser($fake->getLdapConnection(), 'jdoe');
        $fake->getLdapConnection()->expect(
            LdapFake::operation('bind')->with($dn, 'password')->andReturnResponse()
        );

        $response = $this->postJson('/api/auth/student/login', [
            'username' => 'jdoe',
            'password' => 'password',
        ]);

        $response->assertOk();
        $student = User::where('ldap_username', 'jdoe')->first();
        $this->assertNotNull($student->last_login_at);
    }

    public function test_failed_student_ldap_login_does_not_record_last_login_at(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT, 'ldap_username' => 'jdoe']);
        $this->assertNull($student->last_login_at);

        $fake = DirectoryFake::setup();
        $dn = $this->fakeLdapUser($fake->getLdapConnection(), 'jdoe');
        $fake->getLdapConnection()->expect(
            LdapFake::operation('bind')->with($dn, 'wrong-password')->andReturnErrorResponse(49, 'Invalid credentials')
        );

        $response = $this->postJson('/api/auth/student/login', [
            'username' => 'jdoe',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertNull($student->fresh()->last_login_at);
    }

    public function test_consultant_password_login_records_last_login_at(): void
    {
        $consultant = User::factory()->create([
            'role' => User::ROLE_CONSULTANT,
            'email' => 'consultant@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->assertNull($consultant->last_login_at);

        $response = $this->postJson('/api/auth/consultant/login', [
            'email' => 'consultant@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertNotNull($consultant->fresh()->last_login_at);
    }

    public function test_failed_consultant_password_login_does_not_record_last_login_at(): void
    {
        $consultant = User::factory()->create([
            'role' => User::ROLE_CONSULTANT,
            'email' => 'consultant@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/auth/consultant/login', [
            'email' => 'consultant@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertNull($consultant->fresh()->last_login_at);
    }
}
