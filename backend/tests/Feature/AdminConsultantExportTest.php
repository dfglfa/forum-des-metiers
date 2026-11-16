<?php

namespace Tests\Feature;

use App\Models\ConsultantProfile;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class AdminConsultantExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_xlsx_with_all_consultants(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $consultant = User::factory()->create(['role' => User::ROLE_CONSULTANT, 'name' => 'Ada <Lovelace> & Co']);
        ConsultantProfile::create(['user_id' => $consultant->id, 'phone' => '+49 170 1', 'language' => 'de_fr', 'graduation_year' => 2005]);
        $tag = Tag::create(['name' => 'Law', 'slug' => 'law']);
        Topic::create(['title' => 'My talk', 'consultant_id' => $consultant->id, 'tag_id' => $tag->id, 'selected_slots' => ['10:00']]);

        $response = $this->actingAs($admin, 'sanctum')->get('/api/admin/consultants/export');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $path = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($path, $response->getContent());
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($path);

        $this->assertNotFalse(simplexml_load_string($sheet), 'sheet XML is well-formed');
        $this->assertStringContainsString('Ada &lt;Lovelace&gt; &amp; Co', $sheet);
        $this->assertStringContainsString('Law', $sheet);
        $this->assertStringContainsString('My talk', $sheet);
        $this->assertStringContainsString('Deutsch &amp; Französisch', $sheet);
        $this->assertStringContainsString('<v>2005</v>', $sheet);
    }

    public function test_non_admin_cannot_export(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CONSULTANT]);

        $this->actingAs($user, 'sanctum')->get('/api/admin/consultants/export')->assertForbidden();
    }
}
