<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B2-1 (pelengkap): Pastikan index generations tersedia untuk DB lama.
 *
 * Migrasi create saat ini sudah ->index() untuk status & target_framework.
 * Migrasi ini idempotent untuk DB yang migrate sebelum index ditambahkan:
 * hanya menambah index yang belum ada (cek via system catalog per driver).
 */
return new class extends Migration
{
    /** @var array<string, string> indexName => column */
    protected array $indexes = [
        'generations_project_id_index' => 'project_id',
        'generations_status_index' => 'status',
        'generations_target_framework_index' => 'target_framework',
    ];

    public function up(): void
    {
        foreach ($this->indexes as $indexName => $column) {
            if ($this->indexExists('generations', $indexName)) {
                continue;
            }

            Schema::table('generations', function (Blueprint $table) use ($column, $indexName) {
                $table->index($column, $indexName);
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->indexes) as $indexName) {
            if (! $this->indexExists('generations', $indexName)) {
                continue;
            }

            Schema::table('generations', function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        try {
            $driver = DB::getDriverName();

            if ($driver === 'pgsql') {
                return DB::selectOne(
                    'SELECT 1 AS exists_flag FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                    [$table, $indexName]
                ) !== null;
            }

            if ($driver === 'mysql') {
                return DB::selectOne(
                    'SELECT 1 AS exists_flag FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
                    [$table, $indexName]
                ) !== null;
            }

            if ($driver === 'sqlite') {
                return DB::selectOne(
                    "SELECT 1 AS exists_flag FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
                    [$table, $indexName]
                ) !== null;
            }

            if ($driver === 'sqlsrv') {
                return DB::selectOne(
                    'SELECT 1 AS exists_flag FROM sys.indexes WHERE object_id = OBJECT_ID(?) AND name = ?',
                    [$table, $indexName]
                ) !== null;
            }
        } catch (\Throwable $e) {
            // Jika pengecekan gagal (mis. tabel belum ada), anggap belum ada
            // agar up() mencoba membuatnya; kegagalan nyata akan muncul alami.
            return false;
        }

        return false;
    }
};
