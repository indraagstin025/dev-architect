<?php

namespace App\Services;

use App\Enums\TargetFramework;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;

class ProjectService
{
    /**
     * Mendeteksi tipe framework & ORM secara cerdas dari file penanda di dalam direktori proyek.
     */
    public function detectFramework(string $path): TargetFramework
    {
        $realPath = realpath($path) ?: $path;

        // 1. Deteksi Laravel (artisan & composer.json)
        if (File::exists($realPath . DIRECTORY_SEPARATOR . 'artisan') && 
            File::exists($realPath . DIRECTORY_SEPARATOR . 'composer.json')) {
            return TargetFramework::LARAVEL;
        }

        // 2. Deteksi Java Spring Boot (pom.xml atau build.gradle)
        if (File::exists($realPath . DIRECTORY_SEPARATOR . 'pom.xml') || 
            File::exists($realPath . DIRECTORY_SEPARATOR . 'build.gradle') ||
            File::exists($realPath . DIRECTORY_SEPARATOR . 'build.gradle.kts')) {
            return TargetFramework::SPRINGBOOT_HIBERNATE;
        }

        // 3. Deteksi Express.js / Node.js (package.json)
        if (File::exists($realPath . DIRECTORY_SEPARATOR . 'package.json')) {
            try {
                $pkgContent = File::get($realPath . DIRECTORY_SEPARATOR . 'package.json');
                if (str_contains($pkgContent, 'drizzle-orm')) {
                    return TargetFramework::EXPRESS_DRIZZLE;
                }
                if (str_contains($pkgContent, 'prisma') || File::isDirectory($realPath . DIRECTORY_SEPARATOR . 'prisma')) {
                    return TargetFramework::EXPRESS_PRISMA;
                }
            } catch (\Throwable $e) {
                // fallback
            }

            return TargetFramework::EXPRESS_PRISMA;
        }

        // 4. Default ke Universal Raw SQL untuk folder umum / standalone
        return TargetFramework::RAW_SQL;
    }

    /**
     * Memvalidasi folder yang dipilih dan mengidentifikasi framework serta nama proyek.
     *
     * @return array{valid: bool, framework: TargetFramework, project_name: string, message: string}
     */
    public function validateFolder(string $path): array
    {
        $realPath = realpath($path);

        if (!$realPath || !File::isDirectory($realPath)) {
            return [
                'valid' => false,
                'framework' => TargetFramework::RAW_SQL,
                'project_name' => '',
                'message' => 'Direktori folder tidak ditemukan atau tidak valid.',
            ];
        }

        $framework = $this->detectFramework($realPath);
        $projectName = basename($realPath);

        // Ekstraksi nama proyek dari composer.json atau package.json bila ada
        try {
            if ($framework === TargetFramework::LARAVEL && File::exists($realPath . '/composer.json')) {
                $composer = json_decode(File::get($realPath . '/composer.json'), true);
                if (!empty($composer['name'])) {
                    $projectName = explode('/', $composer['name'])[1] ?? $composer['name'];
                }
            } elseif (($framework === TargetFramework::EXPRESS_PRISMA || $framework === TargetFramework::EXPRESS_DRIZZLE) && File::exists($realPath . '/package.json')) {
                $pkg = json_decode(File::get($realPath . '/package.json'), true);
                if (!empty($pkg['name'])) {
                    $projectName = $pkg['name'];
                }
            }
        } catch (\Throwable $e) {
            // Abaikan kesalahan baca metadata file
        }

        return [
            'valid' => true,
            'framework' => $framework,
            'project_name' => $projectName,
            'message' => "Proyek valid terdeteksi: {$framework->label()}",
        ];
    }

    /**
     * Menyimpan atau mendaftarkan proyek baru ke database.
     */
    public function registerProject(
        string $name, 
        string $absolutePath, 
        ?TargetFramework $framework = null
    ): Project {
        $realPath = realpath($absolutePath) ?: $absolutePath;
        $frameworkType = $framework ?? $this->detectFramework($realPath);

        return Project::create([
            'project_name' => $name,
            'absolute_path' => $realPath,
            'framework_type' => $frameworkType,
        ]);
    }

    /**
     * Mengambil daftar seluruh proyek dengan riwayat generasi terakhir.
     *
     * @return Collection<int, Project>
     */
    public function listProjects(): Collection
    {
        return Project::with(['latestGeneration'])->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Mengambil daftar nama file skema yang sudah ada di proyek target.
     *
     * @return array<string>
     */
    public function getExistingSchemaFiles(Project $project): array
    {
        $targetSubpath = $project->framework_type->defaultInjectionPath();
        $targetDir = $project->absolute_path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetSubpath);

        if (!File::isDirectory($targetDir)) {
            return [];
        }

        $files = File::files($targetDir);

        return array_map(fn($file) => $file->getFilename(), $files);
    }

    /**
     * Alias backward-compatibility untuk migrasi Laravel.
     */
    public function getExistingMigrations(Project $project): array
    {
        return $this->getExistingSchemaFiles($project);
    }

    /**
     * Menghapus proyek dari dashboard.
     */
    public function deleteProject(string $projectId): bool
    {
        $project = Project::find($projectId);

        return $project ? (bool) $project->delete() : false;
    }
}
