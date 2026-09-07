# FIX PLAN BATCH-2 — DEVArchitect Sisa Temuan (2026-09-03)

> Lanjutan dari `FIX_PLAN_DEVArchitect.md` yang sudah ~90% selesai.
> Fokus: 1 HIGH baru + 1 MEDIUM belum dikerjakan + 3 LOW hardening.
> Mode: Build — siap dieksekusi per item.

## Ringkasan

| ID | Severity | File utama | Status |
|----|----------|------------|--------|
| B2-1 | HIGH | `database/migrations/2026_09_02_000001_create_projects_table.php:17` | ✅ Dieksekusi — 2 migrasi alter baru, `php -l` hijau |
| B2-2 | MEDIUM | `app/Services/ProjectService.php:33-46` | Belum fix — detect rapuh + fallback salah |
| B2-3 | LOW | `app/Services/MigrationLinterService.php:12`, `GenerationController.php:63,85,199` | Parsial — DI default, hardcode driver, leak error |

Estimasi: B2-1 (30 mnt) + B2-2 (1 jam) + B2-3 (1 jam).

---

## B2-1. HIGH — `absolute_path varchar(1000) unique` jebol di MySQL

**Lokasi:** `database/migrations/2026_09_02_000001_create_projects_table.php:17`
```php
$table->string('absolute_path', 1000)->unique();
```

**Kenapa bermasalah:**
- MySQL InnoDB + utf8mb4: 1000 char × 4 byte = 4000 byte > limit index 3072 byte.
- Error: `SQLSTATE[42000]: Specified key was too long; max key length is 3072 bytes`.
- SQLite/Postgres lolos, jadi bug hanya muncul saat pindah ke MySQL (false-green di lokal).
- Plus: migrasi lama sudah diedit in-place (jsonb→json, unique). Kalau DB tim sudah pernah `migrate`, edit in-place tidak akan jalan tanpa `migrate:fresh`.

**Plan eksekusi:**

1. Jangan edit migrasi `000001` lagi. Buat migrasi alter baru:
   - Nama: `database/migrations/2026_09_04_000001_fix_projects_path_length.php`
   ```php
   <?php
   use Illuminate\Database\Migrations\Migration;
   use Illuminate\Database\Schema\Blueprint;
   use Illuminate\Support\Facades\Schema;

   return new class extends Migration {
       public function up(): void
       {
           Schema::table('projects', function (Blueprint $table) {
               // Butuh doctrine/dbal untuk change() — jika belum ada:
               // composer require doctrine/dbal
               $table->string('absolute_path', 512)->change();
           });
           // unique + index sudah ada dari migrasi lama, tidak perlu drop.
           // Jika DB lama belum punya unique (sudah migrate sebelum fix),
           // tambah defensif:
           // try { Schema::table('projects', fn($t) => $t->unique('absolute_path')); } catch (\Throwable $e) {}
       }
       public function down(): void
       {
           Schema::table('projects', function (Blueprint $table) {
               $table->string('absolute_path', 1000)->change();
           });
       }
   };
   ```
   - Alternatif tanpa dbal (lebih aman di semua driver): buat tabel baru → copy → rename. Pilih ini jika `doctrine/dbal` tidak diinginkan.

2. Tambah index yang kurang di generations (FK sudah buat index implisit, tapi eksplisit lebih jelas untuk SQLite):
   - Nama: `database/migrations/2026_09_04_000002_add_generations_indexes.php`
   ```php
   Schema::table('generations', function (Blueprint $table) {
       $table->index('project_id', 'generations_project_id_index');
       // status + target_framework sudah ->index() di create, skip jika sudah ada
   });
   ```
   - Bungkus dengan `if (!Schema::hasIndex(...))` atau try/catch agar idempotent di SQLite (SQLite tidak support drop/change sebagian — test dulu).

3. Verifikasi:
   ```powershell
   php artisan migrate --database=sqlite
   php artisan migrate:fresh --database=mysql  # atau ke DB MySQL dev
   php artisan test --filter=Project
   ```
   - Acceptance: migrate hijau di sqlite + mysql, `Project::create` dengan path 600 char tetap bisa (karena 512? — jika path Windows panjang >512, pertimbangkan `text` + unique hash, lihat catatan).
   - Catatan: path Windows + NativePHP bisa >512 (MAX_PATH 260, tapi UNC bisa panjang). Jika khawatir, opsi B: ubah ke `text('absolute_path')` + kolom baru `path_hash char(64) unique` (sha256). Ini paling aman untuk semua DB. Pilih A (512) untuk cepat, B untuk robust.

**File disentuh:** 1–2 migrasi baru, tidak ada edit migrasi lama.

---

## B2-2. MEDIUM — `detectFramework()` rapuh + fallback salah

**Lokasi:** `app/Services/ProjectService.php:32-47`
```php
$pkgContent = File::get(.../package.json);
if (str_contains($pkgContent, 'drizzle-orm')) ...
if (str_contains($pkgContent, 'prisma') || File::isDirectory(.../prisma)) ...
return TargetFramework::EXPRESS_PRISMA; // fallback
```

**Kenapa bermasalah:**
- `str_contains` mentah kena false-positive: kata `prisma` di README/deskripsi/script, atau `drizzle-orm` di komentar → salah deteksi.
- Crash jika `package.json` invalid JSON → jatuh ke catch lalu tetap `EXPRESS_PRISMA` (salah).
- Fallback Node tanpa ORM ke `EXPRESS_PRISMA` salah — proyek Express + Mongoose/Sequelize/TypeORM akan dipaksa jadi Prisma → prompt + injector salah folder (`prisma/`).
- Tidak cek `composer.json` untuk Laravel tanpa `artisan` (misal repo belum install), tidak cek `build.gradle.kts` sudah ada (bagus) tapi tidak cek Spring `@SpringBootApplication`.

**Plan eksekusi:**

1. Ganti isi blok Node dengan decode JSON + cek dependency keys:
   ```php
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
           // folder prisma/ sebagai sinyal sekunder (hanya jika package.json valid tapi tanpa deps)
           if (File::isDirectory($realPath . DIRECTORY_SEPARATOR . 'prisma')) {
               return TargetFramework::EXPRESS_PRISMA;
           }
       } catch (\Throwable $e) {
           \Illuminate\Support\Facades\Log::warning('Gagal parse package.json untuk deteksi framework: '.$e->getMessage());
           // jangan return di sini, jatuh ke fallback bawah
       }
       // Node tanpa ORM terdeteksi → bukan Prisma, tapi generic
       // Jika masih ada package.json tapi tanpa ORM → anggap Express generic → RAW_SQL atau tambah enum baru?
       // Untuk sekarang: return RAW_SQL agar tidak salah tulis ke prisma/
       return TargetFramework::RAW_SQL;
   }
   ```
   - Keputusan fallback: `RAW_SQL` (aman, tulis `schema.sql` ke root) vs `EXPRESS_PRISMA`. Pilih `RAW_SQL` sesuai FIX_PLAN awal.
   - Jika ingin lebih presisi, tambah enum `EXPRESS_GENERIC` di masa depan — di luar scope batch-2.

2. Opsional (jika sempat): perkuat Laravel detect:
   ```php
   // Laravel tanpa artisan (repo fresh clone) tapi composer.json require laravel/framework
   if (File::exists($realPath.'/composer.json')) {
       try {
           $c = json_decode(File::get($realPath.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
           if (isset($c['require']['laravel/framework'])) return TargetFramework::LARAVEL;
       } catch (\Throwable $e) {}
   }
   ```
   Letakkan sebelum cek artisan agar clone tanpa vendor tetap terdeteksi.

3. Tambah test:
   - `tests/Unit/ProjectServiceDetectTest.php`:
     - fake dir dengan `package.json` `{dependencies:{drizzle-orm}}` → DRIZZLE
     - `{dependencies:{prisma}}` → PRISMA
     - `{dependencies:{mongoose}}` → RAW_SQL (bukan PRISMA)
     - `package.json` invalid → RAW_SQL, tidak throw
     - `composer.json` require laravel → LARAVEL walau tanpa artisan

**Acceptance:** semua test hijau, tidak ada false-positive `prisma` di description.

**File disentuh:** `app/Services/ProjectService.php` saja (+ test baru).

---

## B2-3. LOW — DI default, hardcode driver, leak error

### B2-3a. `MigrationLinterService.php:11-13` DI default
```php
public function __construct(
    protected ProjectService $projectService = new ProjectService()
) {}
```
**Fix (1 baris):**
```php
public function __construct(
    protected ProjectService $projectService
) {}
```
Container Laravel auto-inject. Hapus `= new ...` agar mock di test bisa masuk. Cek tidak ada `new MigrationLinterService()` manual tanpa argumen di codebase (saat ini controller pakai DI, aman).

### B2-3b. `GenerationController.php:63` hardcode `ai_driver`
```php
'ai_driver' => 'openrouter',
```
**Fix:** ambil nama driver aktual dari request atau manager. Paling simpel:
```php
// di atas: $driverName = AppSetting::get('active_ai_driver', 'openrouter');
'ai_driver' => $driverName,
```
Atau tambah `$aiManager->getActiveDriverName()`. Perlu `use App\Models\AppSetting;` jika belum ada.

### B2-3c. Leak error 500 + notify mentah
**Lokasi:** `GenerationController.php:80-86,194-200`
```php
} catch (Throwable $e) {
    $notificationService->notifyError('Pembuatan Skema', $e->getMessage());
    return response()->json(['success'=>false,'message'=>$e->getMessage()], 500);
}
```
**Risiko:** pesan DB (`SQLSTATE ...`, path absolut), validasi internal, atau potongan prompt bocor ke frontend + notifikasi OS (bisa di-screenshot).

**Fix standar di 2 method (`generate`, `inject`):**
```php
} catch (Throwable $e) {
    \Illuminate\Support\Facades\Log::error('Generate schema failed', [
        'project_id' => $project->id ?? null,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    // Notifikasi tetap user-friendly, tanpa detail mentah
    try {
        $notificationService->notifyError('Pembuatan Skema', 'Terjadi kesalahan, cek log aplikasi.');
    } catch (\Throwable $ignored) {}
    $message = app()->hasDebugModeEnabled() && config('app.debug')
        ? $e->getMessage()
        : 'Terjadi kesalahan internal. Silakan coba lagi.';
    return response()->json(['success'=>false,'message'=>$message], 500);
}
```
- Untuk error bisnis yang memang boleh tampil (misal `API Key belum diatur`, `File sudah ada`, `sudah terdaftar`): lempar `InvalidArgumentException` / `RuntimeException` dengan pesan aman dan tangkap khusus → return 422/409 dengan pesan asli. Hanya `Throwable` tak terduga yang digenerikkan.
- Terapkan pola sama di `ProjectController@store` (sudah ada mapping 409/422, tinggal tambah Log).

**Acceptance:**
- `php artisan test` hijau (mock linter tanpa `new`).
- Trigger error sengaja (cabut API key / inject duplikat tanpa force): response 500 generik saat `APP_DEBUG=false`, detail muncul di `storage/logs/laravel.log`, bukan di JSON.

---

## Urutan eksekusi disarankan

1. **B2-1 dulu** (migrasi) — karena blocking untuk MySQL. Jalankan `php artisan migrate` di sqlite + mysql dev sebelum lanjut.
2. **B2-2** (detect) + test unit — tidak ada migrasi, aman.
3. **B2-3a,3b,3c** (hardening) — sentuh controller, test manual generate → cabut key → cek log + notifikasi.

## Checklist verifikasi akhir

```powershell
php artisan migrate:fresh --seed
php artisan test
# manual:
# 1. daftar proyek path panjang → 201
# 2. browse folder express+mongoose → framework=raw_sql (bukan express_prisma)
# 3. generate tanpa API key (APP_DEBUG=false) → 500 generik + log terisi
# 4. inject 2x tanpa force → 409, dengan ?force=1 → sukses
```

## Log Eksekusi (2026-09-03)

- `2026_09_04_000001_fix_projects_path_length.php` dibuat — driver-aware tanpa `doctrine/dbal` (mysql MODIFY 500 + re-add unique, pgsql ALTER TYPE, sqlite no-op), idempotent try/catch. `php -l` hijau.
- `2026_09_04_000002_add_generations_indexes.php` dibuat — index `project_id/status/target_framework` idempotent. `php -l` hijau.
- Belum dijalankan: `php artisan migrate` (butuh DB dev + keputusan fresh vs alter). Jika DB masih kosong → `migrate:fresh` cukup. Jika sudah ada data lama (varchar 1000) → `php artisan migrate --force`.

## File disentuh (6)

- `database/migrations/2026_09_04_000001_fix_projects_path_length.php` (baru)
- `database/migrations/2026_09_04_000002_add_generations_indexes.php` (baru, opsional)
- `app/Services/ProjectService.php`
- `app/Services/MigrationLinterService.php`
- `app/Http/Controllers/GenerationController.php`
- `tests/Unit/ProjectServiceDetectTest.php` (baru)
