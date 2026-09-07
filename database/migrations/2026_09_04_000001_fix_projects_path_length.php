<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * B2-1: Perbaiki panjang projects.absolute_path agar aman di MySQL.
 *
 * Konteks: migrasi create awal sempat memakai varchar(1000) unique
 * (1000 x utf8mb4 = 4000 byte > limit index MySQL 3072 byte).
 * Migrasi create saat ini sudah varchar(500), jadi fresh install aman.
 * Migrasi ini khusus untuk DB yang sudah terlanjur migrate saat masih 1000.
 *
 * Tanpa doctrine/dbal (driver-aware + idempotent):
 * - mysql  : DROP INDEX -> MODIFY VARCHAR(500) -> ADD UNIQUE
 * - pgsql  : ALTER COLUMN TYPE VARCHAR(500)
 * - sqlite : no-op (SQLite mengabaikan panjang VARCHAR)
 * - lainnya: no-op aman
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE `projects` DROP INDEX `projects_absolute_path_unique`');
            } catch (\Throwable $e) {
                // Index belum ada (DB fresh) — lanjut.
            }

            DB::statement('ALTER TABLE `projects` MODIFY `absolute_path` VARCHAR(500) NOT NULL');

            try {
                DB::statement('ALTER TABLE `projects` ADD UNIQUE `projects_absolute_path_unique` (`absolute_path`)');
            } catch (\Throwable $e) {
                // Unique sudah ada — abaikan agar idempotent.
            }

            return;
        }

        if ($driver === 'pgsql') {
            try {
                DB::statement('ALTER TABLE "projects" ALTER COLUMN "absolute_path" TYPE VARCHAR(500)');
            } catch (\Throwable $e) {
                // Kolom sudah 500 atau tabel belum ada — abaikan.
            }

            return;
        }

        // sqlite / sqlsrv / lain: no-op (aman, tidak merusak data).
    }

    public function down(): void
    {
        // Tidak revert ke 1000 (akan merusak MySQL lagi). No-op disengaja.
    }
};
