<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_and_settings_pages_render_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $responseDashboard = $this->get('/dashboard');
        $responseDashboard->assertStatus(200);

        $responseSettings = $this->get('/settings');
        $responseSettings->assertStatus(200);
    }

    public function test_can_set_and_get_active_project(): void
    {
        $project = Project::create([
            'project_name' => 'Demo Active Project',
            'absolute_path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'demo_active_' . uniqid(),
            'framework_type' => 'laravel',
        ]);

        // Set active
        $responseSet = $this->postJson('/api/projects/active', [
            'project_id' => $project->id,
        ]);
        $responseSet->assertStatus(200);
        $responseSet->assertJsonPath('success', true);

        // Get active
        $responseGet = $this->getJson('/api/projects/active');
        $responseGet->assertStatus(200);
        $responseGet->assertJsonPath('data.id', $project->id);
    }

    public function test_open_project_in_editor_validates_target(): void
    {
        $project = Project::create([
            'project_name' => 'Demo Target Project',
            'absolute_path' => sys_get_temp_dir(),
            'framework_type' => 'laravel',
        ]);

        // Invalid target should fail validation
        $responseInvalid = $this->postJson("/api/projects/{$project->id}/open", [
            'target' => 'sublime_text',
        ]);
        $responseInvalid->assertStatus(422);

        // Valid target should succeed
        $responseValid = $this->postJson("/api/projects/{$project->id}/open", [
            'target' => 'explorer',
        ]);
        $responseValid->assertStatus(200);
        $responseValid->assertJsonPath('success', true);
    }

    public function test_register_duplicate_project_path_returns_409(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devarchitect_dup_' . uniqid();
        mkdir($dir);

        try {
            $payload = [
                'project_name' => 'Dup Project',
                'absolute_path' => $dir,
            ];

            $first = $this->postJson('/api/projects', $payload);
            $first->assertStatus(201);

            $second = $this->postJson('/api/projects', $payload);
            $second->assertStatus(409);
            $second->assertJsonPath('success', false);
        } finally {
            rmdir($dir);
        }
    }

    public function test_register_nonexistent_path_returns_422(): void
    {
        $response = $this->postJson('/api/projects', [
            'project_name' => 'Ghost Project',
            'absolute_path' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'devarchitect_missing_' . uniqid(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }
}
