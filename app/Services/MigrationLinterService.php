<?php

namespace App\Services;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;
use App\Models\Project;

class MigrationLinterService
{
    public function __construct(
        protected ProjectService $projectService
    ) {}

    /**
     * Memvalidasi seluruh file skema/migrasi draft terhadap standar framework & dialek database.
     *
     * @param array<array{filename: string, content: string}> $migrationFiles
     * @return array{isValid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validateDraft(
        array $migrationFiles, 
        Project $project, 
        TargetFramework $fw = TargetFramework::LARAVEL,
        DatabaseDialect $dialect = DatabaseDialect::MYSQL
    ): array {
        $errors = [];
        $warnings = [];

        if (empty($migrationFiles)) {
            $errors[] = "Tidak ada berkas skema yang dihasilkan.";
            return ['isValid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        $existingFiles = $this->projectService->getExistingSchemaFiles($project);
        $seenFilenames = [];
        $seenTables = [];

        foreach ($migrationFiles as $index => $file) {
            $filename = $file['filename'] ?? "file_{$index}.{$fw->fileExtension()}";
            $content = $file['content'] ?? '';

            // 1. Cek duplikasi nama berkas dalam draft
            if (in_array(strtolower($filename), $seenFilenames)) {
                $errors[] = "[{$filename}] Terdapat duplikasi nama berkas di dalam rancangan.";
            }
            $seenFilenames[] = strtolower($filename);

            // 2. Cek konten kosong
            if (empty(trim($content))) {
                $errors[] = "[{$filename}] Isi berkas tidak boleh kosong.";
                continue;
            }

            // 2b. Pindai pola berbahaya (anti prompt-injection, semua framework)
            foreach ($this->securityScan($content) as $finding) {
                $errors[] = "[{$filename}] {$finding}";
            }

            // 3. Validasi spesifik per framework
            switch ($fw) {
                case TargetFramework::LARAVEL:
                    if (!str_contains($content, '<?php') || !str_contains($content, 'Schema::create')) {
                        $errors[] = "[{$filename}] Format migrasi Laravel tidak valid (wajib mengandung '<?php' dan 'Schema::create').";
                    }

                    $tableName = $this->extractLaravelTableName($content);
                    if ($tableName) {
                        if (in_array($tableName, $seenTables)) {
                            $warnings[] = "[{$filename}] Tabel '{$tableName}' didefinisikan lebih dari sekali dalam draft.";
                        }
                        $seenTables[] = $tableName;

                        foreach ($existingFiles as $existingFile) {
                            if (str_contains($existingFile, "create_{$tableName}_table")) {
                                $warnings[] = "[{$filename}] Tabel '{$tableName}' kemungkinan sudah memiliki migrasi di proyek ({$existingFile}).";
                            }
                        }
                    }

                    if ($dialect === DatabaseDialect::POSTGRESQL && str_contains($content, '->increments(') && !str_contains($content, '->bigIncrements(')) {
                        $warnings[] = "[{$filename}] Pada PostgreSQL, disarankan menggunakan 'bigIncrements' atau 'uuid' sebagai primary key.";
                    }
                    if ($dialect === DatabaseDialect::MYSQL && str_contains($content, '->jsonb(')) {
                        $warnings[] = "[{$filename}] MySQL tidak mendukung tipe 'jsonb', gunakan 'json()'.";
                    }
                    break;

                case TargetFramework::EXPRESS_PRISMA:
                    if (!str_contains($content, 'model ') && !str_contains($content, 'datasource')) {
                        $errors[] = "[{$filename}] Skema Prisma tidak valid (tidak ditemukan deklarasi 'model' atau 'datasource').";
                    }
                    break;

                case TargetFramework::EXPRESS_DRIZZLE:
                    if (!str_contains($content, 'Table') && !str_contains($content, 'export const')) {
                        $errors[] = "[{$filename}] Skema Drizzle tidak valid (wajib mendefinisikan tabel dengan pgTable/mysqlTable/sqliteTable).";
                    }
                    break;

                case TargetFramework::SPRINGBOOT_HIBERNATE:
                    if (!str_contains($content, '@Entity')) {
                        $errors[] = "[{$filename}] Entity Java tidak valid (wajib memiliki anotasi '@Entity').";
                    }
                    break;

                case TargetFramework::RAW_SQL:
                    if (!stripos($content, 'CREATE TABLE')) {
                        $errors[] = "[{$filename}] Skrip SQL tidak valid (tidak ditemukan perintah 'CREATE TABLE').";
                    }
                    break;
            }
        }

        return [
            'isValid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Pindai konten terhadap pola berbahaya hasil prompt-injection.
     * Berjalan untuk SEMUA framework karena tidak bergantung pada
     * kepatuhan model terhadap system prompt (defense in depth).
     *
     * @return array<string> daftar temuan (kosong bila bersih)
     */
    protected function securityScan(string $content): array
    {
        $findings = [];

        $patterns = [
            // SQL destruktif / manipulasi data mentah
            '/\bDROP\s+(TABLE|DATABASE|SCHEMA)\b/i' => 'mengandung perintah DROP — dilarang, gunakan definisi CREATE saja.',
            '/\bTRUNCATE\s+TABLE\b/i' => 'mengandung perintah TRUNCATE — dilarang.',
            '/\bDELETE\s+FROM\b/i' => 'mengandung perintah DELETE FROM — berkas skema tidak boleh memanipulasi data.',
            '/\bUPDATE\s+\w+\s+SET\b/i' => 'mengandung perintah UPDATE — berkas skema tidak boleh memanipulasi data.',
            '/DB\s*::\s*(statement|unprepared)\s*\(/i' => 'mengandung eksekusi SQL mentah (DB::statement/unprepared) — dilarang.',
            // Fungsi PHP berbahaya
            '/\b(eval|exec|shell_exec|system|passthru|popen|proc_open|base64_decode|create_function)\s*\(/i' => 'mengandung pemanggilan fungsi PHP berbahaya ($1) — dilarang.',
            '/\bassert\s*\(\s*["\']/i' => 'mengandung assert() dengan string — berpotensi eksekusi kode, dilarang.',
            // Operasi file / jaringan
            '/\b(file_put_contents|unlink|copy)\s*\(/i' => 'mengandung operasi file ($1) — berkas skema tidak boleh menyentuh filesystem.',
            '/\bfile_get_contents\s*\(\s*[\'"]https?:/i' => 'mengandung pengambilan URL jarak jauh — dilarang.',
            '/\bcurl_(init|exec|setopt)/i' => 'mengandung pemanggilan cURL — berkas skema tidak boleh mengakses jaringan.',
            // Runtime lain (Drizzle/Node & Spring)
            '/\brequire\s*\(\s*[\'"]child_process[\'"]/i' => 'mengandung child_process Node.js — dilarang.',
            '/Runtime\s*\.\s*getRuntime\s*\(\s*\)\s*\.\s*exec/i' => 'mengandung Runtime.exec() Java — dilarang.',
            '/new\s+ProcessBuilder\s*\(/i' => 'mengandung ProcessBuilder Java — dilarang.',
        ];

        foreach ($patterns as $regex => $message) {
            if (preg_match($regex, $content, $m)) {
                $findings[] = str_replace('$1', strtolower($m[1] ?? ''), $message);
            }
        }

        return $findings;
    }

    /**
     * Ekstraksi nama tabel dari isi berkas migrasi Laravel.
     */
    protected function extractLaravelTableName(string $migrationContent): ?string
    {
        if (preg_match("/Schema::create\(\s*['\"]([^'\"]+)['\"]/i", $migrationContent, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
