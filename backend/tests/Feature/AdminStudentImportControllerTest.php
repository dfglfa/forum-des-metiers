<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use Tests\TestCase;

class AdminStudentImportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        DirectoryFake::tearDown();
        parent::tearDown();
    }

    public function test_admin_can_import_students_from_a_csv(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "lastname,firstname,class,username\n"
            . "Doe,Jane,8a,jdoe\n"
            . "Smith,John,8b,jsmith\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/students/import', [
            'csv' => $file,
        ]);

        $response->assertOk();
        $response->assertJson([
            'imported_count' => 2,
            'imported'       => ['jdoe', 'jsmith'],
            'skipped'        => [],
        ]);
        $this->assertDatabaseHas('users', [
            'name'          => 'Jane Doe',
            'first_name'    => 'Jane',
            'last_name'     => 'Doe',
            'ldap_username' => 'jdoe',
            'class'         => '8a',
            'role'          => User::ROLE_STUDENT,
        ]);
        $this->assertDatabaseHas('users', [
            'name'          => 'John Smith',
            'first_name'    => 'John',
            'last_name'     => 'Smith',
            'ldap_username' => 'jsmith',
            'class'         => '8b',
            'role'          => User::ROLE_STUDENT,
        ]);
    }

    public function test_a_password_column_is_hashed_and_stored_for_the_local_password_fallback(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "lastname,firstname,class,username,password\n"
            . "Doe,Jane,8a,jdoe,some-password\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/students/import', [
            'csv' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('imported_count', 1);
        $student = User::where('ldap_username', 'jdoe')->first();
        $this->assertNotNull($student->password);
        $this->assertTrue(Hash::check('some-password', $student->password));
    }

    public function test_a_missing_password_column_leaves_the_local_password_null(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "lastname,firstname,class,username\nDoe,Jane,8a,jdoe\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/students/import', [
            'csv' => $file,
        ]);

        $response->assertOk();
        $this->assertNull(User::where('ldap_username', 'jdoe')->first()->password);
    }

    public function test_student_import_skips_rows_with_a_duplicate_or_missing_username(): void
    {
        User::factory()->create(['ldap_username' => 'jdoe', 'role' => User::ROLE_STUDENT]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "lastname,firstname,class,username\n"
            . "Doe,Jane,8a,jdoe\n" // duplicate of an already-existing student
            . "Smith,John,8b,\n" // missing username
            . "Miller,Amy,8c,amiller\n"
            . "Miller,Amy,8c,amiller\n"; // duplicate within the same file
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/students/import', [
            'csv' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('imported_count', 1);
        $response->assertJsonPath('imported', ['amiller']);
        $this->assertCount(3, $response->json('skipped'));
    }

    public function test_student_import_skips_blank_lines(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "lastname,firstname,class,username\n"
            . "Doe,Jane,8a,jdoe\n"
            . "\n"
            . "Smith,John,8b,jsmith\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->actingAs($admin, 'sanctum')->post('/api/admin/students/import', [
            'csv' => $file,
        ]);

        $response->assertOk();
        $response->assertJsonPath('imported_count', 2);
    }

    public function test_imported_students_class_survives_a_subsequent_ldap_login(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $csv = "lastname,firstname,class,username\nDoe,Jane,8a,jdoe\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);
        $this->actingAs($admin, 'sanctum')->post('/api/admin/students/import', ['csv' => $file]);

        $fake = DirectoryFake::setup();
        $dn = 'uid=jdoe,' . config('ldap.connections.default.base_dn');
        $fake->getLdapConnection()->expect(
            LdapFake::operation('bind')->with($dn, 'secret')->andReturnResponse()
        );

        $response = $this->postJson('/api/auth/student/login', [
            'username' => 'jdoe',
            'password' => 'secret',
        ]);

        $response->assertOk();
        // The LDAP directory search isn't faked here, so name/email fall back to the pre-imported
        // values instead of being overwritten with directory data — the `class` column, which the
        // login controller never touches, must survive regardless.
        $this->assertSame('8a', User::where('ldap_username', 'jdoe')->first()->class);
    }

    public function test_non_admin_cannot_import_students(): void
    {
        $student = User::factory()->create(['role' => User::ROLE_STUDENT]);

        $csv = "lastname,firstname,class,username\nDoe,Jane,8a,jdoe\n";
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->actingAs($student, 'sanctum')->post('/api/admin/students/import', [
            'csv' => $file,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['ldap_username' => 'jdoe']);
    }
}
