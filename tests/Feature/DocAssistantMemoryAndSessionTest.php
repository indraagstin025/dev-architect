<?php

namespace Tests\Feature;

use App\Jobs\DocChatJob;
use App\Models\DocMessage;
use App\Models\DocProject;
use App\Models\DocVersion;
use App\Models\Project;
use App\Services\Ai\Drivers\OpenRouterDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantMemoryStubDriver extends OpenRouterDriver
{
    public static array $lastMessages = [];

    public function chat(
        array $messages,
        ?string $model = null,
        float $temperature = 0.7,
        int $maxTokens = 4000
    ): array {
        static::$lastMessages = $messages;

        return [
            'content' => '## Arsitektur Respons (Stub)',
            'prompt_tokens' => 120,
            'completion_tokens' => 60,
        ];
    }
}

class DocAssistantMemoryAndSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AssistantMemoryStubDriver::$lastMessages = [];
        $this->app->bind(OpenRouterDriver::class, fn () => new AssistantMemoryStubDriver());
    }

    public function test_create_dashboard_project_creates_draft_and_links(): void
    {
        $doc = DocProject::create([
            'title' => 'Sistem Tiket Konser',
            'description' => 'Aplikasi penjualan tiket konser online.',
        ]);

        $res = $this->postJson("/api/docs/projects/{$doc->id}/create-dashboard-project");
        $res->assertStatus(201);
        $res->assertJson([
            'status' => 'success',
        ]);

        $project = Project::where('doc_project_id', $doc->id)->first();
        $this->assertNotNull($project);
        $this->assertTrue($project->isDraft());
        $this->assertNull($project->absolute_path);
        $this->assertSame('sistem-tiket-konser', $project->project_name);

        // Repeated call should return existing project without creating duplicates
        $repeat = $this->postJson("/api/docs/projects/{$doc->id}/create-dashboard-project");
        $repeat->assertStatus(200);
        $this->assertSame(1, Project::where('doc_project_id', $doc->id)->count());
    }

    public function test_archive_chat_marks_messages_as_archived(): void
    {
        $doc = DocProject::create([
            'title' => 'App Kasir Toko',
        ]);

        DocMessage::create([
            'doc_project_id' => $doc->id,
            'role' => 'user',
            'content' => 'Halo apa kabar',
            'job_status' => 'ready',
        ]);

        DocMessage::create([
            'doc_project_id' => $doc->id,
            'role' => 'assistant',
            'content' => 'Baik, ada yang bisa dibantu?',
            'job_status' => 'ready',
        ]);

        $this->assertSame(2, $doc->messages()->where('is_archived', false)->count());

        $res = $this->postJson("/api/docs/projects/{$doc->id}/archive-chat");
        $res->assertStatus(200);
        $res->assertJson(['status' => 'success']);

        $this->assertSame(0, $doc->messages()->where('is_archived', false)->count());
        $this->assertSame(2, $doc->messages()->where('is_archived', true)->count());

        // Calling show endpoint returns empty messages array for active chat
        $showRes = $this->getJson("/api/docs/projects/{$doc->id}");
        $showRes->assertStatus(200);
        $this->assertCount(0, $showRes->json('data.messages'));
    }

    public function test_export_transcript_markdown(): void
    {
        $doc = DocProject::create([
            'title' => 'Logistik Ekspedisi',
        ]);

        DocMessage::create([
            'doc_project_id' => $doc->id,
            'role' => 'user',
            'content' => 'Kebutuhan fitur tracking nomor resi kurir',
            'job_status' => 'ready',
        ]);

        DocMessage::create([
            'doc_project_id' => $doc->id,
            'role' => 'assistant',
            'content' => 'Tentu, modul tracking resi memerlukan tabel shipments dan tracking_events.',
            'job_status' => 'ready',
        ]);

        $res = $this->getJson("/api/docs/projects/{$doc->id}/export-transcript");

        $res->assertStatus(200);
        $res->assertJsonStructure([
            'status',
            'data' => ['filename', 'transcript_md'],
        ]);

        $md = $res->json('data.transcript_md');
        $this->assertStringContainsString('# Transkrip Diskusi Arsitektur: Logistik Ekspedisi', $md);
        $this->assertStringContainsString('Kebutuhan fitur tracking nomor resi kurir', $md);
        $this->assertStringContainsString('modul tracking resi memerlukan tabel shipments', $md);
    }

    public function test_messages_pagination_cursor(): void
    {
        $doc = DocProject::create([
            'title' => 'Forum Diskusi',
        ]);

        for ($i = 1; $i <= 30; $i++) {
            $m = new DocMessage([
                'doc_project_id' => $doc->id,
                'role' => $i % 2 === 1 ? 'user' : 'assistant',
                'content' => "Pesan ke-{$i}",
                'job_status' => 'ready',
            ]);
            $m->timestamps = false;
            $m->created_at = now()->subMinutes(35 - $i);
            $m->updated_at = now()->subMinutes(35 - $i);
            $m->save();
        }

        // Show endpoint returns latest 25 messages
        $showRes = $this->getJson("/api/docs/projects/{$doc->id}");
        $showRes->assertStatus(200);
        $this->assertCount(25, $showRes->json('data.messages'));
        $this->assertTrue($showRes->json('data.messages_pagination.has_more'));

        $oldestIdInView = $showRes->json('data.messages_pagination.oldest_id');
        $this->assertNotNull($oldestIdInView);

        // Fetch older messages before cursor
        $pagedRes = $this->getJson("/api/docs/projects/{$doc->id}/messages?before_id={$oldestIdInView}&limit=10");
        $pagedRes->assertStatus(200);
        $this->assertCount(5, $pagedRes->json('data'));
        $this->assertFalse($pagedRes->json('has_more'));
    }

    public function test_doc_chat_job_three_tier_memory_and_rolling_summary(): void
    {
        $doc = DocProject::create([
            'title' => 'Sistem Reservasi Hotel',
            'context_summary' => 'Ringkasan: User telah sepakat membuat fitur pemesanan kamar dan pembayaran DP.',
        ]);

        DocVersion::create([
            'doc_project_id' => $doc->id,
            'version_num' => 1,
            'title' => 'Lembar Dokumen SRS',
            'doc_type' => 'srs',
            'content_markdown' => '## Entitas Kamar, Tamu, dan Reservasi.',
        ]);

        // Archived message (should NOT be fed to LLM context)
        DocMessage::create([
            'doc_project_id' => $doc->id,
            'role' => 'user',
            'content' => 'Pesan lama yang sudah diarsipkan',
            'job_status' => 'ready',
            'is_archived' => true,
        ]);

        $userMsg = DocMessage::create([
            'doc_project_id' => $doc->id,
            'role' => 'user',
            'content' => 'Tambahkan skema tabel review dan rating hotel.',
            'job_status' => 'ready',
            'is_archived' => false,
        ]);

        $asstMsg = DocMessage::create([
            'doc_project_id' => $doc->id,
            'role' => 'assistant',
            'content' => '',
            'job_status' => 'queued',
            'is_archived' => false,
        ]);

        DocChatJob::dispatchSync($asstMsg->id);

        $sent = AssistantMemoryStubDriver::$lastMessages;
        $this->assertNotEmpty($sent);

        // Collect all system messages sent to driver
        $systemContents = collect($sent)
            ->where('role', 'system')
            ->pluck('content')
            ->implode("\n\n");

        $this->assertStringContainsString('LEMBAR DOKUMEN SISTEM SAAT INI (SINGLE SOURCE OF TRUTH / CANVAS', $systemContents);
        $this->assertStringContainsString('## Entitas Kamar, Tamu, dan Reservasi.', $systemContents);
        $this->assertStringContainsString('RINGKASAN KONTEKS DISKUSI SEBELUMNYA (ROLLING CONTEXT SUMMARY)', $systemContents);
        $this->assertStringContainsString('Ringkasan: User telah sepakat membuat fitur pemesanan kamar', $systemContents);

        // Make sure archived message was NOT in LLM context
        foreach ($sent as $m) {
            $this->assertStringNotContainsString('Pesan lama yang sudah diarsipkan', $m['content']);
        }
    }
}
