<?php

namespace Tests\Feature;

use App\Jobs\DocGenerateJob;
use App\Jobs\GenerateSchemaJob;
use App\Models\DocMessage;
use App\Models\DocProject;
use App\Models\DocVersion;
use App\Models\Project;
use App\Services\Ai\Drivers\OpenRouterDriver;
use App\Services\Ai\Prompts\Docs\DocPromptFactory;
use App\Services\Ai\Prompts\Docs\UrdPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatDocStubDriver extends OpenRouterDriver
{
    public static array $seenModels = [];

    public function chat(
        array $messages,
        ?string $model = null,
        float $temperature = 0.7,
        int $maxTokens = 4000
    ): array {
        static::$seenModels[] = $model;

        return [
            'content' => "# Dokumen stub\n\nIsi hasil generate.",
            'prompt_tokens' => 10,
            'completion_tokens' => 20,
        ];
    }
}

class DocGenerateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ChatDocStubDriver::$seenModels = [];
        $this->app->bind(OpenRouterDriver::class, fn () => new ChatDocStubDriver());
    }

    protected function makeProject(array $overrides = []): DocProject
    {
        return DocProject::create(array_merge([
            'title' => 'Penjualan Barang Bekas',
            'description' => 'Marketplace barang bekas.',
        ], $overrides));
    }

    public function test_prompt_builders_contain_structure_and_rules(): void
    {
        $urd = UrdPromptBuilder::build([
            'briefTitle' => 'Toko',
            'brief' => 'Jual beli.',
            'approved' => [],
            'existing' => [],
            'targetFrameworkLabel' => 'Laravel',
            'section' => null,
        ]);

        $this->assertStringContainsString('Kebutuhan Fungsional', $urd);
        $this->assertStringContainsString('Bahasa Indonesia', $urd);
        $this->assertStringContainsString('BATAS OTORITAS', $urd);

        $sys = DocPromptFactory::for('sysdesign')::build([
            'briefTitle' => 'Toko',
            'brief' => 'Jual beli.',
            'approved' => ['urd' => '# URD approved'],
            'existing' => ['2024_create_users_table.php'],
            'targetFrameworkLabel' => null,
            'section' => '5. Risiko Teknis',
        ]);

        $this->assertStringContainsString('Risiko', $sys);
        $this->assertStringContainsString('Complexity', $sys);
        $this->assertStringContainsString('2024_create_users_table.php', $sys);
        $this->assertStringContainsString('HANYA seksi', $sys);
        // Tanpa estimasi waktu dalam template
        $this->assertStringContainsString('estimasi waktu', strtolower($sys));

        $this->expectException(\InvalidArgumentException::class);
        DocPromptFactory::for('tidak-ada');
    }

    public function test_generate_doc_dispatches_and_rejects_bad_section(): void
    {
        Queue::fake();

        $project = $this->makeProject();

        $ok = $this->postJson("/api/docs/projects/{$project->id}/generate-doc", [
            'doc_type' => 'urd',
        ]);
        $ok->assertStatus(202);
        \Illuminate\Support\Facades\Queue::assertPushed(DocGenerateJob::class);

        $this->postJson("/api/docs/projects/{$project->id}/generate-doc", [
            'doc_type' => 'urd',
            'section' => '99. Tidak Ada',
        ])->assertStatus(422);
    }

    public function test_job_generates_and_auto_snapshots_draft(): void
    {
        $project = $this->makeProject();
        $msg = DocMessage::create([
            'doc_project_id' => $project->id,
            'role' => 'assistant',
            'content' => '',
            'stage' => 'brief',
            'job_status' => 'queued',
        ]);

        DocGenerateJob::dispatchSync($msg->id, 'urd', null);

        $this->assertEquals('ready', $msg->fresh()->job_status);

        $draft = DocVersion::where('doc_project_id', $project->id)
            ->where('doc_type', 'urd')
            ->first();
        $this->assertNotNull($draft);
        $this->assertEquals('draft', $draft->status);
        $this->assertEquals(1, $draft->version);
        $this->assertStringContainsString('Dokumen stub', $draft->content_markdown);
    }

    public function test_handoff_requires_approved_sysdesign_and_linked_project(): void
    {
        $project = $this->makeProject();

        $urd = DocVersion::create([
            'doc_project_id' => $project->id,
            'doc_type' => 'urd',
            'version' => 1,
            'content_markdown' => '# URD',
            'status' => 'approved',
        ]);

        // Bukan sysdesign → 422
        $this->postJson("/api/docs/versions/{$urd->id}/to-schema")->assertStatus(422);

        $sys = DocVersion::create([
            'doc_project_id' => $project->id,
            'doc_type' => 'sysdesign',
            'version' => 1,
            'content_markdown' => '# SysDesign',
            'status' => 'approved',
        ]);

        // Belum taut proyek kode → 422
        $this->postJson("/api/docs/versions/{$sys->id}/to-schema")->assertStatus(422);

        $codeProject = Project::create([
            'project_name' => 'Backend Toko',
            'absolute_path' => sys_get_temp_dir(),
            'framework_type' => 'laravel',
        ]);
        $project->update(['code_project_id' => $codeProject->id]);

        Queue::fake();
        $response = $this->postJson("/api/docs/versions/{$sys->id}/to-schema");
        $response->assertStatus(202);
        $response->assertJsonPath('data.project_id', $codeProject->id);
        $response->assertJsonPath('data.doc_version_id', $sys->id);
        Queue::assertPushed(GenerateSchemaJob::class);
    }

    public function test_sections_endpoint_lists_per_type(): void
    {
        $response = $this->getJson('/api/docs/sections');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.urd'));
        $this->assertContains('5. Risiko Teknis', $response->json('data.sysdesign'));
    }
}
