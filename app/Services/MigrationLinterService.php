<?php

namespace App\Services;

use App\Enums\DatabaseDialect;
use App\Models\Project;

class MigrationLinterService
{
    /**
     * Memvalidasi seluruh file migrasi draft terhadap kompatibilitas dialek database & konflik nama file.
     *
     * @param array<array{filename: string, content: string}> $migrationFiles
     * @return array{isValid: bool, errors: array<string>, warnings: array<string>}
     */
    public function validateDraft(
        array $migrationFiles, 
        Project $project, 
        DatabaseDialect $dialect = DatabaseDialect::MYSQL
    ): array {
        $errors = [];
        $warnings = [];

        $existingFiles = (new ProjectService())->getExistingMigrations($project);

        foreach ($migrationFiles as $file) {
            $filename = $file['filename'] ?? 'unknown_migration.php';
            $content = $file['content'] ?? '';

            if (!str_contains($content, '<?php') || !str_contains($content, 'Schema::create')) {
                $errors[] = "[{$filename}] Format file migrasi tidak valid (harus mengandung pembuka PHP dan Schema::create).";
            }

            $tableName = $this->extractTableName($content);
            if ($tableName) {
                foreach ($existingFiles as $existingFile) {
                    if (str_contains($existingFile, "create_{$tableName}_table")) {
                        $warnings[] = "[{$filename}] Tabel '{$tableName}' kemungkinan sudah memiliki migrasi di proyek ({$existingFile}).";
                    }
                }
            }

            // Validasi spesifik per dialek
            if ($dialect === DatabaseDialect::POSTGRESQL) {
                if (str_contains($content, '->increments(') && !str_contains($content, '->bigIncrements(')) {
                    $warnings[] = "[{$filename}] Pada PostgreSQL, disarankan menggunakan 'bigIncrements' atau 'uuid' sebagai primary key.";
                }
            } elseif ($dialect === DatabaseDialect::MYSQL) {
                if (str_contains($content, '->jsonb(')) {
                    $warnings[] = "[{$filename}] MySQL tidak mendukung tipe 'jsonb', gunakan 'json()'.";
                }
            }
        }

        return [
            'isValid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Ekstraksi nama tabel dari isi file migrasi.
     */
    protected function extractTableName(string $migrationContent): ?string
    {
        if (preg_match("/Schema::create\(\s*['\"]([^'\"]+)['\"]/i", $migrationContent, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
