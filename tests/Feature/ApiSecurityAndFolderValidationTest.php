<?php

namespace Tests\Feature;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use App\Models\AppSetting;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApiSecurityAndFolderValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devarch_sec_' . uniqid();
        File::makeDirectory($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_artisan_detect_dialects_warns_on_unverified_mysql_and_clears_with_flag(): void
    {
        // Proyek lama yang telanjur mysql tapi tidak ada bukti di direktori
        $project = Project::create([
            'project_name' => 'Legacy Fake MySQL Project',
            'absolute_path' => $this->tempDir,
            'framework_type' => TargetFramework::RAW_SQL,
            'database_dialect' => DatabaseDialect::MYSQL,
        ]);

        // 1. Mode --dry-run biasa harus menandai "PERLU REVIEW: mysql tanpa bukti"
        $this->artisan('projects:detect-dialects --dry-run')
            ->expectsOutputToContain('PERLU REVIEW: mysql tanpa bukti')
            ->assertExitCode(0);

        $project->refresh();
        $this->assertEquals(DatabaseDialect::MYSQL, $project->database_dialect);

        // 2. Mode --dry-run dengan --clear-unverified
        $this->artisan('projects:detect-dialects --dry-run --clear-unverified')
            ->expectsOutputToContain('Akan Direset ke (null) (Tanpa Bukti)')
            ->assertExitCode(0);

        $project->refresh();
        $this->assertEquals(DatabaseDialect::MYSQL, $project->database_dialect);

        // 3. Mode --apply dengan --clear-unverified (non-interactive) mereset dialek ke null
        $this->artisan('projects:detect-dialects --apply --clear-unverified --no-interaction')
            ->assertExitCode(0);

        $project->refresh();
        $this->assertNull($project->database_dialect, 'Legacy unverified dialect should be reset to null');
    }

    public function test_framework_marker_enforcement_rejects_folder_missing_markers(): void
    {
        // Folder kosong tanpa artisan / composer.json di luar temp dir simulasi
        $nonTempDir = (PHP_OS_FAMILY === 'Windows' ? 'C:\\DEVArchitect_Fake_Dir_' : '/tmp_devarch_fake_') . uniqid();
        File::makeDirectory($nonTempDir, 0755, true);

        try {
            $response = $this->postJson('/api/projects', [
                'project_name' => 'Invalid Laravel Project',
                'absolute_path' => $nonTempDir,
                'framework_type' => 'laravel',
            ]);

            $response->assertStatus(422);
            $response->assertJsonPath('success', false);
            $this->assertStringContainsString('penanda proyek yang valid untuk Laravel', $response->json('message'));
        } finally {
            if (File::isDirectory($nonTempDir)) {
                File::deleteDirectory($nonTempDir);
            }
        }
    }

    public function test_generic_folder_requires_explicit_confirmation(): void
    {
        $genericDir = (PHP_OS_FAMILY === 'Windows' ? 'C:\\DEVArchitect_Generic_' : '/generic_devarch_') . uniqid();
        File::makeDirectory($genericDir, 0755, true);

        try {
            // 1. Tanpa konfirmasi eksplisit -> ditolak 422
            $response = $this->postJson('/api/projects', [
                'project_name' => 'Unconfirmed Generic Folder',
                'absolute_path' => $genericDir,
                'framework_type' => 'raw_sql',
            ]);

            $response->assertStatus(422);
            $this->assertStringContainsString('Berikan konfirmasi eksplisit (confirm_generic_folder)', $response->json('message'));

            // 2. Dengan konfirmasi eksplisit (confirm_generic_folder: true) -> berhasil 201
            $successResponse = $this->postJson('/api/projects', [
                'project_name' => 'Confirmed Generic Folder',
                'absolute_path' => $genericDir,
                'framework_type' => 'raw_sql',
                'confirm_generic_folder' => true,
            ]);

            $successResponse->assertStatus(201);
            $this->assertDatabaseHas('projects', [
                'project_name' => 'Confirmed Generic Folder',
            ]);
        } finally {
            if (File::isDirectory($genericDir)) {
                File::deleteDirectory($genericDir);
            }
        }
    }

    public function test_user_profile_core_folders_are_rejected(): void
    {
        $userHome = getenv('USERPROFILE') ?: getenv('HOME');
        if (!$userHome) {
            $this->markTestSkipped('USERPROFILE/HOME not found');
        }

        $downloadsPath = $userHome . DIRECTORY_SEPARATOR . 'Downloads';
        if (!File::isDirectory($downloadsPath)) {
            $downloadsPath = $userHome . DIRECTORY_SEPARATOR . 'Desktop';
        }

        if (File::isDirectory($downloadsPath)) {
            $response = $this->postJson('/api/projects', [
                'project_name' => 'User Profile Folder Project',
                'absolute_path' => $downloadsPath,
                'framework_type' => 'raw_sql',
                'confirm_generic_folder' => true,
            ]);

            $response->assertStatus(422);
            $this->assertStringContainsString('folder profil pengguna tidak dapat didaftarkan', $response->json('message'));
        }
    }

    public function test_api_rejects_external_non_loopback_ip(): void
    {
        // Simulasi request dari IP jaringan luar (LAN 192.168.1.50)
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.50',
        ])->getJson('/api/projects');

        $response->assertStatus(403);
        $this->assertStringContainsString('hanya dapat diakses melalui koneksi lokal loopback', $response->json('message'));
    }

    public function test_api_rejects_untrusted_cross_origin_requests(): void
    {
        // Simulasi request dari situs web pihak ketiga di browser
        $response = $this->withHeaders([
            'Origin' => 'http://malicious-website.com',
        ])->getJson('/api/projects');

        $response->assertStatus(403);
        $this->assertStringContainsString('Permintaan lintas asal (Cross-Origin)', $response->json('message'));
    }

    public function test_api_allows_trusted_origin_and_validates_bridge_key(): void
    {
        $bridgeKey = AppSetting::getOrCreateDesktopBridgeKey();

        // 1. Origin lokal diizinkan
        $response = $this->withHeaders([
            'Origin' => 'http://127.0.0.1:8000',
        ])->getJson('/api/projects');

        $response->assertStatus(200);

        // 2. Simulasi penegakan token bridge: salah token -> 403
        $invalidKeyResponse = $this->withHeaders([
            'X-Test-Enforce-Bridge-Key' => '1',
            'X-DEVArchitect-Bridge-Key' => 'wrong-token',
        ])->getJson('/api/projects');

        $invalidKeyResponse->assertStatus(403);

        // 3. Token bridge cocok -> 200
        $validKeyResponse = $this->withHeaders([
            'X-Test-Enforce-Bridge-Key' => '1',
            'X-DEVArchitect-Bridge-Key' => $bridgeKey,
        ])->getJson('/api/projects');

        $validKeyResponse->assertStatus(200);
    }
}
