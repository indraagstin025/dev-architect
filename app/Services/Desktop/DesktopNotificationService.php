<?php

namespace App\Services\Desktop;

use Illuminate\Support\Facades\Log;
use Native\Desktop\Facades\Notification;

class DesktopNotificationService
{
    /**
     * Notifikasi saat AI selesai men-generate skema database & ERD.
     */
    public function notifySchemaGenerated(string $projectName, int $filesCount, string $frameworkLabel): void
    {
        try {
            Notification::title('Rancangan Skema Database Siap! 🚀')
                ->message("AI berhasil merancang {$filesCount} berkas skema ({$frameworkLabel}) & diagram ERD untuk proyek '{$projectName}'. Silakan tinjau di mode Dry-Run.")
                ->show();
        } catch (\Throwable $e) {
            Log::warning("Gagal memicu notifikasi desktop (notifySchemaGenerated): " . $e->getMessage());
        }
    }

    /**
     * Notifikasi saat file skema/migrasi berhasil disuntikkan ke folder lokal proyek.
     */
    public function notifySchemaInjected(string $projectName, int $filesCount, string $frameworkLabel): void
    {
        try {
            Notification::title('Injeksi Skema Berhasil! ✅')
                ->message("Sebanyak {$filesCount} file skema ({$frameworkLabel}) telah berhasil ditulis ke direktori proyek '{$projectName}'.")
                ->show();
        } catch (\Throwable $e) {
            Log::warning("Gagal memicu notifikasi desktop (notifySchemaInjected): " . $e->getMessage());
        }
    }

    /**
     * Notifikasi jika terjadi kesalahan.
     */
    public function notifyError(string $title, string $errorMessage): void
    {
        try {
            Notification::title("Peringatan: {$title} ⚠️")
                ->message($errorMessage)
                ->show();
        } catch (\Throwable $e) {
            Log::warning("Gagal memicu notifikasi desktop (notifyError): " . $e->getMessage());
        }
    }
}
