<?php

namespace Tests\Feature;

use App\Jobs\DocChatJob;
use App\Models\DocMessage;
use App\Models\DocProject;
use App\Models\DocVersion;
use App\Services\Ai\Drivers\OpenRouterDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChatStubDriver extends OpenRouterDriver
{
    public static bool $shouldThrow = false;

    public function chat(
        array $messages,
        ?string $model = null,
        float $temperature = 0.7,
        int $maxTokens = 4000
    ): array {
        if (static::$shouldThrow) {
            throw new \RuntimeException('AI down');
        }

        return [
            'content' => '# Draf Assistant (stub)',
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
        ];
    }
}

class DocChatFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ChatStubDriver::$shouldThrow = false;
        $this->app->bind(OpenRouterDriver::class, fn () => new ChatStubDriver());
    }

    protected function makeProject(array $overrides = []): DocProject
    {
        return DocProject::create(array_merge([
            'title' => 'Penjualan Barang Bekas',
            'description' => 'Marketplace barang bekas antar tetangga.',
        ], $overrides));
    }

    public function test_create_and_list_doc_projects(): void
    {
        $response = $this->postJson('/api/docs/projects', [
            'title' => 'Penjualan Barang Bekas',
            'description' => 'Marketplace.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.stage', 'brief');

        $this->getJson('/api/docs/projects')->assertStatus(200);
    }

    public function test_send_message_dispatches_job(): void
    {
        Queue::fake();

        $project = $this->makeProject();

        $response = $this->postJson("/api/docs/projects/{$project->id}/messages", [
            'content' => 'Saya ingin membuat marketplace barang bekas',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.assistant_message.job_status', 'queued');
        Queue::assertPushed(DocChatJob::class);
    }

    public function test_send_message_rejects_empty_content(): void
    {
        $project = $this->makeProject();

        $this->postJson("/api/docs/projects/{$project->id}/messages", [
            'content' => '',
        ])->assertStatus(422);
    }

    public function test_send_message_blocked_on_archived_project(): void
    {
        $project = $this->makeProject(['status' => 'archived']);

        $this->postJson("/api/docs/projects/{$project->id}/messages", [
            'content' => 'Halo',
        ])->assertStatus(409);
    }

    public function test_cancel_pending_assistant_message(): void
    {
        $project = $this->makeProject();
        $msg = DocMessage::create([
            'doc_project_id' => $project->id,
            'role' => 'assistant',
            'content' => '',
            'stage' => 'brief',
            'job_status' => 'queued',
        ]);

        $this->postJson("/api/docs/messages/{$msg->id}/cancel")->assertStatus(200);
        $this->assertEquals('cancelled', $msg->fresh()->job_status);

        $this->postJson("/api/docs/messages/{$msg->id}/cancel")->assertStatus(409);
    }

    public function test_job_completes_assistant_message_with_tokens(): void
    {
        $project = $this->makeProject();
        $msg = DocMessage::create([
            'doc_project_id' => $project->id,
            'role' => 'assistant',
            'content' => '',
            'stage' => 'brief',
            'job_status' => 'queued',
        ]);

        DocChatJob::dispatchSync($msg->id);

        $fresh = $msg->fresh();
        $this->assertEquals('ready', $fresh->job_status);
        $this->assertStringContainsString('Draf Assistant', $fresh->content);
        $this->assertEquals(100, $fresh->prompt_tokens);
        $this->assertEquals(50, $fresh->completion_tokens);
    }

    public function test_job_marks_failed_on_ai_error(): void
    {
        ChatStubDriver::$shouldThrow = true;

        $project = $this->makeProject();
        $msg = DocMessage::create([
            'doc_project_id' => $project->id,
            'role' => 'assistant',
            'content' => '',
            'stage' => 'brief',
            'job_status' => 'queued',
        ]);

        DocChatJob::dispatchSync($msg->id);

        $fresh = $msg->fresh();
        $this->assertEquals('failed', $fresh->job_status);
        $this->assertStringContainsString('AI down', $fresh->job_error);
    }

    public function test_snapshot_versioning_and_approve_chain(): void
    {
        $project = $this->makeProject();

        // v1 URD (parent null)
        $v1 = $this->postJson("/api/docs/projects/{$project->id}/versions", [
            'doc_type' => 'urd',
            'content_markdown' => '# URD v1',
        ]);
        $v1->assertStatus(201);
        $v1->assertJsonPath('data.version', 1);

        // v2 URD (saudara, parent = v1)
        $v2 = $this->postJson("/api/docs/projects/{$project->id}/versions", [
            'doc_type' => 'urd',
            'content_markdown' => '# URD v2',
        ]);
        $v2->assertStatus(201);
        $v2->assertJsonPath('data.version', 2);
        $this->assertEquals($v1->json('data.id'), $v2->json('data.parent_version_id'));

        // Approve v2 saat stage brief → maju ke prd
        $approve = $this->postJson("/api/docs/versions/{$v2->json('data.id')}/approve");
        $approve->assertStatus(200);
        $this->assertEquals('prd', $project->fresh()->stage);
    }

    public function test_approve_blocks_skipped_stage(): void
    {
        $project = $this->makeProject(); // stage brief

        $prd = $this->postJson("/api/docs/projects/{$project->id}/versions", [
            'doc_type' => 'prd',
            'content_markdown' => '# PRD nekat',
        ]);
        $prd->assertStatus(201);

        // Lompat ke PRD tanpa URD approved → 422
        $this->postJson("/api/docs/versions/{$prd->json('data.id')}/approve")
            ->assertStatus(422);
        $this->assertEquals('brief', $project->fresh()->stage);
    }

    public function test_edit_approved_version_is_blocked(): void
    {
        $project = $this->makeProject();
        $version = DocVersion::create([
            'doc_project_id' => $project->id,
            'doc_type' => 'urd',
            'version' => 1,
            'content_markdown' => '# final',
            'status' => 'approved',
        ]);

        $this->putJson("/api/docs/versions/{$version->id}", [
            'content_markdown' => '# diubah',
        ])->assertStatus(409);
    }

    public function test_archive_and_delete_project(): void
    {
        $project = $this->makeProject();

        $this->postJson("/api/docs/projects/{$project->id}/archive")->assertStatus(200);
        $this->assertEquals('archived', $project->fresh()->status);

        $this->deleteJson("/api/docs/projects/{$project->id}")->assertStatus(200);
        $this->assertNull(DocProject::find($project->id));
    }

    public function test_update_project_model_choice(): void
    {
        $project = $this->makeProject();

        $response = $this->putJson("/api/docs/projects/{$project->id}", [
            'ai_model' => 'qwen/qwen3.8-max',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('qwen/qwen3.8-max', $project->fresh()->ai_model);
    }

    public function test_model_catalog_returns_presets(): void
    {
        $response = $this->getJson('/api/docs/models');

        $response->assertStatus(200);
        $response->assertJsonPath('data.default', \App\Services\Ai\AiManager::DEFAULT_DOCS_MODEL);
        $this->assertNotEmpty($response->json('data.presets'));
    }

    public function test_assistant_and_dashboard_pages_render(): void
    {
        $this->get('/assistant')->assertStatus(200);
        $this->get('/dashboard')->assertStatus(200);
    }
}
