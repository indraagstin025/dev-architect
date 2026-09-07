<?php

namespace App\Jobs;

use App\Models\ScaffoldJob;
use App\Services\ScaffoldCancelledException;
use App\Services\ScaffoldProjectService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TASK-606: Menjalankan scaffold proyek di background (bisa bermenit-menit).
 * - tries = 1: tidak mengulang unduhan/instalasi berat otomatis.
 * - timeout 600 dtk: selaras composer/npm. Pastikan DB_QUEUE_RETRY_AFTER > 600
 *   dan timeout worker antrean >= 900 agar job tidak dieksekusi ganda/dibunuh.
 */
class ScaffoldProjectJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public string $scaffoldJobId) {}

    public function handle(ScaffoldProjectService $service): void
    {
        $job = ScaffoldJob::find($this->scaffoldJobId);

        if (! $job || $job->status === 'cancelled') {
            return;
        }

        $job->update(['status' => 'processing']);

        try {
            $service->run($job, fn () => $job->fresh()->status === 'cancelled');
        } catch (ScaffoldCancelledException $e) {
            $service->cleanupTarget($job->target_path);
            return; // status sudah 'cancelled' dari endpoint
        } catch (Throwable $e) {
            $service->cleanupTarget($job->target_path);
            $job->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000)]);
            $job->appendLog('GAGAL: ' . $e->getMessage() . ' (Direktori target telah dibersihkan otomatis).');
            Log::error('ScaffoldProjectJob gagal: ' . $e->getMessage(), [
                'scaffold_job_id' => $job->id,
            ]);

            return;
        }

        $job->update(['status' => 'ready']);
        $job->appendLog('Selesai.');
    }

    public function failed(?Throwable $exception): void
    {
        $job = ScaffoldJob::find($this->scaffoldJobId);

        if (! $job || in_array($job->status, ['ready', 'cancelled'], true)) {
            return;
        }

        if (! empty($job->target_path)) {
            app(ScaffoldProjectService::class)->cleanupTarget($job->target_path);
        }

        $job->update([
            'status' => 'failed',
            'error' => $exception ? mb_substr($exception->getMessage(), 0, 2000) : 'Job gagal tanpa detail.',
        ]);
    }
}
