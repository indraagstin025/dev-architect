<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Ai\AiManager;
use App\Services\Desktop\DesktopNotificationService;
use App\Services\MigrationLinterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TASK-705: Menjalankan pemanggilan AI + linter di background (queue database).
 *
 * - tries = 1: gagal cepat tanpa menagih API dua kali.
 * - timeout 120 dtk: selaras dengan timeout HTTP driver 60 dtk + jeda.
 *   Pastikan DB_QUEUE_RETRY_AFTER >= 180 agar job tidak dieksekusi ganda.
 */
class GenerateSchemaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public string $generationId) {}

    public function handle(
        AiManager $aiManager,
        MigrationLinterService $linter,
        DesktopNotificationService $notificationService
    ): void {
        $generation = Generation::with('project')->find($this->generationId);

        if (! $generation || $generation->job_status === 'cancelled') {
            return;
        }

        $generation->update(['job_status' => 'processing']);

        try {
            $result = $aiManager->driver($generation->ai_driver)->generate(
                $generation->prompt_text,
                $generation->target_framework,
                $generation->database_dialect,
                $generation->target_version ?? '13'
            );
        } catch (Throwable $e) {
            $this->markFailed($generation, $e->getMessage(), $notificationService);

            return;
        }

        // Batal di tengah jalan (setelah AI menjawab): buang hasil, jangan simpan.
        if ($generation->fresh()->job_status === 'cancelled') {
            return;
        }

        $lint = $linter->validateDraft(
            $result['migration_files'] ?? [],
            $generation->project,
            $generation->target_framework,
            $generation->database_dialect
        );

        if (! $lint['isValid']) {
            $this->markFailed(
                $generation,
                'Hasil AI tidak lolos validasi: ' . implode(' ', array_slice($lint['errors'], 0, 3)),
                $notificationService,
                $lint['warnings']
            );

            return;
        }

        $files = array_slice($result['migration_files'] ?? [], 0, 30);

        $generation->update([
            'erd_mermaid_text' => $result['erd_mermaid_text'] ?? '',
            'migration_files' => $files,
            'status' => GenerationStatus::DRAFT,
            'job_status' => 'ready',
            'job_error' => null,
            'job_warnings' => $lint['warnings'],
        ]);

        try {
            $notificationService->notifySchemaGenerated(
                $generation->project->project_name,
                count($files),
                $generation->target_framework->label()
            );
        } catch (Throwable $e) {
            Log::warning('Gagal memicu notifikasi desktop: ' . $e->getMessage());
        }
    }

    /**
     * Dipanggil Laravel saat job gagal total (exception tak tertangani / timeout).
     */
    public function failed(?Throwable $exception): void
    {
        $generation = Generation::find($this->generationId);

        if (! $generation || in_array($generation->job_status, ['ready', 'cancelled'], true)) {
            return;
        }

        $generation->update([
            'job_status' => 'failed',
            'job_error' => $exception
                ? 'Job antrean gagal: ' . $exception->getMessage()
                : 'Job antrean gagal tanpa detail.',
        ]);

        Log::error('GenerateSchemaJob gagal total', [
            'generation_id' => $this->generationId,
            'error' => $exception?->getMessage(),
        ]);
    }

    protected function markFailed(
        Generation $generation,
        string $message,
        DesktopNotificationService $notificationService,
        array $warnings = []
    ): void {
        $generation->update([
            'job_status' => 'failed',
            'job_error' => $message,
            'job_warnings' => $warnings,
        ]);

        Log::error('GenerateSchemaJob gagal: ' . $message, [
            'generation_id' => $generation->id,
            'project_id' => $generation->project_id,
        ]);

        try {
            $notificationService->notifyError('Pembuatan Skema', 'Gagal memproses skema. Silakan periksa log aplikasi.');
        } catch (Throwable $e) {
            Log::warning('Gagal memicu notifikasi desktop: ' . $e->getMessage());
        }
    }
}
