<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Console\Command;

class DetectProjectDialectsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'projects:detect-dialects 
                            {--dry-run : Tampilkan hasil deteksi tanpa menyimpan perubahan ke database}
                            {--apply : Terapkan dan simpan hasil deteksi ke database}
                            {--force : Deteksi ulang dan perbarui seluruh proyek termasuk yang sudah memiliki dialek}
                            {--clear-unverified : Ubah dialek lama (misal MySQL default) menjadi null jika tidak ditemukan bukti pendukung di proyek}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mendeteksi dialek database untuk seluruh proyek terdaftar berdasarkan bukti nyata di filesystem';

    /**
     * Execute the console command.
     */
    public function handle(ProjectService $projectService): int
    {
        $isApply = (bool) $this->option('apply');
        $isDryRun = (bool) $this->option('dry-run') || !$isApply;
        $isForce = (bool) $this->option('force');
        $isClearUnverified = (bool) $this->option('clear-unverified');

        $this->info("=== DEVArchitect: Deteksi Dialek Database Proyek ===");
        if ($isDryRun) {
            $this->warn("Mode: DRY-RUN (Data hanya diinspeksi, tidak ada perubahan yang disimpan).");
        } else {
            $this->info("Mode: APPLY (Perubahan akan disimpan ke database).");
        }

        if ($isClearUnverified) {
            $this->warn("Opsi --clear-unverified aktif: Dialek tanpa bukti konkrit akan direset menjadi (null).");
        }

        $projects = Project::orderBy('created_at', 'asc')->get();

        if ($projects->isEmpty()) {
            $this->line("Tidak ada proyek yang terdaftar di database.");
            return Command::SUCCESS;
        }

        $rows = [];
        $updatedCount = 0;
        $clearedCount = 0;
        $skippedCount = 0;

        foreach ($projects as $project) {
            $detected = $projectService->detectDatabaseDialect($project->absolute_path);
            $currentDialect = $project->database_dialect?->value;
            $detectedDialect = $detected?->value;

            $status = 'Tidak Berubah';
            $willUpdate = false;
            $willClear = false;

            if ($currentDialect === null) {
                if ($detectedDialect !== null) {
                    $status = $isApply ? 'Diperbarui' : 'Akan Diperbarui';
                    $willUpdate = true;
                } else {
                    $status = 'Bukti Tidak Ditemukan';
                }
            } else {
                // Proyek sudah memiliki dialek tersimpan
                if ($detectedDialect !== null && $detectedDialect !== $currentDialect) {
                    if ($isForce) {
                        $status = $isApply ? 'Diperbarui (Force)' : 'Akan Diperbarui (Force)';
                        $willUpdate = true;
                    } else {
                        $status = "Sudah Ada ({$currentDialect}, terdeteksi {$detectedDialect})";
                        $skippedCount++;
                    }
                } elseif ($detectedDialect === null) {
                    // Kasus kritis: proyek memiliki dialek (misal default 'mysql' lama) tetapi TIDAK ada bukti di filesystem
                    if ($isClearUnverified) {
                        $status = $isApply ? 'Direset ke (null)' : 'Akan Direset ke (null) (Tanpa Bukti)';
                        $willClear = true;
                    } elseif ($isForce || $isDryRun) {
                        $status = "<comment>PERLU REVIEW: {$currentDialect} tanpa bukti</comment>";
                        $skippedCount++;
                    } else {
                        $status = "Sudah Ada Dialek (Tanpa Bukti)";
                        $skippedCount++;
                    }
                } else {
                    // Dialek saat ini sama dengan dialek terdeteksi
                    $status = "Cocok ({$currentDialect})";
                    $skippedCount++;
                }
            }

            if ($willUpdate) {
                if ($isApply) {
                    $project->database_dialect = $detected;
                    $project->save();
                }
                $updatedCount++;
            } elseif ($willClear) {
                if ($isApply) {
                    $confirmed = true;
                    if ($this->input->isInteractive()) {
                        $confirmed = $this->confirm("Reset dialek proyek [{$project->project_name}] dari '{$currentDialect}' menjadi null?", true);
                    }
                    if ($confirmed) {
                        $project->database_dialect = null;
                        $project->save();
                        $clearedCount++;
                    } else {
                        $status = 'Dibatalkan Pengguna';
                    }
                } else {
                    $clearedCount++;
                }
            }

            $rows[] = [
                'id' => substr($project->id, 0, 8) . '...',
                'name' => $project->project_name,
                'framework' => $project->framework_type?->value ?? '-',
                'current' => $currentDialect ?? '(null)',
                'detected' => $detectedDialect ?? '(null)',
                'status' => $status,
            ];
        }

        $this->table(
            ['ID', 'Nama Proyek', 'Framework', 'Dialek Saat Ini', 'Dialek Terdeteksi', 'Status'],
            $rows
        );

        $this->newLine();
        if ($isDryRun) {
            $this->info("[DRY-RUN SELESAI] Ditemukan {$updatedCount} proyek untuk diperbarui dan {$clearedCount} proyek tanpa bukti untuk direset.");
            $this->line("Jalankan dengan opsi --apply untuk menerapkan: <comment>php artisan projects:detect-dialects --apply</comment>");
            if ($clearedCount > 0 && !$isClearUnverified) {
                $this->line("Untuk mereset dialek tanpa bukti, tambahkan --clear-unverified: <comment>php artisan projects:detect-dialects --apply --clear-unverified</comment>");
            }
        } else {
            $this->info("[APPLY SELESAI] Berhasil memperbarui dialek pada {$updatedCount} proyek, mereset {$clearedCount} proyek tanpa bukti. ({$skippedCount} proyek dilewati)");
        }

        return Command::SUCCESS;
    }
}
