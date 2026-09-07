# FIX PLAN — DEVArchitect Fase 1–5 (Review 2026-09-03)

> Sumber review: Enums, Models, Migrations, Services, AI Engine, Controllers, Requests, Routes, Desktop NativePHP.
> Prinsip: Human-in-the-Loop (DRAFT dry-run → INJECTED), atomic inject + rollback.

## 0. Ringkasan Prioritas

- **P0-Kritis Fungsional:** H1 migrasi `jsonb`, H4 provider Prisma, H2 linter Laravel-only + dead code.
- **P0-Kritis Keamanan:** H3 path traversal injector, H5 CSRF `web.php`, H7 tanpa batas size.
- **P1-Medium:** duplikat proyek, re-inject, N+1 settings, detect rapuh, block 180s.
- Estimasi: P0 ~1–2 hari, P1 ~2–3 hari.

---

## P0-1. Migration `generations.migration_files` — `jsonb` → `json`

**File:** `database/migrations/2026_09_02_000002_create_generations_table.php:19`
**Masalah:** `jsonb` hanya valid PostgreSQL. Migrate gagal di SQLite/MySQL (default Laravel).

**Plan:**
1. Buat migrasi baru `2026_09_04_000001_fix_generations_jsonb.php`:
   - Jika butuh `change()`: tambah `doctrine/dbal`.
   - Alternatif tanpa dbal: `Schema::table` drop + add (hati-hati data), atau edit migrasi lama jika belum production + `migrate:fresh`.
2. Tambah index di migrasi baru:
   ```php
   $table->index('project_id');
   $table->index('status');
   ```
3. Tambah migrasi baru untuk projects:
   ```php
   $table->string('absolute_path', 2000)->unique()->change();
   $table->index('framework_type');
   ```

**Acceptance:** `php artisan migrate --database=sqlite` + `mysql` hijau.

---

## P0-2. Injector — sanitasi filename + guard inside-project

**File:** `app/Services/MigrationInjectorService.php:40-54,86-95`
**Masalah:** AI filename langsung digabung ke `$targetDir` tanpa sanitasi. `../`, path absolut bisa keluar dari proyek. Overwrite diam-diam. `RAW_SQL '.'` tulis ke root.

**Plan:**
1. Tambah method `sanitizeFilename(string $raw, TargetFramework $fw): string`:
   - `$base = basename($raw)`; whitelist `/^[A-Za-z0-9_\-\.]+$/`; paksa ekstensi `$fw->fileExtension()`; `Str::limit(120)`.
   - Throw `RuntimeException` jika kosong setelah sanitasi.
2. Di `inject()` setelah `resolveTargetDirectory()`:
   - `$realBase = realpath($project->absolute_path)`; `$realTarget = realpath($targetDir) ?: $targetDir`; pastikan `Str::startsWith($realTarget, $realBase)`.
   - Sebelum `File::put`: jika `File::exists($targetPath)` → throw `File sudah ada, tolak overwrite` (atau tambah flag `$overwrite=false`).
   - Validasi `!empty(content)` + `strlen < 200_000` per file + `count < 30`.
3. Ganti `@unlink` → `File::delete`.
4. `resolveTargetDirectory()` untuk `RAW_SQL`: tetap root tapi wajib lewat sanitasi di atas + default filename `schema.sql` jika tidak valid.

**Acceptance:** payload `../../.env`, `/etc/passwd`, `a.php` di Laravel ditolak; rollback hapus semua file parsial.

---

## P0-3. Prisma provider mapping + prompt guardrails

**File:** `app/Services/Ai/Prompts/DatabasePromptBuilder.php:71-75`, `app/Enums/DatabaseDialect.php:22-51`
**Masalah:** `{$dialect->value}` menghasilkan `pgsql/sqlsrv`, sedangkan Prisma butuh `postgresql/sqlserver`.

**Plan:**
1. Tambah `DatabaseDialect::prismaProvider(): string`:
   ```php
   return match($this) {
     self::MYSQL => 'mysql',
     self::POSTGRESQL => 'postgresql',
     self::SQLITE => 'sqlite',
     self::SQLSERVER => 'sqlserver',
   };
   ```
2. Di `getFrameworkInstructions(PRISMA)`: pakai `$dialect->prismaProvider()` bukan `$dialect->value`.
3. Tambah ke `buildSystemPrompt()` ATURAN OUTPUT:
   - `5. Jangan sertakan penjelasan di luar JSON. Jangan ada DROP TABLE/DROP DATABASE. Maks 20 tabel.`

**Acceptance:** generate pgsql → `provider = "postgresql"` valid `prisma validate`.

---

## P0-4. Linter per-framework + aktifkan di controller

**File:** `app/Services/MigrationLinterService.php:16-60`, `app/Http/Controllers/GenerationController.php:35-51,92-107`
**Masalah:** cek `<?php/Schema::create` untuk semua FW; tidak pernah dipanggil (dead code).

**Plan:**
1. Ubah signature: `validateDraft(array $files, Project $project, TargetFramework $fw, DatabaseDialect $dialect)`.
2. Branch:
   - `LARAVEL`: cek `<?php` + `Schema::create` + `extractTableName`.
   - `PRISMA`: cek `model ` + `datasource db` + regex `/model\s+(\w+)\s*\{/`.
   - `DRIZZLE`: cek `pgTable|mysqlTable|sqliteTable` + `export const`.
   - `SPRINGBOOT`: cek `@Entity` + `@Table`.
   - `RAW_SQL`: cek `CREATE TABLE`.
3. Cek umum: duplikat filename dalam draft, duplikat tabel dalam draft, `filename` lolos sanitasi, `content` tidak kosong.
4. Inject via DI: `__construct(protected ProjectService $ps)` bukan `new`.
5. Di `GenerationController@generate` + `update`: panggil linter, jika `!isValid` → `422` + errors (jangan simpan draft invalid). Warnings tetap simpan.

**Acceptance:** draft Prisma valid lolos; draft Laravel rusak ditolak 422.

---

## P0-5. Routes `web.php` → `api.php` + throttle

**File:** `routes/web.php:13-29`
**Masalah:** Semua `/api/*` di `web.php` lewat `VerifyCsrfToken` + session. Fetch desktop tanpa token = 419.

**Plan:**
1. Pindah grup ke `routes/api.php` (Laravel 11: sudah auto prefix `/api`).
2. Tambah `->middleware('throttle:60,1')`; `generate` + `inject` `throttle:10,1`.
3. Hapus dari `web.php`, sisakan `Route::get('/')`.
4. Test: `POST /api/generations/generate` tanpa CSRF → 200/422 (bukan 419).

**Catatan:** jika tetap di `web.php` (NativePHP webview butuh session), alternatif: tambah ke `$middleware->validateCsrfTokens(except: ['api/*'])` di `bootstrap/app.php`.

---

## P0-6. Request validation limits

**File:** `app/Http/Requests/GenerateSchemaRequest.php:21`, `UpdateDraftRequest.php:18-20`, `StoreProjectRequest.php:20-21`
**Masalah:** tanpa `max` → DB/disk exhaustion + tagihan token.

**Plan:**
- `prompt_text`: `['required','string','min:10','max:5000']`
- `target_version`: `['nullable','string','max:20']`
- `migration_files`: `['required','array','max:30']`
- `*.filename`: `['required','string','max:120','regex:/^[A-Za-z0-9_\-\.]+$/']`
- `*.content`: `['required','string','max:200000']`
- `absolute_path`: `['required','string','max:2000']`
- `project_name`: tambah `min:3`

**Acceptance:** payload raksasa → 422, bukan 500/OOM.

---

## P0-7. OpenRouter parser + timeout hardening

**File:** `app/Services/Ai/Drivers/OpenRouterDriver.php:50-101`
**Masalah:** `cleanMarkdownJson` hanya strip backtick di ujung; `env()` di runtime; timeout 180s gantung.

**Plan:**
1. `cleanMarkdownJson()`: ambil `substr(first '{', last '}')` lalu `json_decode(..., JSON_THROW_ON_ERROR)` dalam try/catch dengan pesan `json_last_error_msg + substr 500`.
2. Validasi tiap `migration_files[i].filename/content` + batasi 30 file.
3. `Http::timeout(60)->connectTimeout(10)->retry(2,500)`; ganti `env()` → `config('services.openrouter.key')` (tambah config + `.env.example`).
4. Tambah `Log::error('openrouter failed', [...])` saat `failed()`, return pesan generik ke user.

**Acceptance:** output dengan prolog/epilog tetap ter-parse; timeout tidak gantung 180s.

---

## P1-1. Duplikat + lifecycle DRAFT

**File:** `app/Services/ProjectService.php:102-115`, `app/Http/Controllers/GenerationController.php:92,112`, `ProjectController.php:63`
**Plan:**
- `registerProject()`: cek `Project::where('absolute_path',$realPath)->first()` → return existing atau throw 409. Panggil `validateFolder()` dulu, tolak jika `!valid`.
- `update()`: tolak jika `$generation->status==INJECTED` → 409 `Gunakan duplicate/revisi`.
- `inject()`: tolak jika sudah `INJECTED` → 409 (atau butuh `?force=1`).
- `destroy()`: return 404 jika not found, validasi `uuid`.

---

## P1-2. Settings + perf

**File:** `app/Models/AppSetting.php:46-79`, `app/Http/Controllers/SettingController.php:36`, `app/Services/ProjectService.php:122-125`
**Plan:**
- `AppSetting::get()`: `Cache::rememberForever("setting:$key", fn...)`; `set()`: `Cache::forget`. Tangkap `DecryptException` spesifik + `Log::warning`.
- `SettingController@update`: ganti ke FormRequest, `default_framework => Rule::enum(TargetFramework::class)`, `default_dialect => Rule::enum(...)`, `openrouter_model max:100`.
- `listProjects()`: `with(['latestGeneration' => fn($q)=>$q->select('id','project_id','status','created_at')])` + `select(...)` + paginate 20.
- `AiManager.php:19`: `app(OpenRouterDriver::class)` bukan `new`; `DesktopDialogService.php:11`: hapus default `= new`, pakai DI murni.

---

## P1-3. Desktop hardening

**File:** `app/Events/ToggleWindowVisibility.php`, `app/Services/Desktop/DesktopDialogService.php:53`, `DesktopNotificationService.php`
**Plan:**
- Buat `app/Listeners/ShowMainWindow.php` dengan `handle(ToggleWindowVisibility $e){ Window::open('main'); }`; kosongkan constructor event.
- `pickProjectFolder()`: return `'framework' => $validation['framework']->value`, `'framework_label' => ->label()`.
- Bungkus semua `Notification::...->show()` + pemanggilnya di controller dengan try/catch agar notify gagal tidak jadi 500.

---

## Verifikasi Akhir

```powershell
php artisan migrate:fresh --seed
php artisan test
# manual: browse folder → generate (laravel + prisma pgsql) → update draft invalid → 422 → inject → cek file di disk → re-inject → 409
```

## File yang disentuh (17)

`migrations/*fix*.php`, `MigrationInjectorService.php`, `DatabaseDialect.php`, `DatabasePromptBuilder.php`, `MigrationLinterService.php`, `GenerationController.php`, `routes/api.php`, `web.php`, `bootstrap/app.php` (opsional CSRF), `*Request.php` (3), `OpenRouterDriver.php`, `config/services.php`, `ProjectService.php`, `ProjectController.php`, `AppSetting.php`, `SettingController.php`, `AiManager.php`, `ToggleWindowVisibility.php` + Listener baru, `Desktop*Service.php`.
