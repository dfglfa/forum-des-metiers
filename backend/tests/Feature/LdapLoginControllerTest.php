<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use Tests\TestCase;

class LdapLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DirectoryFake::tearDown();
        parent::tearDown();
    }

    public function test_student_can_log_in_via_ldap_with_the_correct_password(): void
    {
        $fake = DirectoryFake::setup();
        $dn = 'uid=jdoe,' . config('ldap.connections.default.base_dn');

        $fake->getLdapConnection()->expect(
            LdapFake::operation('bind')->with($dn, 'correct-password')->andReturnResponse()
        );

        $response = $this->postJson('/api/auth/student/login', [
            'username' => 'jdoe',
            'password' => 'correct-password',
        ]);

        $response->assertOk();
        $this->assertSame(User::ROLE_STUDENT, User::where('ldap_username', 'jdoe')->first()->role);
    }

    public function test_student_login_via_ldap_rejects_a_wrong_password(): void
    {
        $fake = DirectoryFake::setup();
        $dn = 'uid=jdoe,' . config('ldap.connections.default.base_dn');

        $fake->getLdapConnection()->expect(
            LdapFake::operation('bind')->with($dn, 'wrong-password')->andReturnErrorResponse(49, 'Invalid credentials')
        );

        $response = $this->postJson('/api/auth/student/login', [
            'username' => 'jdoe',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
        $this->assertNull(User::where('ldap_username', 'jdoe')->first());
    }

    public function test_consultant_can_log_in_via_ldap_with_the_correct_password(): void
    {
        AppSetting::set('ldap_consultants', 'true');

        $fake = DirectoryFake::setup();
        $dn = 'uid=jsmith,' . config('ldap.connections.default.base_dn');

        $fake->getLdapConnection()->expect(
            LdapFake::operation('bind')->with($dn, 'correct-password')->andReturnResponse()
        );

        $response = $this->postJson('/api/auth/consultant/login', [
            'username' => 'jsmith',
            'password' => 'correct-password',
        ]);

        $response->assertOk();
    }

    public function test_consultant_login_via_ldap_rejects_a_wrong_password(): void
    {
        AppSetting::set('ldap_consultants', 'true');

        $fake = DirectoryFake::setup();
        $dn = 'uid=jsmith,' . config('ldap.connections.default.base_dn');

        $fake->getLdapConnection()->expect(
            LdapFake::operation('bind')->with($dn, 'wrong-password')->andReturnErrorResponse(49, 'Invalid credentials')
        );

        $response = $this->postJson('/api/auth/consultant/login', [
            'username' => 'jsmith',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_cannot_log_in_via_the_consultant_endpoint(): void
    {
        AppSetting::set('ldap_consultants', 'true');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'email' => 'admin@example.com', 'password' => bcrypt('admin-pass')]);

        // No LDAP expectations are set up at all — if the request were routed through LDAP this
        // would throw an "unexpected method call" exception instead of a clean validation error.
        DirectoryFake::setup();

        $response = $this->postJson('/api/auth/consultant/login', [
            'email' => 'admin@example.com',
            'password' => 'admin-pass',
        ]);

        // Admins have their own dedicated login (/auth/admin/login) — the consultant endpoint
        // rejects them even with correct credentials.
        $response->assertStatus(422);
        $this->assertNotNull($admin->fresh());
    }

    public function test_admin_email_login_via_student_endpoint_fails_without_attempting_ldap(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'email' => 'admin@example.com', 'password' => bcrypt('admin-pass')]);

        // No LDAP expectations are set up — students always authenticate via LDAP now, so posting
        // `email` (no `username`) here must fail on validation grounds, never attempt an LDAP bind.
        DirectoryFake::setup();

        $response = $this->postJson('/api/auth/student/login', [
            'email' => 'admin@example.com',
            'password' => 'admin-pass',
        ]);

        $response->assertStatus(422);
        $this->assertNotNull($admin->fresh());
    }

    public function test_ldap_consultant_login_is_rejected_if_the_matched_user_is_an_admin(): void
    {
        AppSetting::set('ldap_consultants', 'true');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'ldap_username' => 'jdoe']);

        $fake = DirectoryFake::setup();
        $dn = 'uid=jdoe,' . config('ldap.connections.default.base_dn');
        $fake->getLdapConnection()->expect(
            LdapFake::operation('bind')->with($dn, 'correct-password')->andReturnResponse()
        );

        $response = $this->postJson('/api/auth/consultant/login', [
            'username' => 'jdoe',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(422);
        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    public function test_ldap_student_login_is_rejected_if_the_matched_user_is_an_admin(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'ldap_username' => 'jdoe']);

        $fake = DirectoryFake::setup();
        $dn = 'uid=jdoe,' . config('ldap.connections.default.base_dn');
        $fake->getLdapConnection()->expect(
            LdapFake::operation('bind')->with($dn, 'correct-password')->andReturnResponse()
        );

        $response = $this->postJson('/api/auth/student/login', [
            'username' => 'jdoe',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(422);
        $this->assertSame(User::ROLE_ADMIN, $admin->fresh()->role);
    }

    public function test_a_students_email_login_payload_is_rejected(): void
    {
        // Students always submit {username, password} — even with local-password fallback, there's
        // no email-based login path for them.
        User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'email' => 'student@example.com',
            'password' => bcrypt('student-pass'),
            'email_verified_at' => now(),
        ]);

        // No LDAP expectations are set up — the request must fail on missing `username`, not
        // attempt an LDAP bind.
        DirectoryFake::setup();

        $response = $this->postJson('/api/auth/student/login', [
            'email' => 'student@example.com',
            'password' => 'student-pass',
        ]);

        $response->assertStatus(422);
    }

    public function test_student_falls_back_to_the_local_password_when_ldap_is_unreachable(): void
    {
        // Point the "default" LDAP connection at a closed port so every bind fails with a real
        // connection error, simulating an unreachable directory server (as opposed to a reachable
        // one that simply rejects the credentials).
        Container::addConnection(new Connection([
            'hosts' => ['127.0.0.1'],
            'port' => 1,
            'base_dn' => config('ldap.connections.default.base_dn'),
            'username' => config('ldap.connections.default.username'),
            'password' => config('ldap.connections.default.password'),
            'timeout' => 1,
        ]), 'default');

        User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'ldap_username' => 'jdoe',
            'password' => bcrypt('local-password'),
        ]);

        $response = $this->postJson('/api/auth/student/login', [
            'username' => 'jdoe',
            'password' => 'local-password',
        ]);

        $response->assertOk();
    }

    public function test_student_login_with_a_wrong_ldap_password_is_rejected_without_falling_back_to_a_local_password(): void
    {
        // The student also has a valid local password that matches what's submitted below — if the
        // controller fell back on a mere auth rejection (rather than only on LDAP being unreachable),
        // this login would incorrectly succeed via the local password instead of failing.
        User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'ldap_username' => 'jdoe',
            'password' => bcrypt('wrong-password'),
        ]);

        $fake = DirectoryFake::setup();
        $dn = 'uid=jdoe,' . config('ldap.connections.default.base_dn');
        $fake->getLdapConnection()->expect(
            LdapFake::operation('bind')->with($dn, 'wrong-password')->andReturnErrorResponse(49, 'Invalid credentials')
        );

        $response = $this->postJson('/api/auth/student/login', [
            'username' => 'jdoe',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_student_can_log_in_with_a_local_password_when_ldap_students_is_disabled(): void
    {
        AppSetting::set('ldap_students', 'false');

        User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'ldap_username' => 'jdoe',
            'password' => bcrypt('local-password'),
        ]);

        // No LDAP expectations are set up — if the disabled flag were ignored, attempting an LDAP
        // bind would throw an "unexpected method call" exception instead of a clean response.
        DirectoryFake::setup();

        $response = $this->postJson('/api/auth/student/login', [
            'username' => 'jdoe',
            'password' => 'local-password',
        ]);

        $response->assertOk();
    }

    public function test_student_local_password_login_rejects_a_wrong_password_when_ldap_students_is_disabled(): void
    {
        AppSetting::set('ldap_students', 'false');

        User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'ldap_username' => 'jdoe',
            'password' => bcrypt('local-password'),
        ]);

        DirectoryFake::setup();

        $response = $this->postJson('/api/auth/student/login', [
            'username' => 'jdoe',
            'password' => 'totally-wrong',
        ]);

        $response->assertStatus(422);
    }

    public function test_student_local_password_is_never_checked_for_an_input_under_8_characters(): void
    {
        AppSetting::set('ldap_students', 'false');

        // The stored local password is itself under 8 characters, and is submitted verbatim below —
        // if the length gate were missing this would incorrectly succeed.
        User::factory()->create([
            'role' => User::ROLE_STUDENT,
            'ldap_username' => 'jdoe',
            'password' => bcrypt('short1'),
        ]);

        DirectoryFake::setup();

        $response = $this->postJson('/api/auth/student/login', [
            'username' => 'jdoe',
            'password' => 'short1',
        ]);

        $response->assertStatus(422);
    }

    public function test_a_consultants_own_password_cannot_bypass_a_mandated_ldap_consultants_flag(): void
    {
        AppSetting::set('ldap_consultants', 'true');
        User::factory()->create([
            'role' => User::ROLE_CONSULTANT,
            'email' => 'consultant@example.com',
            'password' => bcrypt('consultant-pass'),
            'email_verified_at' => now(),
        ]);

        DirectoryFake::setup();

        $response = $this->postJson('/api/auth/consultant/login', [
            'email' => 'consultant@example.com',
            'password' => 'consultant-pass',
        ]);

        $response->assertStatus(422);
    }
}
