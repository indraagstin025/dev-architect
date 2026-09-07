<?php

namespace Tests\Feature;

use App\Contracts\AIDriverInterface;
use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use App\Jobs\GenerateSchemaJob;
use App\Models\Generation;
use App\Models\Project;
use App\Services\Ai\Drivers\OpenRouterDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StubSuccessDriver implements AIDriverInterface
{
    public function generate(
        string $prompt,
        TargetFramework $framework = TargetFramework::LARAVEL,
        DatabaseDialect $dialect = DatabaseDialect::MYSQL,
        string $targetVersion = '13',
        ?string $model = null
    ): array {
        return [
            'erd_mermaid_text' => "erDiagram\n  USERS ||--o{ POSTS : has",
            'migration_files' => [
                [
                    'filename' => 'create_users_table.php',
                    'content' => "<?php\nuse Illuminate\Database\Migrations\Migration;\nuse Illuminate\Support\Facades\Schema;\nreturn new class extends Migration { public function up(): void { Schema::create('users', function (\$table) { \$table->id(); }); } };",
                ],
            ],
        ];
    }

    public function chat(
        array $messages,
        ?string $model = null,
        float $temperature = 0.7,
        int $maxTokens = 4000
    ): array {
        return [
            'content' => 'Stub response',
            'prompt_tokens' => 10,
            'completion_tokens' => 10,
        ];
    }
}

class StubFailingDriver extends StubSuccessDriver
{
    public function generate(
        string $prompt,
        TargetFramework $framework = TargetFramework::LARAVEL,
        DatabaseDialect $dialect = DatabaseDialect::MYSQL,
        string $targetVersion = '13',
        ?string $model = null
    ): array {
        throw new \RuntimeException('AI service down');
    }
}

class GenerationAsyncTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProject(): Project
    {
        return Project::create([
            'project_name' => 'Async Demo',
            'absolute_path' => sys_get_temp_dir(),
            'framework_type' => 'laravel',
        ]);
    }

    protected function makeGeneration(Project $project, string $jobStatus = 'queued'): Generation
    {
        return Generation::create([
            'project_id' => $project->id,
            'prompt_text' => 'Buatkan tabel users sederhana',
            'erd_mermaid_text' => '',
            'migration_files' => [],
            'status' => 'draft',
            'target_version' => '13',
            'ai_driver' => 'openrouter',
            'database_dialect' => 'mysql',
            'target_framework' => 'laravel',
            'job_status' => $jobStatus,
        ]);
    }

    public function test_generate_dispatches_job_and_returns_202(): void
    {
        Queue::fake();

        $project = $this->makeProject();

        $response = $this->postJson('/api/generations/generate', [
            'project_id' => $project->id,
            'prompt_text' => 'Buatkan tabel users sederhana',
            'target_framework' => 'laravel',
            'database_dialect' => 'mysql',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.job_status', 'queued');

        Queue::assertPushed(GenerateSchemaJob::class);
    }

    public function test_generate_rejects_incompatible_framework(): void
    {
        Queue::fake();

        $project = $this->makeProject(); // laravel

        $response = $this->postJson('/api/generations/generate', [
            'project_id' => $project->id,
            'prompt_text' => 'Buatkan tabel users sederhana',
            'target_framework' => 'express_prisma',
            'database_dialect' => 'mysql',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['target_framework']);
        Queue::assertNotPushed(GenerateSchemaJob::class);
    }

    public function test_generate_allows_raw_sql_for_any_project(): void
    {
        Queue::fake();

        $project = $this->makeProject(); // laravel

        $response = $this->postJson('/api/generations/generate', [
            'project_id' => $project->id,
            'prompt_text' => 'Buatkan tabel users sederhana',
            'target_framework' => 'raw_sql',
            'database_dialect' => 'mysql',
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(GenerateSchemaJob::class);
    }

    public function test_generate_rejects_mismatch_even_with_acknowledgement(): void
    {
        Queue::fake();

        $project = $this->makeProject(); // laravel

        // Opsi B: tidak ada bypass — ack diabaikan, tetap 422.
        $response = $this->postJson('/api/generations/generate', [
            'project_id' => $project->id,
            'prompt_text' => 'Buatkan tabel users sederhana',
            'target_framework' => 'express_prisma',
            'database_dialect' => 'mysql',
            'acknowledge_mismatch' => true,
        ]);

        $response->assertStatus(422);
        Queue::assertNotPushed(GenerateSchemaJob::class);
    }

    public function test_generate_allows_any_target_for_unknown_project(): void
    {
        Queue::fake();

        $project = Project::create([
            'project_name' => 'Unknown Stack',
            'absolute_path' => sys_get_temp_dir(),
            'framework_type' => 'raw_sql',
        ]);

        $response = $this->postJson('/api/generations/generate', [
            'project_id' => $project->id,
            'prompt_text' => 'Buatkan tabel users sederhana',
            'target_framework' => 'express_drizzle',
            'database_dialect' => 'mysql',
        ]);

        $response->assertStatus(202);
        Queue::assertPushed(GenerateSchemaJob::class);
    }

    public function test_status_endpoint_reports_job_status(): void
    {
        $generation = $this->makeGeneration($this->makeProject(), 'processing');

        $response = $this->getJson("/api/generations/{$generation->id}/status");

        $response->assertStatus(200);
        $response->assertJsonPath('data.job_status', 'processing');
    }

    public function test_cancel_queued_job_marks_cancelled(): void
    {
        $generation = $this->makeGeneration($this->makeProject(), 'queued');

        $response = $this->postJson("/api/generations/{$generation->id}/cancel");

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $generation->fresh()->job_status);

        $again = $this->postJson("/api/generations/{$generation->id}/cancel");
        $again->assertStatus(409);
    }

    public function test_job_skips_cancelled_generation_without_ai_call(): void
    {
        $this->app->bind(OpenRouterDriver::class, fn () => new StubFailingDriver());

        $generation = $this->makeGeneration($this->makeProject(), 'cancelled');

        // Harus return diam-diam: jika AI dipanggil, stub akan melempar.
        GenerateSchemaJob::dispatchSync($generation->id);

        $this->assertEquals('cancelled', $generation->fresh()->job_status);
        $this->assertNull($generation->fresh()->job_error);
    }

    public function test_job_marks_ready_on_success(): void
    {
        $this->app->bind(OpenRouterDriver::class, fn () => new StubSuccessDriver());

        $generation = $this->makeGeneration($this->makeProject(), 'queued');

        GenerateSchemaJob::dispatchSync($generation->id);

        $fresh = $generation->fresh();
        $this->assertEquals('ready', $fresh->job_status);
        $this->assertNotEmpty($fresh->migration_files);
        $this->assertNotEmpty($fresh->erd_mermaid_text);
    }

    public function test_job_marks_failed_on_ai_error(): void
    {
        $this->app->bind(OpenRouterDriver::class, fn () => new StubFailingDriver());

        $generation = $this->makeGeneration($this->makeProject(), 'queued');

        GenerateSchemaJob::dispatchSync($generation->id);

        $fresh = $generation->fresh();
        $this->assertEquals('failed', $fresh->job_status);
        $this->assertStringContainsString('AI service down', $fresh->job_error);
    }

    public function test_generator_and_review_pages_render(): void
    {
        $this->get('/generator')->assertStatus(200);

        $generation = $this->makeGeneration($this->makeProject(), 'ready');
        $this->get("/generations/{$generation->id}")->assertStatus(200);
    }
}
