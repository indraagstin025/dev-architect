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

        // TASK-M2-07: Perbarui rolling context summary jika percakapan aktif > 10 pesan
        $this->updateRollingSummaryIfNeeded($project);

        try {
            $notificationService->notifySchemaGenerated($project->title, 1, 'Asisten Dokumen');
        } catch (Throwable $e) {
            Log::warning('Gagal memicu notifikasi desktop: ' . $e->getMessage());
        }
    }

    /**
     * Memperbarui ringkasan bergulir (Rolling Context Summary) untuk pesan-pesan lama
     * di luar jendela 10 pesan terbaru agar AI tidak amnesia tanpa membakar token (TASK-M2-07).
     */
    protected function updateRollingSummaryIfNeeded(\App\Models\DocProject $project): void
    {
        try {
            $activeCount = $project->messages()
                ->where('is_archived', false)
                ->where('job_status', 'ready')
                ->count();

            if ($activeCount > 10) {
                // Ambil pesan di luar 10 pesan terakhir
                $olderMessages = $project->messages()
                    ->where('is_archived', false)
                    ->where('job_status', 'ready')
                    ->orderBy('created_at', 'desc')
                    ->skip(10)
                    ->take(15)
                    ->get()
                    ->reverse();

                if ($olderMessages->isNotEmpty()) {
                    $summaryLines = [];
                    foreach ($olderMessages as $msg) {
                        $role = $msg->role === 'user' ? 'User' : 'AI';
                        $snippet = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', (string) $msg->content)), 120);
                        $summaryLines[] = "- {$role}: {$snippet}";
                    }
                    $newSummary = implode("\n", $summaryLines);
                    $project->update(['context_summary' => $newSummary]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal memperbarui rolling context summary: ' . $e->getMessage());
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
     * Susun konteks: instruksi (ID, Q1, Q8) + brief + versi approved + rolling summary + N pesan terakhir.
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

        // Tier 1: Lembar Dokumen Canvas sebagai Single Source of Truth (SSOT) permanen
        $versions = $project->versions()
            ->orderBy('version_num', 'desc')
            ->take(3)
            ->get();

        if ($versions->isNotEmpty()) {
            foreach ($versions->reverse() as $ver) {
                $statusLabel = $ver->status === 'approved' ? 'APPROVED' : 'AKTIF / DRAFT';
                $messages[] = [
                    'role' => 'system',
                    'content' => "LEMBAR DOKUMEN SISTEM SAAT INI (SINGLE SOURCE OF TRUTH / CANVAS - {$statusLabel}):\n"
                        . "Tipe: " . strtoupper((string) $ver->doc_type) . " v{$ver->version_num} ({$ver->title})\n"
                        . $ver->content_markdown,
                ];
            }
        }

        // Tier 3: Rolling Context Summary (ringkasan padat percakapan lama di luar 10 pesan)
        if (! empty($project->context_summary)) {
            $messages[] = [
                'role' => 'system',
                'content' => "RINGKASAN KONTEKS DISKUSI SEBELUMNYA (ROLLING CONTEXT SUMMARY):\n" . $project->context_summary,
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

        // Tier 2: Sliding Context Window (10 pesan percakapan aktif terakhir yang belum diarsipkan)
        $history = $project->messages()
            ->where('id', '!=', $current->id)
            ->where('is_archived', false)
            ->where('job_status', '!=', 'failed')
            ->reorder('created_at', 'desc')
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
