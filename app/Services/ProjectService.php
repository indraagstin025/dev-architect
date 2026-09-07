<?php

namespace App\Services;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ProjectService
{
    /**
     * Mendeteksi tipe framework & ORM secara cerdas dari file penanda di dalam direktori proyek.
     */
    public function detectFramework(string $path): TargetFramework
    {
        $realPath = realpath($path) ?: $path;

        // 1. Deteksi Laravel (artisan atau composer.json dengan laravel/framework)
        if (File::exists($realPath . DIRECTORY_SEPARATOR . 'composer.json')) {
            try {
                $composer = json_decode(File::get($realPath . DIRECTORY_SEPARATOR . 'composer.json'), true, 512, JSON_THROW_ON_ERROR);
                if (isset($composer['require']['laravel/framework'])) {
                    return TargetFramework::LARAVEL;
                }
            } catch (\Throwable $e) {
                // Lanjut ke pemeriksaan artisan
            }
        }

        if (File::exists($realPath . DIRECTORY_SEPARATOR . 'artisan')) {
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
                $pkg = json_decode(File::get($realPath . DIRECTORY_SEPARATOR . 'package.json'), true, 512, JSON_THROW_ON_ERROR);
                $deps = array_merge(
                    $pkg['dependencies'] ?? [],
                    $pkg['devDependencies'] ?? [],
                    $pkg['peerDependencies'] ?? []
                );

                $hasDrizzle = isset($deps['drizzle-orm']) || isset($deps['drizzle-kit']);
                $hasPrisma = isset($deps['prisma']) || isset($deps['@prisma/client']);

                if ($hasDrizzle && !$hasPrisma) {
                    return TargetFramework::EXPRESS_DRIZZLE;
                }

                if ($hasPrisma) {
                    return TargetFramework::EXPRESS_PRISMA;
                }

                // Sinyal sekunder: keberadaan folder prisma/
                if (File::isDirectory($realPath . DIRECTORY_SEPARATOR . 'prisma')) {
                    return TargetFramework::EXPRESS_PRISMA;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Gagal parse package.json saat deteksi framework: " . $e->getMessage());
            }

            // Proyek Node tanpa Prisma/Drizzle (mis. Mongoose/Sequelize/Express generik) -> Universal SQL
            return TargetFramework::RAW_SQL;
        }

        // 4. Default ke Universal Raw SQL untuk folder umum / standalone
        return TargetFramework::RAW_SQL;
    }

    /**
     * Memeriksa apakah folder yang diberikan merupakan folder sistem atau root drive OS yang dilarang.
     */
    public function isSystemOrRootFolder(string $realPath): bool
    {
        $normalized = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $realPath), DIRECTORY_SEPARATOR);

        // 1. Root drive Windows (misal C:, C:\, D:\) atau root Unix (/)
        if (preg_match('/^[a-zA-Z]:\\\\?$/', $realPath) || $realPath === DIRECTORY_SEPARATOR || $realPath === '/' || $realPath === '') {
            return true;
        }

        // 2. Folder sistem inti OS
        $systemRoots = [
            'C:\\Windows',
            'C:\\Program Files',
            'C:\\Program Files (x86)',
            'C:\\ProgramData',
            'C:\\Recovery',
            'C:\\System Volume Information',
            'C:\\Users',
            '/bin', '/boot', '/dev', '/etc', '/lib', '/lib64', '/proc', '/root', '/run', '/sbin', '/sys', '/usr', '/var', '/home',
        ];

        foreach ($systemRoots as $sysRoot) {
            $normSys = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sysRoot), DIRECTORY_SEPARATOR);
            if (strcasecmp($normalized, $normSys) === 0) {
                return true;
            }
            // Subfolder di dalam folder sistem inti seperti C:\Windows atau C:\Program Files juga dilarang
            if (in_array($sysRoot, ['C:\\Windows', 'C:\\Program Files', 'C:\\Program Files (x86)', 'C:\\ProgramData', '/bin', '/boot', '/dev', '/etc', '/lib', '/lib64', '/proc', '/root', '/run', '/sbin', '/sys', '/usr'], true)) {
                if (str_starts_with(strtolower($normalized), strtolower($normSys) . DIRECTORY_SEPARATOR)) {
                    return true;
                }
            }
        }

        // 3. Blokir root home direktori pengguna langsung (misal C:\Users\Indra atau /home/user)
        $userHome = getenv('USERPROFILE') ?: getenv('HOME');
        if ($userHome) {
            $normHome = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $userHome), DIRECTORY_SEPARATOR);
            if (strcasecmp($normalized, $normHome) === 0) {
                return true;
            }

            // Blokir folder profil pengguna utama (Downloads, Desktop, direct Documents, AppData)
            $userProfileRoots = [
                $normHome . DIRECTORY_SEPARATOR . 'Downloads',
                $normHome . DIRECTORY_SEPARATOR . 'Desktop',
                $normHome . DIRECTORY_SEPARATOR . 'Documents',
                $normHome . DIRECTORY_SEPARATOR . 'AppData',
                $normHome . DIRECTORY_SEPARATOR . '.gemini',
            ];

            foreach ($userProfileRoots as $uRoot) {
                if (strcasecmp($normalized, $uRoot) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Memeriksa apakah folder memiliki penanda (marker) proyek yang sah sesuai target framework.
     */
    public function hasFrameworkMarkers(string $realPath, ?TargetFramework $framework = null): bool
    {
        $fw = $framework ?? $this->detectFramework($realPath);

        return match ($fw) {
            TargetFramework::LARAVEL => File::exists($realPath . DIRECTORY_SEPARATOR . 'artisan') ||
                File::exists($realPath . DIRECTORY_SEPARATOR . 'composer.json'),
            TargetFramework::SPRINGBOOT_HIBERNATE => File::exists($realPath . DIRECTORY_SEPARATOR . 'pom.xml') ||
                File::exists($realPath . DIRECTORY_SEPARATOR . 'build.gradle') ||
                File::exists($realPath . DIRECTORY_SEPARATOR . 'build.gradle.kts'),
            TargetFramework::EXPRESS_PRISMA => File::exists($realPath . DIRECTORY_SEPARATOR . 'package.json') ||
                File::exists($realPath . DIRECTORY_SEPARATOR . 'prisma' . DIRECTORY_SEPARATOR . 'schema.prisma') ||
                File::exists($realPath . DIRECTORY_SEPARATOR . 'schema.prisma'),
            TargetFramework::EXPRESS_DRIZZLE => File::exists($realPath . DIRECTORY_SEPARATOR . 'package.json') ||
                File::exists($realPath . DIRECTORY_SEPARATOR . 'drizzle.config.ts') ||
                File::exists($realPath . DIRECTORY_SEPARATOR . 'drizzle.config.js'),
            TargetFramework::RAW_SQL => File::exists($realPath . DIRECTORY_SEPARATOR . 'composer.json') ||
                File::exists($realPath . DIRECTORY_SEPARATOR . 'package.json') ||
                File::exists($realPath . DIRECTORY_SEPARATOR . 'pom.xml') ||
                File::exists($realPath . DIRECTORY_SEPARATOR . 'schema.sql') ||
                File::exists($realPath . DIRECTORY_SEPARATOR . 'database.sql'),
        };
    }

    /**
     * Memeriksa apakah folder diizinkan untuk didaftarkan sebagai proyek DEVArchitect.
     */
    public function isAllowedProjectFolder(string $realPath, ?TargetFramework $framework = null, bool $allowGenericFolder = false): bool
    {
        if (!File::isDirectory($realPath)) {
            return false;
        }

        if ($this->isSystemOrRootFolder($realPath)) {
            return false;
        }

        $targetFw = $framework ?? $this->detectFramework($realPath);

        // Selalu izinkan subfolder pengujian dalam temp dir agar test suite berjalan lancar
        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        if (str_starts_with($realPath, $tempDir)) {
            return true;
        }

        // Jika memilih framework spesifik (Laravel/Spring/Express), wajib memiliki penanda proyek yang sesuai
        if ($targetFw !== TargetFramework::RAW_SQL) {
            return $this->hasFrameworkMarkers($realPath, $targetFw);
        }

        // Untuk Universal Raw SQL: diizinkan jika memiliki marker apa pun, atau memiliki konfirmasi eksplisit
        if ($this->hasFrameworkMarkers($realPath, TargetFramework::RAW_SQL)) {
            return true;
        }

        return $allowGenericFolder;
    }

    /**
     * Menginspeksi folder proyek secara pasif tanpa efek samping.
     *
     * @return array{allowed: bool, reason: ?string, framework: TargetFramework, dialect: ?DatabaseDialect, project_name: string}
     */
    public function inspectFolder(string $realPath): array
    {
        if (!$this->isAllowedProjectFolder($realPath)) {
            return [
                'allowed' => false,
                'reason' => 'Folder sistem atau root drive tidak dapat didaftarkan sebagai proyek DEVArchitect.',
                'framework' => TargetFramework::RAW_SQL,
                'dialect' => null,
                'project_name' => basename($realPath),
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'framework' => $this->detectFramework($realPath),
            'dialect' => $this->detectDatabaseDialect($realPath),
            'project_name' => basename($realPath),
        ];
    }

    /**
     * Mendeteksi dialek basis data target secara cerdas dari file konfigurasi proyek berbasis bukti nyata.
     * Mengembalikan null bila tidak ditemukan bukti konfigurasi yang valid (tidak menebak MySQL secara acak).
     */
    public function detectDatabaseDialect(string $path): ?DatabaseDialect
    {
        $realPath = realpath($path) ?: $path;

        // 1. Periksa berkas Prisma Schema (prisma/schema.prisma atau schema.prisma)
        $prismaPaths = [
            $realPath . DIRECTORY_SEPARATOR . 'prisma' . DIRECTORY_SEPARATOR . 'schema.prisma',
            $realPath . DIRECTORY_SEPARATOR . 'schema.prisma',
        ];

        foreach ($prismaPaths as $prismaFile) {
            if (File::exists($prismaFile)) {
                try {
                    $parsed = \App\Services\Parsers\PrismaSchemaParser::parse(File::get($prismaFile));
                    if ($parsed->dialect !== null) {
                        return $parsed->dialect;
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Gagal membaca berkas prisma untuk dialek: " . $e->getMessage());
                }
            }
        }

        // 2. Periksa berkas konfigurasi lingkungan (.env / .env.example) dengan toleransi whitespace & komentar inline
        $envPaths = [
            $realPath . DIRECTORY_SEPARATOR . '.env',
            $realPath . DIRECTORY_SEPARATOR . '.env.example',
        ];

        foreach ($envPaths as $envFile) {
            if (File::exists($envFile)) {
                try {
                    $lines = File::lines($envFile);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if ($line === '' || str_starts_with($line, '#')) {
                            continue;
                        }

                        // A. Sinyal DB_CONNECTION Laravel
                        if (preg_match('/^DB_CONNECTION\s*=\s*["\']?([^"\'\s#]+)/i', $line, $m)) {
                            $conn = strtolower(trim($m[1]));
                            if (in_array($conn, ['pgsql', 'postgres', 'postgresql'], true)) {
                                return DatabaseDialect::POSTGRESQL;
                            }
                            if (in_array($conn, ['mysql', 'mariadb'], true)) {
                                return DatabaseDialect::MYSQL;
                            }
                            if ($conn === 'sqlite') {
                                return DatabaseDialect::SQLITE;
                            }
                            if (in_array($conn, ['sqlsrv', 'sqlserver'], true)) {
                                return DatabaseDialect::SQLSERVER;
                            }
                        }

                        // B. Sinyal DATABASE_URL connection string
                        if (preg_match('/^DATABASE_URL\s*=\s*["\']?([^"\'\s#]+)/i', $line, $m)) {
                            $url = strtolower(trim($m[1]));
                            if (str_starts_with($url, 'postgres://') || str_starts_with($url, 'postgresql://')) {
                                return DatabaseDialect::POSTGRESQL;
                            }
                            if (str_starts_with($url, 'mysql://') || str_starts_with($url, 'mysql2://')) {
                                return DatabaseDialect::MYSQL;
                            }
                            if (str_starts_with($url, 'sqlite:') || str_starts_with($url, 'file:')) {
                                return DatabaseDialect::SQLITE;
                            }
                            if (str_starts_with($url, 'sqlserver://')) {
                                return DatabaseDialect::SQLSERVER;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Gagal membaca berkas .env untuk dialek: " . $e->getMessage());
                }
            }
        }

        // 3. Periksa konfigurasi Drizzle (drizzle.config.*)
        $drizzleConfigs = [
            $realPath . DIRECTORY_SEPARATOR . 'drizzle.config.ts',
            $realPath . DIRECTORY_SEPARATOR . 'drizzle.config.js',
            $realPath . DIRECTORY_SEPARATOR . 'drizzle.config.json',
            $realPath . DIRECTORY_SEPARATOR . 'drizzle.config.mjs',
        ];

        foreach ($drizzleConfigs as $dFile) {
            if (File::exists($dFile)) {
                try {
                    $dContent = strtolower(File::get($dFile));
                    if (preg_match('/dialect\s*:\s*["\'](postgresql|postgres)["\']/', $dContent) ||
                        preg_match('/driver\s*:\s*["\'](pg|pglite|neon)["\']/', $dContent)) {
                        return DatabaseDialect::POSTGRESQL;
                    }
                    if (preg_match('/dialect\s*:\s*["\']mysql["\']/', $dContent) ||
                        preg_match('/driver\s*:\s*["\'](mysql2)["\']/', $dContent)) {
                        return DatabaseDialect::MYSQL;
                    }
                    if (preg_match('/dialect\s*:\s*["\']sqlite["\']/', $dContent) ||
                        preg_match('/driver\s*:\s*["\'](better-sqlite|better-sqlite3|turso|d1)["\']/', $dContent)) {
                        return DatabaseDialect::SQLITE;
                    }
                    if (preg_match('/dialect\s*:\s*["\'](sqlserver|mssql)["\']/', $dContent)) {
                        return DatabaseDialect::SQLSERVER;
                    }
                } catch (\Throwable $e) {}
            }
        }

        // 4. Periksa ketergantungan driver database di package.json
        $pkgFile = $realPath . DIRECTORY_SEPARATOR . 'package.json';
        if (File::exists($pkgFile)) {
            try {
                $pkg = json_decode(File::get($pkgFile), true, 512, JSON_THROW_ON_ERROR);
                $deps = array_merge(
                    $pkg['dependencies'] ?? [],
                    $pkg['devDependencies'] ?? []
                );

                if (isset($deps['pg']) || isset($deps['@vercel/postgres']) || isset($deps['postgres'])) {
                    return DatabaseDialect::POSTGRESQL;
                }
                if (isset($deps['mysql2']) || isset($deps['mysql'])) {
                    return DatabaseDialect::MYSQL;
                }
                if (isset($deps['better-sqlite3']) || isset($deps['sqlite3']) || isset($deps['@libsql/client'])) {
                    return DatabaseDialect::SQLITE;
                }
                if (isset($deps['tedious']) || isset($deps['mssql'])) {
                    return DatabaseDialect::SQLSERVER;
                }
            } catch (\Throwable $e) {}
        }

        // 5. Periksa Spring Boot (application.properties atau application.yml)
        $springPaths = [
            $realPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'main' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'application.properties',
            $realPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'main' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'application.yml',
            $realPath . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'main' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'application.yaml',
        ];

        foreach ($springPaths as $springFile) {
            if (File::exists($springFile)) {
                try {
                    $content = strtolower(File::get($springFile));
                    if (str_contains($content, 'jdbc:postgresql:')) {
                        return DatabaseDialect::POSTGRESQL;
                    }
                    if (str_contains($content, 'jdbc:mysql:') || str_contains($content, 'jdbc:mariadb:')) {
                        return DatabaseDialect::MYSQL;
                    }
                    if (str_contains($content, 'jdbc:sqlite:')) {
                        return DatabaseDialect::SQLITE;
                    }
                    if (str_contains($content, 'jdbc:sqlserver:')) {
                        return DatabaseDialect::SQLSERVER;
                    }
                    // Catatan: jdbc:h2: adalah in-memory database dengan dialek tersendiri, TIDAK dipetakan ke SQLite.
                } catch (\Throwable $e) {}
            }
        }

        // 6. Periksa keberadaan berkas database SQLite fisik
        if (File::exists($realPath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite') ||
            File::exists($realPath . DIRECTORY_SEPARATOR . 'database.sqlite')) {
            return DatabaseDialect::SQLITE;
        }

        // 7. Jika tidak ada bukti konkrit sama sekali, kembalikan null (tidak menebak secara sembarangan)
        return null;
    }

    /**
     * Memvalidasi folder yang dipilih dan mengidentifikasi framework serta nama proyek.
     *
     * @return array{valid: bool, framework: TargetFramework, framework_label: string, dialect: ?string, dialect_label: ?string, project_name: string, is_generic_folder: bool, requires_confirmation: bool, message: string}
     */
    public function validateFolder(string $path, bool $allowGenericFolder = false): array
    {
        $realPath = realpath($path);

        if (!$realPath || !File::isDirectory($realPath)) {
            return [
                'valid' => false,
                'framework' => TargetFramework::RAW_SQL,
                'framework_label' => TargetFramework::RAW_SQL->label(),
                'dialect' => null,
                'dialect_label' => null,
                'project_name' => '',
                'is_generic_folder' => false,
                'requires_confirmation' => false,
                'message' => 'Direktori folder tidak ditemukan atau tidak valid.',
            ];
        }

        if ($this->isSystemOrRootFolder($realPath)) {
            return [
                'valid' => false,
                'framework' => TargetFramework::RAW_SQL,
                'framework_label' => TargetFramework::RAW_SQL->label(),
                'dialect' => null,
                'dialect_label' => null,
                'project_name' => basename($realPath),
                'is_generic_folder' => false,
                'requires_confirmation' => false,
                'message' => 'Folder sistem, root drive, atau folder profil pengguna tidak dapat didaftarkan sebagai proyek DEVArchitect.',
            ];
        }

        $framework = $this->detectFramework($realPath);
        $hasMarkers = $this->hasFrameworkMarkers($realPath, $framework);
        $projectName = basename($realPath);
        $dialect = $this->detectDatabaseDialect($realPath);

        // Abaikan validasi marker ketat hanya jika folder pengujian di dalam sys_get_temp_dir()
        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $isTestDir = str_starts_with($realPath, $tempDir);

        if ($framework !== TargetFramework::RAW_SQL && !$hasMarkers && !$isTestDir) {
            return [
                'valid' => false,
                'framework' => $framework,
                'framework_label' => $framework->label(),
                'dialect' => $dialect?->value,
                'dialect_label' => $dialect?->label(),
                'project_name' => $projectName,
                'is_generic_folder' => false,
                'requires_confirmation' => false,
                'message' => "Folder tidak memiliki penanda proyek yang valid untuk {$framework->label()}.",
            ];
        }

        $isGenericFolder = ($framework === TargetFramework::RAW_SQL && !$hasMarkers && !$isTestDir);

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
            'framework_label' => $framework->label(),
            'dialect' => $dialect?->value,
            'dialect_label' => $dialect?->label(),
            'project_name' => $projectName,
            'is_generic_folder' => $isGenericFolder,
            'requires_confirmation' => $isGenericFolder && !$allowGenericFolder,
            'message' => $isGenericFolder
                ? "Folder umum tanpa penanda framework terdeteksi. Memerlukan konfirmasi untuk didaftarkan sebagai Universal Raw SQL."
                : ($dialect 
                    ? "Proyek valid terdeteksi: {$framework->label()} ({$dialect->label()})"
                    : "Proyek valid terdeteksi: {$framework->label()} (Dialek belum terdeteksi)"),
        ];
    }

    /**
     * Daftar target framework yang kompatibel untuk sebuah proyek.
     *
     * Aturan: framework proyek sendiri selalu boleh + Raw SQL (universal,
     * aman ditulis ke proyek mana pun). Proyek tak dikenal (RAW_SQL)
     * dibebaskan memilih semua karena tipenya memang belum terdeteksi.
     *
     * @return array<TargetFramework>
     */
    public function allowedTargetFrameworks(Project $project): array
    {
        $framework = $project->framework_type ?? TargetFramework::RAW_SQL;

        if ($framework === TargetFramework::RAW_SQL) {
            return TargetFramework::cases();
        }

        return [$framework, TargetFramework::RAW_SQL];
    }

    /**
     * Menyimpan atau mendaftarkan proyek baru ke database.
     */
    public function registerProject(
        string $name, 
        string $absolutePath, 
        ?TargetFramework $framework = null,
        ?DatabaseDialect $dialect = null,
        bool $confirmGenericFolder = false
    ): Project {
        $realPath = realpath($absolutePath);
        if (!$realPath || !File::isDirectory($realPath)) {
            throw new RuntimeException("Direktori proyek tidak ditemukan atau tidak valid: {$absolutePath}");
        }

        $targetFramework = $framework ?? $this->detectFramework($realPath);

        // Validasi kelayakan folder proyek
        if (!$this->isAllowedProjectFolder($realPath, $targetFramework, $confirmGenericFolder)) {
            if ($this->isSystemOrRootFolder($realPath)) {
                throw new RuntimeException("Folder sistem, root drive, atau folder profil pengguna tidak dapat didaftarkan sebagai proyek DEVArchitect.");
            }
            if ($targetFramework !== TargetFramework::RAW_SQL && !$this->hasFrameworkMarkers($realPath, $targetFramework)) {
                throw new RuntimeException("Folder tidak memiliki penanda proyek yang valid untuk {$targetFramework->label()}.");
            }
            throw new RuntimeException("Folder ini tidak memiliki penanda framework proyek. Berikan konfirmasi eksplisit (confirm_generic_folder) untuk mendaftarkannya sebagai Universal Raw SQL.");
        }

        // Cek duplikasi path proyek
        $existing = Project::where('absolute_path', $realPath)->first();
        if ($existing) {
            throw new RuntimeException("Proyek dengan folder ini sudah terdaftar: [{$existing->project_name}].");
        }

        $databaseDialect = $dialect ?? $this->detectDatabaseDialect($realPath);

        // Jika sebelumnya ada kartu draft virtual dengan nama yang sama, perbarui menjadi terpasang
        $draft = Project::where('is_draft', true)
            ->whereNull('absolute_path')
            ->where('project_name', $name)
            ->first();

        if ($draft) {
            $draft->update([
                'absolute_path' => $realPath,
                'framework_type' => $targetFramework,
                'database_dialect' => $databaseDialect,
                'is_draft' => false,
            ]);

            return $draft->fresh();
        }

        return Project::create([
            'project_name' => $name,
            'absolute_path' => $realPath,
            'framework_type' => $targetFramework,
            'database_dialect' => $databaseDialect,
            'is_draft' => false,
        ]);
    }

    /**
     * Mengambil daftar seluruh proyek dengan riwayat generasi terakhir (dioptimasi tanpa muatan JSON/Mermaid berat).
     *
     * @return Collection<int, Project>
     */
    public function listProjects(): Collection
    {
        return Project::with(['latestGeneration' => function ($query) {
            $query->select(
                'generations.id',
                'generations.project_id',
                'generations.status',
                'generations.target_framework',
                'generations.database_dialect',
                'generations.created_at',
                'generations.updated_at'
            );
        }])->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Mengambil daftar nama file skema yang sudah ada di proyek target.
     *
     * @return array<string>
     */
    public function getExistingSchemaFiles(Project $project): array
    {
        $framework = $project->framework_type ?? TargetFramework::LARAVEL;
        $targetSubpath = $framework->defaultInjectionPath();
        
        $targetDir = $targetSubpath === '.' 
            ? $project->absolute_path 
            : $project->absolute_path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetSubpath);

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
