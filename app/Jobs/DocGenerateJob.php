<?php

namespace App\Jobs;

use App\Models\DocMessage;
use App\Models\DocVersion;
use App\Services\Ai\AiManager;
use App\Services\Ai\Prompts\Docs\DocPromptFactory;
use App\Services\Desktop\DesktopNotificationService;
use App\Services\ProjectService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TASK-1004: Generate satu dokumen (atau satu seksi, Q2) dari prompt builder
 * + chaining versi approved + konteks skema existing (Q16). Hasil otomatis
 * menjadi draf versi baru agar HITL bisa lanjut (approve/edit/regenerate).
 */
class DocGenerateJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public string $assistantMessageId,
        public string $docType,
        public ?string $section = null
    ) {}

    public function handle(
        AiManager $aiManager,
        DesktopNotificationService $notificationService
    ): void {
        $message = DocMessage::with('project')->find($this->assistantMessageId);

        if (! $message || $message->role !== 'assistant' || $message->job_status === 'cancelled') {
            return;
        }

        $message->update(['job_status' => 'processing']);
        $project = $message->project;
        $builder = DocPromptFactory::for($this->docType);

        $system = $builder::build($this->buildContext($project));

        $userInstruction = $this->section === null
            ? "Susun dokumen {$this->docType} lengkap sesuai struktur di atas."
            : "Susun HANYA seksi \"{$this->section}\" dari dokumen {$this->docType} sesuai struktur di atas.";

        try {
            [$result, $usedModel] = $aiManager->docsChat(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userInstruction],
                ],
                $project->ai_model ?: null,
                0.4,
                6000
            );
        } catch (Throwable $e) {
            $message->update([
                'job_status' => 'failed',
                'job_error' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            Log::error('DocGenerateJob gagal: ' . $e->getMessage(), [
                'message_id' => $message->id,
                'doc_type' => $this->docType,
            ]);

            try {
                $notificationService->notifyError('Generator Dokumen', 'Gagal menyusun dokumen. Silakan periksa log aplikasi.');
            } catch (Throwable $ignored) {
            }

            return;
        }

        if ($message->fresh()->job_status === 'cancelled') {
            return;
        }

        $message->update([
            'content' => $result['content'],
            'ai_model' => $usedModel,
            'prompt_tokens' => $result['prompt_tokens'] ?: null,
            'completion_tokens' => $result['completion_tokens'] ?: null,
            'job_status' => 'ready',
            'job_error' => null,
        ]);

        // Auto-snapshot draf (regenerasi = versi saudara, Q5).
        $latest = DocVersion::where('doc_project_id', $project->id)
            ->where('doc_type', $this->docType)
            ->orderBy('version', 'desc')
            ->first();

        DocVersion::create([
            'doc_project_id' => $project->id,
            'doc_type' => $this->docType,
            'version' => DocVersion::nextVersionNumber($project->id, $this->docType),
            'content_markdown' => $result['content'],
            'status' => 'draft',
            'parent_version_id' => $latest?->id,
            'ai_model' => $usedModel,
        ]);

        try {
            $notificationService->notifySchemaGenerated($project->title, 1, 'Dokumen ' . strtoupper($this->docType));
        } catch (Throwable $e) {
            Log::warning('Gagal memicu notifikasi desktop: ' . $e->getMessage());
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = DocMessage::find($this->assistantMessageId);

        if (! $message || in_array($message->job_status, ['ready', 'cancelled'], true)) {
            return;
        }

        $message->update([
            'job_status' => 'failed',
            'job_error' => $exception ? mb_substr($exception->getMessage(), 0, 2000) : 'Job gagal tanpa detail.',
        ]);
    }

    /**
     * Konteks chaining: brief + versi approved + skema existing (Q16).
     */
    protected function buildContext(\App\Models\DocProject $project): array
    {
        $approved = [];
        foreach ($project->versions()->where('status', 'approved')->orderBy('created_at')->get() as $version) {
            $approved[$version->doc_type] = $version->content_markdown;
        }

        $existing = [];
        $frameworkLabel = null;
        if ($project->code_project_id) {
            $codeProject = \App\Models\Project::find($project->code_project_id);
            if ($codeProject) {
                $frameworkLabel = $codeProject->framework_type?->label();
                try {
                    $existing = app(ProjectService::class)->getExistingSchemaFiles($codeProject);
                } catch (Throwable $e) {
                    Log::warning('Gagal membaca skema existing untuk konteks dokumen: ' . $e->getMessage());
                }
            }
        }

        if (! $frameworkLabel && $project->target_framework) {
            try {
                $frameworkLabel = \App\Enums\TargetFramework::from($project->target_framework)->label();
            } catch (\Throwable $e) {
                $frameworkLabel = $project->target_framework;
            }
        }

        return [
            'briefTitle' => $project->title,
            'brief' => (string) ($project->description ?? ''),
            'approved' => $approved,
            'existing' => $existing,
            'targetFrameworkLabel' => $frameworkLabel,
            'section' => $this->section,
        ];
    }
}
