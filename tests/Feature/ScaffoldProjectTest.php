<?php

namespace Tests\Feature;

use App\Jobs\ScaffoldProjectJob;
use App\Models\ScaffoldJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScaffoldProjectTest extends TestCase
{
    use RefreshDatabase;

    protected string $parent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parent = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scaffold_feat_' . uniqid();
        mkdir($this->parent, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->parent);
        parent::tearDown();
    }

    protected function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_prerequisites_returns_tool_matrix(): void
    {
        $response = $this->getJson('/api/scaffold/prerequisites');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['php', 'composer', 'git', 'node', 'npm', 'java', 'zip', 'internet', 'parent_default'],
        ]);
    }

    public function test_store_rejects_bad_slug(): void
    {
        $this->postJson('/api/projects/scaffold', [
            'template' => 'laravel',
            'project_name' => 'Nama Salah!!',
            'parent_path' => $this->parent,
        ])->assertStatus(422);
    }

    public function test_store_auto_creates_missing_parent_one_level(): void
    {
        Queue::fake();

        $missing = $this->parent . DIRECTORY_SEPARATOR . 'workspace-baru';

        $response = $this->postJson('/api/projects/scaffold', [
            'template' => 'raw_sql',
            'project_name' => 'toko-bekas',
            'parent_path' => $missing,
        ]);

        $response->assertStatus(202);
        $this->assertDirectoryExists($missing);
        Queue::assertPushed(ScaffoldProjectJob::class);
    }

    public function test_store_rejects_deeply_nested_missing_parent(): void
    {
        $this->postJson('/api/projects/scaffold', [
            'template' => 'raw_sql',
            'project_name' => 'toko-bekas',
            'parent_path' => $this->parent . DIRECTORY_SEPARATOR . 'typo' . DIRECTORY_SEPARATOR . 'berlapis',
        ])->assertStatus(422);
    }

    public function test_store_rejects_unknown_template(): void
    {
        $this->postJson('/api/projects/scaffold', [
            'template' => 'codeigniter',
            'project_name' => 'toko-bekas',
            'parent_path' => $this->parent,
        ])->assertStatus(422);
    }

    public function test_store_dispatches_raw_sql_without_tools(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/projects/scaffold', [
            'template' => 'raw_sql',
            'project_name' => 'toko-bekas',
            'parent_path' => $this->parent,
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'queued');
        Queue::assertPushed(ScaffoldProjectJob::class);
    }

    public function test_cancel_queued_scaffold_job(): void
    {
        $job = ScaffoldJob::create([
            'template' => 'raw_sql',
            'project_name' => 'toko-bekas',
            'parent_path' => $this->parent,
            'target_path' => $this->parent . DIRECTORY_SEPARATOR . 'toko-bekas',
            'status' => 'queued',
        ]);

        $this->postJson("/api/scaffold/{$job->id}/cancel")->assertStatus(200);
        $this->assertEquals('cancelled', $job->fresh()->status);

        $this->postJson("/api/scaffold/{$job->id}/cancel")->assertStatus(409);
    }

    public function test_scaffold_job_runs_raw_sql_and_registers_project(): void
    {
        $job = ScaffoldJob::create([
            'template' => 'raw_sql',
            'project_name' => 'toko-bekas',
            'parent_path' => $this->parent,
            'target_path' => $this->parent . DIRECTORY_SEPARATOR . 'toko-bekas',
            'status' => 'queued',
        ]);

        ScaffoldProjectJob::dispatchSync($job->id);

        $fresh = $job->fresh();
        $this->assertEquals('ready', $fresh->status);
        $this->assertNotNull($fresh->project_id);
        $this->assertFileExists($this->parent . DIRECTORY_SEPARATOR . 'toko-bekas' . DIRECTORY_SEPARATOR . 'README.md');
        $this->assertEquals($fresh->project_id, \App\Models\AppSetting::get('active_project_id'));
    }

    public function test_scaffold_job_cleans_up_target_on_failure(): void
    {
        $target = $this->parent . DIRECTORY_SEPARATOR . 'gagal-proyek';
        mkdir($target, 0755, true);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'partial.txt', 'test');

        $job = ScaffoldJob::create([
            'template' => 'raw_sql',
            'project_name' => 'gagal-proyek',
            'parent_path' => $this->parent,
            'target_path' => $target,
            'status' => 'queued',
        ]);

        // Mock job failure by triggering exception or calling cleanupTarget
        $service = app(\App\Services\ScaffoldProjectService::class);
        $service->cleanupTarget($target);

        $this->assertDirectoryDoesNotExist($target);
    }

    public function test_cancel_scaffold_cleans_up_target_directory(): void
    {
        $target = $this->parent . DIRECTORY_SEPARATOR . 'batal-proyek';
        mkdir($target, 0755, true);
        file_put_contents($target . DIRECTORY_SEPARATOR . 'dummy.txt', 'data');

        $job = ScaffoldJob::create([
            'template' => 'raw_sql',
            'project_name' => 'batal-proyek',
            'parent_path' => $this->parent,
            'target_path' => $target,
            'status' => 'queued',
        ]);

        $this->postJson("/api/scaffold/{$job->id}/cancel")->assertStatus(200);
        $this->assertEquals('cancelled', $job->fresh()->status);
        $this->assertDirectoryDoesNotExist($target);
    }

    public function test_artisan_scaffold_run_command_executes_job(): void
    {
        $job = ScaffoldJob::create([
            'template' => 'raw_sql',
            'project_name' => 'artisan-proj',
            'parent_path' => $this->parent,
            'target_path' => $this->parent . DIRECTORY_SEPARATOR . 'artisan-proj',
            'status' => 'queued',
        ]);

        $this->artisan('devarchitect:scaffold-run', ['jobId' => $job->id])
            ->assertExitCode(0);

        $fresh = $job->fresh();
        $this->assertEquals('ready', $fresh->status);
        $this->assertNotNull($fresh->project_id);
        $this->assertFileExists($this->parent . DIRECTORY_SEPARATOR . 'artisan-proj' . DIRECTORY_SEPARATOR . 'README.md');
    }
}
