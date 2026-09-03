<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Enums\TargetFramework;
use App\Models\Generation;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

class MigrationInjectorService
{
    /**
     * Menyuntikkan (menulis) seluruh file skema/migrasi draft ke proyek target secara atomik.
     *
     * @return array{success: bool, written_files: array<string>, message: string}
     */
    public function inject(Generation $generation, Project $project): array
    {
        $schemaFiles = $generation->migration_files ?? [];
        if (empty($schemaFiles)) {
            throw new RuntimeException("Tidak ada file skema/migrasi pada draft ini.");
        }

        $framework = $generation->target_framework ?? $project->framework_type ?? TargetFramework::LARAVEL;
        $targetDir = $this->resolveTargetDirectory($project, $framework);

        // Buat folder tujuan jika belum ada (misal folder prisma/ atau src/db/)
        if (!File::isDirectory($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        $writtenFiles = [];
        $baseTimestamp = Carbon::now();
        $counter = 0;

        try {
            foreach ($schemaFiles as $fileData) {
                $rawFilename = $fileData['filename'] ?? "schema_{$counter}.{$framework->fileExtension()}";
                
                // Khusus Laravel, berikan timestamp Y_m_d_His di awal nama file
                if ($framework === TargetFramework::LARAVEL) {
                    $timestamp = $baseTimestamp->copy()->addSeconds($counter)->format('Y_m_d_His');
                    $cleanFilename = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $rawFilename);
                    $finalFilename = "{$timestamp}_{$cleanFilename}";
                } else {
                    $finalFilename = $rawFilename;
                }

                $targetPath = $targetDir . DIRECTORY_SEPARATOR . $finalFilename;

                File::put($targetPath, $fileData['content']);
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
                    @unlink($filePath);
                }
            }

            throw new RuntimeException("Gagal menyuntikkan berkas skema: " . $e->getMessage());
        }
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
