<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerationHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProject(string $suffix = ''): Project
    {
        return Project::create([
            'project_name' => 'History Demo' . $suffix,
            'absolute_path' => sys_get_temp_dir(),
            'framework_type' => 'laravel',
        ]);
    }

    protected function makeGeneration(Project $project, array $overrides = []): Generation
    {
        return Generation::create(array_merge([
            'project_id' => $project->id,
            'prompt_text' => 'Prompt riwayat',
            'erd_mermaid_text' => 'erDiagram',
            'migration_files' => [['filename' => 'a.php', 'content' => 'isi-berat']],
            'status' => 'draft',
            'target_version' => '13',
            'ai_driver' => 'openrouter',
            'database_dialect' => 'mysql',
            'target_framework' => 'laravel',
            'job_status' => 'ready',
        ], $overrides));
    }

    public function test_history_endpoint_paginates_without_heavy_columns(): void
    {
        $project = $this->makeProject();
        $this->makeGeneration($project);
        $this->makeGeneration($project, ['prompt_text' => 'Prompt kedua']);

        $response = $this->getJson("/api/projects/{$project->id}/generations");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total', 2);
        // Kolom berat tidak boleh bocor ke daftar riwayat
        $this->assertArrayNotHasKey('migration_files', $response->json('data.data.0'));
        $this->assertArrayNotHasKey('erd_mermaid_text', $response->json('data.data.0'));
    }

    public function test_history_endpoint_404_for_unknown_project(): void
    {
        $this->getJson('/api/projects/00000000-0000-0000-0000-000000000000/generations')
            ->assertStatus(404);
    }

    public function test_history_endpoint_supports_sorting(): void
    {
        $project = $this->makeProject();
        $this->makeGeneration($project, ['prompt_text' => 'AAA pertama']);
        $this->makeGeneration($project, ['prompt_text' => 'ZZZ kedua', 'status' => 'injected']);

        $asc = $this->getJson("/api/projects/{$project->id}/generations?sort=status&direction=asc");
        $asc->assertStatus(200);
        $statuses = array_column($asc->json('data.data'), 'status');
        $sorted = $statuses;
        sort($sorted);
        $this->assertEquals($sorted, $statuses);

        $this->getJson("/api/projects/{$project->id}/generations?sort=password&direction=desc")
            ->assertStatus(422);

        $this->getJson("/api/projects/{$project->id}/generations?sort=status&direction=sideways")
            ->assertStatus(422);
    }

    public function test_conflicts_preview_returns_target_and_files(): void
    {
        $project = $this->makeProject();
        $generation = $this->makeGeneration($project);

        $response = $this->getJson("/api/generations/{$generation->id}/conflicts");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.valid', true);
        $response->assertJsonStructure([
            'data' => ['target_directory', 'existing_files', 'proposed_files', 'already_injected'],
        ]);
        $this->assertContains('a.php', $response->json('data.proposed_files'));
    }

    public function test_conflicts_preview_invalid_without_files(): void
    {
        $project = $this->makeProject();
        $generation = $this->makeGeneration($project, ['migration_files' => []]);

        $response = $this->getJson("/api/generations/{$generation->id}/conflicts");

        $response->assertStatus(200);
        $response->assertJsonPath('data.valid', false);
    }

    public function test_history_page_renders(): void
    {
        $this->get('/history')->assertStatus(200);
    }
}
