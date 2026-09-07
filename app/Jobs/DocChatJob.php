<?php

namespace App\Jobs;

use App\Models\DocMessage;
use App\Services\Ai\AiManager;
use App\Services\Desktop\DesktopNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TASK-1002: Balasan chat asisten dokumen di background (bisa bermenit-menit
 * untuk draf panjang). Pola job_status sama seperti GenerateSchemaJob.
 */
class DocChatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public string $assistantMessageId) {}

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

        try {
            [$result, $usedModel] = $aiManager->docsChat(
                $this->buildContext($project, $message),
                $project->ai_model ?: null
            );
        } catch (Throwable $e) {
            $message->update([
                'job_status' => 'failed',
                'job_error' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            Log::error('DocChatJob gagal: ' . $e->getMessage(), [
                'message_id' => $message->id,
                'doc_project_id' => $project->id,
            ]);

            try {
                $notificationService->notifyError('Asisten Dokumen', 'Gagal memproses pesan. Silakan periksa log aplikasi.');
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

        try {
            $notificationService->notifySchemaGenerated($project->title, 1, 'Asisten Dokumen');
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
     * Susun konteks: instruksi (ID, Q1, Q8) + brief + versi approved + N pesan terakhir.
     *
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildContext(\App\Models\DocProject $project, DocMessage $current): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'Anda adalah Asisten Arsitek Perangkat Lunak DEVArchitect. '
                    . 'Jawab SELALU dalam Bahasa Indonesia yang profesional. '
                    . 'Tugas Anda membantu pengguna menyusun dokumen arsitektur tahap demi tahap '
                    . '(brief, URD, PRD, SRS, system design). Bersikap ringkas namun lengkap, '
                    . 'gunakan format Markdown (heading, tabel, daftar). '
                    . 'Jangan mengarang kebutuhan yang tidak disampaikan; tandai asumsi secara eksplisit. '
                    . 'Tahap aktif saat ini: ' . $project->stage . '.',
            ],
        ];

        if (! empty($project->description)) {
            $messages[] = ['role' => 'system', 'content' => "BRIEF PROYEK '{$project->title}':\n" . $project->description];
        }

        $approved = $project->versions()
            ->where('status', 'approved')
            ->orderBy('created_at')
            ->get(['doc_type', 'version', 'content_markdown']);

        foreach ($approved as $version) {
            $messages[] = [
                'role' => 'system',
                'content' => "DOKUMEN " . strtoupper($version->doc_type) . " v{$version->version} (APPROVED, JANGAN UBAH FAKTANYA):\n" . $version->content_markdown,
            ];
        }

        // Lampirkan skema database existing jika proyek kode ditautkan (Q16)
        if ($project->code_project_id) {
            $codeProject = \App\Models\Project::find($project->code_project_id);
            if ($codeProject) {
                try {
                    $existingFiles = app(\App\Services\ProjectService::class)->getExistingSchemaFiles($codeProject);
                    if (! empty($existingFiles)) {
                        $messages[] = [
                            'role' => 'system',
                            'content' => "SKEMA / FILE DATABASE YANG SUDAH ADA DI PROYEK KODE TERTAUT:\n- "
                                . implode("\n- ", $existingFiles)
                                . "\n(Gunakan konteks ini sebagai acuan agar tidak merancang ulang entitas/tabel yang sudah ada.)",
                        ];
                    }
                } catch (\Throwable $e) {
                    Log::warning('DocChatJob gagal membaca file skema existing: ' . $e->getMessage());
                }
            }
        }

        $history = $project->messages()
            ->where('id', '!=', $current->id)
            ->where('job_status', '!=', 'failed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['role', 'content'])
            ->reverse();

        foreach ($history as $item) {
            if ($item->role === 'system' || trim((string) $item->content) === '') {
                continue;
            }
            $messages[] = ['role' => $item->role, 'content' => $item->content];
        }

        return $messages;
    }
}
