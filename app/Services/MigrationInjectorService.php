<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Enums\TargetFramework;
use App\Models\Generation;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class MigrationInjectorService
{
    /**
     * Menyuntikkan (menulis) seluruh file skema/migrasi draft ke proyek target secara atomik dan aman.
     *
     * @return array{success: bool, written_files: array<string>, message: string}
     */
    public function inject(Generation $generation, Project $project, bool $allowOverwrite = false): array
    {
        $schemaFiles = $generation->migration_files ?? [];
        if (empty($schemaFiles)) {
            throw new RuntimeException("Tidak ada file skema/migrasi pada draft ini.");
        }

        if (count($schemaFiles) > 30) {
            throw new RuntimeException("Maksimal 30 file skema yang dapat disuntikkan sekaligus.");
        }

        $realBase = realpath($project->absolute_path);
        if (!$realBase || !File::isDirectory($realBase)) {
            throw new RuntimeException("Direktori proyek tidak valid atau tidak ditemukan: {$project->absolute_path}");
        }

        $framework = $generation->target_framework ?? $project->framework_type ?? TargetFramework::LARAVEL;
        $targetDir = $this->resolveTargetDirectory($project, $framework);

        // Buat folder tujuan jika belum ada
        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        $realTarget = realpath($targetDir);
        // Guard Path Traversal: Pastikan target directory berada di dalam root proyek
        if (!$realTarget || !Str::startsWith($realTarget, $realBase)) {
            throw new RuntimeException("Pelanggaran keamanan: Direktori target berada di luar root proyek.");
        }

        $writtenFiles = [];
        $baseTimestamp = Carbon::now();
        $counter = 0;

        try {
            foreach ($schemaFiles as $fileData) {
                $rawFilename = $fileData['filename'] ?? "schema_{$counter}.{$framework->fileExtension()}";
                $content = $fileData['content'] ?? '';

                if (empty(trim($content))) {
                    throw new RuntimeException("Isi file [{$rawFilename}] tidak boleh kosong.");
                }

                if (strlen($content) > 200_000) {
                    throw new RuntimeException("Ukuran konten file [{$rawFilename}] melebihi batas 200KB.");
                }

                // Sanitasi nama file untuk mencegah path traversal
                $cleanFilename = $this->sanitizeFilename($rawFilename, $framework);

                // Khusus Laravel, berikan timestamp Y_m_d_His di awal nama file
                if ($framework === TargetFramework::LARAVEL) {
                    $timestamp = $baseTimestamp->copy()->addSeconds($counter)->format('Y_m_d_His');
                    $stripped = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $cleanFilename);
                    $finalFilename = "{$timestamp}_{$stripped}";
                } else {
                    $finalFilename = $cleanFilename;
                }

                $targetPath = $realTarget . DIRECTORY_SEPARATOR . $finalFilename;

                // Verifikasi file akhir tetap berada di dalam realTarget
                if (!Str::startsWith(dirname($targetPath), $realTarget)) {
                    throw new RuntimeException("Pelanggaran keamanan path pada nama file: {$rawFilename}");
                }

                if (File::exists($targetPath) && !$allowOverwrite) {
                    throw new RuntimeException("File sudah ada: [{$finalFilename}]. Injeksi ditolak untuk mencegah penimpaan file.");
                }

                File::put($targetPath, $content);
                $writtenFiles[] = $targetPath;

                $counter++;
            }

            // Update status draft menjadi Injected di database
            $generation->update([
                'status' => GenerationStatus::INJECTED,
            ]);

            return [
                'success' => true,
                'written_files' => $writtenFiles,
                'message' => count($writtenFiles) . " berkas skema ({$framework->label()}) berhasil disuntikkan ke proyek.",
            ];

        } catch (\Throwable $e) {
            // Rollback seluruh file yang sempat tertulis jika terjadi kegagalan
            foreach ($writtenFiles as $filePath) {
                if (File::exists($filePath)) {
                    File::delete($filePath);
                }
            }

            throw new RuntimeException("Gagal menyuntikkan berkas skema: " . $e->getMessage());
        }
    }

    /**
     * Sanitasi nama berkas untuk mencegah path traversal dan karakter berbahaya.
     */
    public function sanitizeFilename(string $raw, TargetFramework $fw): string
    {
        // Ambil nama dasar (menghilangkan path ../ atau c:/)
        $base = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $raw));
        
        // Hapus null bytes dan karakter non-whitelist
        $cleaned = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $base);
        $cleaned = preg_replace('/\.+/', '.', $cleaned); // hindari double dot
        $cleaned = trim($cleaned, '._-');

        if (empty($cleaned)) {
            $cleaned = "schema_" . time();
        }

        // Batasi panjang maksimal 120 karakter
        $cleaned = Str::limit($cleaned, 120, '');

        // Pastikan ekstensi sesuai dengan framework
        $expectedExt = '.' . $fw->fileExtension();
        if (!Str::endsWith(strtolower($cleaned), strtolower($expectedExt))) {
            $cleaned .= $expectedExt;
        }

        return $cleaned;
    }

    /**
     * Pratinjau injeksi tanpa menulis apa pun (TASK-802).
     * Dipakai modal konfirmasi: direktori tujuan, file existing, dan
     * nama file usulan (untuk Laravel, nama final mendapat prefix
     * timestamp saat eksekusi sehingga tidak menimpa file existing).
     *
     * @return array{valid: bool, message: string, target_directory: ?string, existing_files: array<string>, proposed_files: array<string>, already_injected: bool}
     */
    public function preview(Generation $generation, Project $project): array
    {
        $schemaFiles = $generation->migration_files ?? [];

        if (empty($schemaFiles)) {
            return [
                'valid' => false,
                'message' => 'Tidak ada file skema/migrasi pada draft ini.',
                'target_directory' => null,
                'existing_files' => [],
                'proposed_files' => [],
                'already_injected' => $generation->status === GenerationStatus::INJECTED,
            ];
        }

        $framework = $generation->target_framework ?? $project->framework_type ?? TargetFramework::LARAVEL;
        $targetDir = $this->resolveTargetDirectory($project, $framework);

        $proposed = [];
        foreach ($schemaFiles as $i => $fileData) {
            $raw = $fileData['filename'] ?? "schema_{$i}.{$framework->fileExtension()}";
            $proposed[] = $this->sanitizeFilename($raw, $framework);
        }

        $existing = File::isDirectory($targetDir)
            ? array_map(fn ($f) => $f->getFilename(), File::files($targetDir))
            : [];

        return [
            'valid' => true,
            'message' => count($proposed) . " berkas akan ditulis ke {$targetDir}.",
            'target_directory' => $targetDir,
            'existing_files' => $existing,
            'proposed_files' => $proposed,
            'already_injected' => $generation->status === GenerationStatus::INJECTED,
        ];
    }

    /**
     * Menentukan direktori fisik tujuan penulisan file berdasarkan framework.
     */
    protected function resolveTargetDirectory(Project $project, TargetFramework $framework): string
    {
        $subpath = $framework->defaultInjectionPath();
        
        if ($subpath === '.') {
            return $project->absolute_path;
        }

        return $project->absolute_path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $subpath);
    }
}
