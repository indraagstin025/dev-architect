<?php

namespace Tests\Feature;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use App\Models\DocProject;
use App\Models\DocVersion;
use App\Models\Generation;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ProjectLifecycleAndProgressTest extends TestCase
{
    use RefreshDatabase;

    private string $tempProjectDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempProjectDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devarch_test_proj_' . uniqid();
        File::makeDirectory($this->tempProjectDir, 0755, true, true);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempProjectDir)) {
            File::deleteDirectory($this->tempProjectDir);
        }
        parent::tearDown();
    }

    public function test_draft_project_attributes_and_defaults(): void
    {
        $project = Project::create([
            'project_name' => 'Marketplace Draft',
            'is_draft' => true,
            'absolute_path' => null,
            'framework_type' => TargetFramework::LARAVEL,
        ]);

        $this->assertTrue($project->isDraft());
        $this->assertFalse($project->hasPhysicalFolder());
        $this->assertFalse($project->hasErd());
        $this->assertSame(1, $project->lifecycle_progress);
        $this->assertSame('ideation', $project->lifecycle_stage);
        $this->assertSame('link', $project->next_action['action_type']);
        $this->assertSame('Rancang Dokumen ERD →', $project->next_action['text']);
    }

    public function test_lifecycle_progress_level_2_erd(): void
    {
        $doc = DocProject::create([
            'title' => 'ERP System',
            'stage' => 'sysdesign',
        ]);

        $project = Project::create([
            'project_name' => 'ERP System',
            'is_draft' => true,
            'absolute_path' => null,
            'doc_project_id' => $doc->id,
            'framework_type' => TargetFramework::LARAVEL,
        ]);

        DocVersion::create([
            'doc_project_id' => $doc->id,
            'version_num' => 1,
            'title' => 'Database Schema Design',
            'doc_type' => 'sysdesign',
            'content_markdown' => '## ERD Tables: users, roles, invoices',
        ]);

        $this->assertTrue($project->hasErd());
        $this->assertSame(2, $project->lifecycle_progress);
        $this->assertSame('erd_ready', $project->lifecycle_stage);
        $this->assertSame('modal_scaffold', $project->next_action['action_type']);
        $this->assertSame('Buat & Instal Proyek Ini →', $project->next_action['text']);
    }

    public function test_lifecycle_progress_level_3_installed_in_folder(): void
    {
        $project = Project::create([
            'project_name' => 'Installed App',
            'is_draft' => false,
            'absolute_path' => $this->tempProjectDir,
            'framework_type' => TargetFramework::LARAVEL,
        ]);

        $this->assertTrue($project->hasPhysicalFolder());
        $this->assertSame(3, $project->lifecycle_progress);
        $this->assertSame('scaffolded', $project->lifecycle_stage);
        $this->assertSame('link', $project->next_action['action_type']);
        $this->assertStringContainsString('/generator', $project->next_action['url']);
        $this->assertSame('Injeksi Kode Migrasi →', $project->next_action['text']);
    }

    public function test_lifecycle_progress_level_4_ready(): void
    {
        $project = Project::create([
            'project_name' => 'Completed App',
            'is_draft' => false,
            'absolute_path' => $this->tempProjectDir,
            'framework_type' => TargetFramework::LARAVEL,
        ]);

        Generation::create([
            'project_id' => $project->id,
            'status' => 'injected',
            'prompt_text' => 'Initial migration setup',
            'target_framework' => TargetFramework::LARAVEL,
            'migration_files' => ['create_users_table.php' => '...'],
        ]);

        $this->assertTrue($project->hasInjectedMigrations());
        $this->assertFalse($project->hasPendingMigrations());
        $this->assertSame(4, $project->lifecycle_progress);
        $this->assertSame('completed', $project->lifecycle_stage);
        $this->assertSame('open_editor', $project->next_action['action_type']);
        $this->assertSame('Buka di VS Code →', $project->next_action['text']);
    }

    public function test_needs_reinjection_when_schema_updated_after_migration(): void
    {
        $doc = DocProject::create([
            'title' => 'Billing System',
            'stage' => 'sysdesign',
        ]);

        $project = Project::create([
            'project_name' => 'Billing System',
            'is_draft' => false,
            'absolute_path' => $this->tempProjectDir,
            'doc_project_id' => $doc->id,
            'framework_type' => TargetFramework::LARAVEL,
        ]);

        // Older generation migration injection
        $gen = Generation::create([
            'project_id' => $project->id,
            'status' => 'injected',
            'prompt_text' => 'Initial migration',
            'target_framework' => TargetFramework::LARAVEL,
            'migration_files' => ['create_invoices_table.php' => '...'],
        ]);
        $gen->updated_at = now()->subHours(2);
        $gen->saveQuietly();

        // Newer version in assistant Canvas
        $ver = DocVersion::create([
            'doc_project_id' => $doc->id,
            'version_num' => 2,
            'title' => 'Updated Schema',
            'doc_type' => 'sysdesign',
            'content_markdown' => 'Added subscriptions table',
        ]);
        $ver->updated_at = now();
        $ver->saveQuietly();

        $this->assertTrue($project->needsReinjection());
        $this->assertTrue($project->next_action['re_injection']);
        $this->assertSame('Update & Injeksi Ulang →', $project->next_action['text']);
    }

    public function test_project_service_upgrades_existing_draft(): void
    {
        File::put($this->tempProjectDir . DIRECTORY_SEPARATOR . 'composer.json', json_encode(['require' => ['laravel/framework' => '^11.0']]));

        $doc = DocProject::create([
            'title' => 'HRIS Portal',
            'stage' => 'urd',
        ]);

        $draft = Project::create([
            'project_name' => 'HRIS Portal',
            'is_draft' => true,
            'absolute_path' => null,
            'doc_project_id' => $doc->id,
            'framework_type' => TargetFramework::LARAVEL,
        ]);

        $service = app(ProjectService::class);
        $result = $service->registerProject(
            'HRIS Portal',
            $this->tempProjectDir,
            TargetFramework::LARAVEL,
            DatabaseDialect::SQLITE
        );

        $this->assertSame($draft->id, $result->id);
        $this->assertFalse($result->isDraft());
        $this->assertSame($this->tempProjectDir, $result->absolute_path);
        $this->assertSame(1, Project::count());
    }

    public function test_terminal_open_editor_target_is_valid(): void
    {
        $project = Project::create([
            'project_name' => 'Terminal App',
            'is_draft' => false,
            'absolute_path' => $this->tempProjectDir,
            'framework_type' => TargetFramework::LARAVEL,
        ]);

        $response = $this->postJson("/api/projects/{$project->id}/open", [
            'target' => 'terminal',
        ]);

        // Should return 200 with success status
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }
}
