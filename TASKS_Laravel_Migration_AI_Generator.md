# Task List & Implementation Roadmap
## DEVArchitect: Aplikasi Desktop Generator ERD & Skema Database Multi-Framework Berbasis AI

| Informasi Dokumen | Keterangan |
|---|---|
| Nama Proyek | **DEVArchitect** (Universal AI ERD & Multi-Framework Schema Generator) |
| File Acuan | [URD_Laravel_Migration_AI_Generator.md](file:///c:/Users/Indra/Documents/DEVArchitect/docs/URD_Laravel_Migration_AI_Generator.md) & [PRD_Laravel_Migration_AI_Generator.md](file:///c:/Users/Indra/Documents/DEVArchitect/docs/PRD_Laravel_Migration_AI_Generator.md) |
| Versi Dokumen | 2.0 (Sinkron dengan URD & PRD v2.0) |
| Status Proyek | Sedang Berjalan (In Progress) |

---

## Ringkasan Progress Proyek

```
[███████████████████████████░░░] 90% Selesai
```
- **Fase 1: Setup Environment & Framework**: Selesai (100%) ✅
- **Fase 2: Database & Core Data Modeling**: Selesai (100%) ✅
- **Fase 3: Service Layer & AI Engine (OpenRouter)**: Selesai (100%) ✅
- **Fase 4: Integrasi Desktop NativePHP**: Selesai (100%) ✅
- **Fase 5: Controller & API Endpoints**: Selesai (100%) ✅
- **Fase 6: Antarmuka UI Dashboard & Settings**: Selesai (100%) ✅
- **Fase 7: Visualizer ERD Canvas & Multi-ORM Code Review**: Selesai (100%) ✅
- **Fase 8: Injeksi Skema Universal & Notifikasi OS**: Selesai (100%) ✅
- **Fase 9: Pengujian & Finalisasi**: Belum Dimulai (0%) 📋

---

## 📌 Rincian Task per Fase

### FASE 1: Inisialisasi Project & Environment (SELESAI ✅)
> Fokus: Memastikan runtime PHP, Laravel 13, NativePHP, dan database lokal siap.

- [x] **TASK-101**: Inisialisasi project Laravel 13 di folder `laravel-ai-generator`.
- [x] **TASK-102**: Instalasi dan scaffolding paket `nativephp/desktop` (v2.2.1).
- [x] **TASK-103**: Setup bundler frontend (Vite 8 & Tailwind CSS 4).
- [x] **TASK-104**: Perbaikan bug signature command NativePHP (`FreshCommand` & `WipeDatabaseCommand`) agar kompatibel dengan Laravel 13.
- [x] **TASK-105**: Konfigurasi koneksi database SQLite embedded (`DB_CONNECTION=sqlite`, berkas `database/nativephp.sqlite` — disatukan untuk CLI & runtime NativePHP).
- [x] **TASK-106**: Verifikasi tes dasar framework (`php artisan test` pass 100%).

---

### FASE 2: Database Schema & Core Modeling (SELESAI ✅)
> Fokus: Membangun skema relasional SQLite embedded untuk multi-proyek dan draft hasil AI.

- [x] **TASK-201**: Buat Enum `GenerationStatus` (`draft`, `injected`) di `app/Enums/GenerationStatus.php`.
- [x] **TASK-202**: Buat Enum `DatabaseDialect` (`mysql`, `pgsql`, `sqlite`, `sqlsrv`) di `app/Enums/DatabaseDialect.php`.
- [x] **TASK-203**: Buat Migration & Model `Project` (UUID PK, `project_name`, `absolute_path`).
- [x] **TASK-204**: Buat Migration & Model `Generation` (UUID PK, `project_id`, `prompt_text`, `erd_mermaid_text`, `migration_files` JSON, `database_dialect`, `status`).
- [x] **TASK-205**: Buat Migration & Model `AppSetting` (UUID PK, key-value storage dengan enkripsi otomatis untuk API Key).
- [x] **TASK-206**: Eksekusi seluruh migrasi ke database SQLite `database/nativephp.sqlite` (`php artisan migrate`).
- [x] **TASK-207**: Update dokumen spesifikasi [URD](file:///c:/Users/Indra/Documents/DEVArchitect/docs/URD_Laravel_Migration_AI_Generator.md) dan [PRD](file:///c:/Users/Indra/Documents/DEVArchitect/docs/PRD_Laravel_Migration_AI_Generator.md) ke Versi 2.0 (DEVArchitect Multi-Framework & Multi-ORM).

---

### FASE 3: Service Layer & AI Engine (SELESAI ✅)
> Fokus: Logika bisnis validasi proyek, linter dialek, prompt generator, dan adapter LLM.

- [x] **TASK-301**: Implementasi `ProjectService` (validasi struktur folder proyek & deteksi signature framework).
- [x] **TASK-302**: Implementasi `MigrationLinterService` (validasi tipe data sesuai dialek yang dipilih & deteksi potensi konflik nama tabel).
- [x] **TASK-303**: Implementasi `MigrationInjectorService` (penulisan file fisik migrasi secara atomik dengan timestamp berurutan).
- [x] **TASK-304**: Implementasi `DatabasePromptBuilder` (system prompt universal untuk MySQL, PostgreSQL, SQLite, dan SQL Server).
- [x] **TASK-305**: Update kontrak antarmuka `AIDriverInterface` untuk mendukung parameter dialek database dan target framework.
- [x] **TASK-306** `[P0]`: Buat driver `OpenRouterDriver` (`app/Services/Ai/Drivers/OpenRouterDriver.php`) terintegrasi dengan model `openai/gpt-oss-120b` via OpenRouter API.
- [x] **TASK-307** `[P0]`: Buat factory/resolver `AiManager` (`app/Services/Ai/AiManager.php`) untuk mengatur driver AI yang aktif secara dinamis.
- [x] **TASK-308** `[P0]`: Pengujian koneksi OpenRouter berhasil (menghasilkan ERD Mermaid & skema migrasi Laravel 13).
- [x] **TASK-309** `[P0]`: Buat Enum `TargetFramework` (`laravel`, `express_prisma`, `express_drizzle`, `springboot_hibernate`, `raw_sql`) dan sesuaikan `DatabasePromptBuilder` untuk menghasilkan kode ORM spesifik.

---

### FASE 4: Integrasi Desktop NativePHP (SELESAI ✅)
> Fokus: Integrasi aplikasi dengan fitur sistem operasi (Windows/macOS).

- [x] **TASK-401** `[P0]`: Konfigurasi jendela desktop di `NativeAppServiceProvider.php` (Ukuran default 1360x860, minimum 1024x700, window title `DEVArchitect - Universal AI Database Architect`, centering, dan `rememberState`).
- [x] **TASK-402** `[P0]`: Buat service `DesktopDialogService` untuk pemanggilan **Native Folder Picker** menggunakan Facade `Dialog` NativePHP (membuka Windows Explorer untuk memilih direktori proyek).
- [x] **TASK-403** `[P1]`: Buat service `DesktopNotificationService` pengiriman **Native OS Notification** (notifikasi sistem saat AI selesai memproses diagram dan saat file berhasil disuntikkan).
- [x] **TASK-404** `[P1]`: Daftarkan **Global Hotkey** (`Ctrl+Alt+A`) untuk memunculkan atau menyembunyikan jendela aplikasi dari layar mana pun via event `ToggleWindowVisibility`.
- [x] **TASK-405** `[P1]`: Konfigurasi icon dan menu pada **System Tray** (minimize to tray via `MenuBar`).

---

### FASE 5: Controller, Request & API Layer (SELESAI ✅)
> Fokus: Menyediakan endpoint untuk menghubungkan logika backend ke antarmuka pengguna.

- [x] **TASK-501** `[P0]`: Buat Form Request validator (`StoreProjectRequest`, `GenerateSchemaRequest`, `UpdateDraftRequest`).
- [x] **TASK-502** `[P0]`: Buat `ProjectController` (Endpoint load riwayat proyek, trigger folder picker native, smart detection framework, register proyek baru, dan hapus proyek).
- [x] **TASK-503** `[P0]`: Buat `GenerationController`:
  - `POST /api/generations/generate`: Memproses prompt AI secara async dan menyimpan draft baru berstatus `Draft` di database.
  - `GET /api/generations/{id}`: Mengambil data draft untuk pratinjau dry-run.
  - `PUT /api/generations/{id}`: Menyimpan hasil edit manual pada kode skema/Mermaid ke draft di database.
  - `POST /api/generations/{id}/inject`: Menjalankan injeksi file skema ke proyek lokal sesuai framework dan mengubah status menjadi `Injected`.
- [x] **TASK-504** `[P0]`: Buat `SettingController` (Endpoint membaca dan menyimpan API Key OpenRouter yang terenkripsi dan preferensi default dialek/framework).
- [x] **TASK-505** `[P0]`: Daftarkan seluruh rute di `routes/web.php` (`/api/*`).

---

### FASE 6: Frontend UI Shell & Project Dashboard (SELESAI ✅)
> Fokus: Membangun tata letak aplikasi desktop yang rapi, modern, dan fungsional.
> Urutan eksekusi yang disepakati: 601 → 603 → 602 → 605 → 604.
> Stack: Blade + vanilla JS `fetch` (tanpa Alpine/Livewire — zero-setup). Satu helper `window.api()` di `app.js` untuk fetch + toast error 409/422/500.

- [x] **TASK-601** `[P0]`: Desain Master Layout Desktop di Blade (Modern Dark Theme, developer-centric, responsive sidebar navigasi: Dashboard, Generator, History, Settings).
  - Sidebar **collapsible (buka-tutup)**: tombol toggle, state persisten di `localStorage`, mode icon-only saat collapse; murni Tailwind + vanilla JS.
  - Item Generator/History tampil disabled + tooltip "Fase 7/8".
  - Komponen: `toast`, `badge-framework` (5 warna), `empty-state`; route `/` → dashboard.
- [x] **TASK-603** `[P0]`: Halaman **Settings**:
  - Form input API Key OpenRouter (tersimpan aman/terenkripsi) dan pilihan model (default `openai/gpt-oss-120b`).
  - Pemilihan default target framework dan dialek database.
  - Dikerjakan sebelum 602; banner global di layout bila API key kosong.
- [x] **TASK-602** `[P0]`: Halaman **Project History Dashboard**:
  - Tampilan daftar proyek yang pernah ditambahkan (dengan badge tipe framework: Laravel, Prisma, Drizzle, Spring Boot, Standalone).
  - Status proyek aktif (nama proyek, absolute path, framework type, tanggal terakhir dikerjakan).
  - Tombol "Tambah Proyek Baru" yang memicu native folder picker.
  - Tombol aksi: Pilih Proyek Aktif, Buka di VS Code/Explorer, dan Hapus Proyek.
- [x] **TASK-605** `[P1]`: Konsep "Proyek Aktif" via `AppSetting active_project_id` + endpoint set/get (`GET|POST /api/projects/active`).
- [x] **TASK-604** `[P0]`: Endpoint "Buka di editor" (`POST /api/projects/{id}/open`, body `target: explorer|vscode|zed|antigravity` via FormRequest enum) — hanya untuk path proyek terdaftar (whitelist); mapping perintah per-OS di satu config array agar mudah tambah editor; fallback toast ramah bila binary tidak ditemukan. Perintah launch Antigravity diverifikasi saat implementasi.
- [x] **TASK-606** `[P0]`: Scaffold proyek via terminal — `ScaffoldProjectService` (perintah array tanpa shell, validasi slug/parent/target, stub Express di `resources/stubs/`, Spring via Initializr API + H2), `ScaffoldProjectJob` async + tabel `scaffold_jobs`, endpoint prerequisites/scaffold/status/cancel, auto-register + set aktif.
- [x] **TASK-607** `[P0]`: Tab "Buat Baru" di modal dashboard (kartu template, slug checker, parent default + picker, opsi Spring, polling progres/log).

---

### FASE 7: Visualizer ERD Canvas & Multi-ORM Code Review (SELESAI ✅)
> Fokus: Playground pembuatan skema, diagram interaktif, dan editor kode migrasi/skema (Mode Dry-Run).

- [x] **TASK-701** `[P0]`: Halaman **Universal AI Generator Playground**:
  - Kolom input *Smart Prompt Area* yang luas dengan contoh placeholder instruksi database.
  - Dropdown pemilihan: Target Framework/ORM (Laravel Eloquent, Express Prisma, Express Drizzle, Spring Boot Hibernate, Raw SQL) dan Target Dialek Database (MySQL / PostgreSQL / SQLite / SQL Server).
  - Tombol "Generate Architecture" dengan indikator loading animasi dan tombol cancel.
- [x] **TASK-702** `[P0]`: **Interactive ERD Canvas**:
  - Integrasi Mermaid.js untuk me-render kode `erDiagram` hasil AI.
  - Kontrol kanvas interaktif: Pan (geser), Zoom In, Zoom Out, dan Reset Zoom.
  - Tombol "Ekspor ERD" ke format gambar PNG / SVG untuk dokumentasi proyek.
- [x] **TASK-703** `[P0]`: **Smart Code Review Tabs**:
  - Menampilkan daftar file kode yang di-generate AI dalam tab terpisah sesuai format (file `.php`, `schema.prisma`, `schema.ts`, `.java`, atau `.sql`).
  - Indikator status badge `Draft (Dry-Run)` yang menegaskan belum ada file yang tertulis ke disk.
- [x] **TASK-704** `[P0]`: **Inline Code Editor**:
  - Syntax highlighting kode skema multi-bahasa.
  - Kemampuan mengedit kode secara langsung sebelum disimpan, dengan tombol "Simpan Perubahan ke Draft".
- [x] **TASK-705** `[P0]`: Async backend generator — kolom `job_status`/`job_error` (`queued/processing/ready/failed/cancelled`), `GenerateSchemaJob` (queue database), endpoint status + cancel.
- [x] **TASK-706** `[P0]`: Instal `mermaid` via npm (vendor lokal, dibundel Vite — offline-first, tanpa CDN).

---

### FASE 8: Injeksi Skema Universal & Notifikasi OS (SELESAI ✅)
> Fokus: Eksekusi penulisan file ke komputer lokal dengan konfirmasi dan notifikasi aman.

- [x] **TASK-801** `[P0]`: Tombol "Inject Schema to Project" pada halaman review.
- [x] **TASK-802** `[P0]`: Modal konfirmasi dan peringatan deteksi file existing sebelum injeksi.
- [x] **TASK-803** `[P0]`: Eksekusi injeksi universal (penulisan file fisik ke folder spesifik framework, mis. `database/migrations/` untuk Laravel, `prisma/` untuk Prisma, `src/db/` untuk Drizzle, `src/main/java/.../model/` untuk Hibernate, atau root untuk Raw SQL).
- [x] **TASK-804** `[P0]`: Pembaruan status di database dari `Draft` menjadi `Injected` secara real-time.
- [x] **TASK-805** `[P1]`: Pemicu notifikasi native OS Windows saat file berhasil diinjeksi.
- [x] **TASK-806** `[P1]`: Halaman **Generation History**: daftar riwayat seluruh draft dan generasi sebelumnya yang pernah dibuat pada proyek terkait.
- [x] **TASK-807** `[P0]`: Endpoint riwayat generasi per proyek (`GET /api/projects/{id}/generations`, paginate, tanpa kolom `migration_files`).

---

### FASE 9: Pengujian & Finalisasi (TO DO 📋)
> Fokus: Uji stabilitas end-to-end dan persiapan build distribusi.

- [ ] **TASK-901** `[P0]`: Unit Test untuk seluruh Service & Model.
- [ ] **TASK-902** `[P0]`: Feature Test untuk seluruh endpoint API controller.
- [ ] **TASK-903** `[P0]`: End-to-End Test alur utama (Pilih folder lokal $\rightarrow$ Tulis prompt $\rightarrow$ Generate $\rightarrow$ Review ERD $\rightarrow$ Edit kode $\rightarrow$ Inject ke folder).
- [ ] **TASK-904** `[P1]`: Uji build installer aplikasi desktop untuk Windows via `php artisan native:build`.

---

### FASE 10: Chatbot Dokumen — Milestone Architecture & Model Expansion (IN PROGRESS 🏗️)
> Fokus: Asisten chat URD → PRD → SRS → System Design (Human-in-the-Loop per tahap).
> Keputusan terkunci (PLAN_Chatbot_Dokumen.md): Indonesia, per-section, tanpa batas
> aplikasi, editor inline, versi saudara + diff, approve berurutan, ekspor `.md`,
> handoff 1:1, arsip+hapus, notif Windows, footer token, offline baca/edit.

- [x] **TASK-1001** `[P0]`: Migrasi + model `doc_projects/doc_messages/doc_versions` + relasi `doc_version_id` di `generations`.
- [x] **TASK-1002** `[P0]`: `DocChatJob` + `DocChatController`/`DocVersionController` + endpoint chat/versi/approve + tests state machine (B1).
- [x] **TASK-1003** `[P0]`: Halaman `/assistant` + sidebar + model picker (preset + live refresh) + dashboard ringkas (B2).
- [x] **TASK-1004** `[P0]`: 4 prompt builder dokumen + chaining + handoff ke Generator Skema + peringatan privasi `:free` + footer token (B3).
- [ ] **TASK-1005** `[P0]`: Diff view + template brief + ekspor `.md` + arsip/hapus + uji manual tercatat (B4).

---

## 📝 Catatan Hasil Audit Sinkronisasi Kode vs Task (2026-09-03)

> Audit oleh reviewer: seluruh kode Fase 1–5 dibandingkan klaim task di atas.
> Hasil: **~95% sinkron**. Semua klaim fungsional terbukti di kode + `php artisan test` **13/13 passed**.
> Sisa 5 drift di bawah — tidak blocking pengerjaan Fase 6–8.

### A. Hasil per fase

- **Fase 1 (6/6 ✅):** Laravel 13, `nativephp/desktop 2.2.1` (terkunci di `composer.lock`), Vite 8 + Tailwind 4, `.env` pgsql 5432/`Devarchitect`, `migrate:fresh` 8/8 DONE, test hijau.
- **Fase 2 (7/7 ✅):** 3 enum, 3 model + 5 migrasi (termasuk 2 alter B2-1), URD/PRD v2.0 di `docs/`.
- **Fase 3 (9/9 ✅):** Seluruh service/driver/manager ada; linter per-framework dan dipakai controller; API key OpenRouter terisi di `.env` (uji live belum dilakukan).
- **Fase 4 (4/5 ⚠️):** Window, dialog, notifikasi fail-safe, hotkey + listener terpisah ✅. TASK-405 parsial — lihat D4.
- **Fase 5 (5/5 ✅):** 4 FormRequest, 3 controller (guard 409/422/404 + Log), rute `/api/*` + throttle + CSRF-except.

### B. Drift yang perlu ditindaklanjuti

- [x] **D1** `[docs]`: Dokumen di `docs/TASKS_Laravel_Migration_AI_Generator.md` telah disinkronkan secara identik dengan file root ini (single source of truth).
- [x] **D2** `[docs]`: TASK-104 menyiratkan fix di app code — aktualnya terpenuhi via `nativephp/desktop 2.2.1` (`#[AsCommand]` + prefix `native:` di `FreshCommand`/`WipeDatabaseCommand` vendor). Tidak perlu override.
- [x] **D3** `[minor]`: Pemanggilan `->center()` telah ditambahkan pada `NativeAppServiceProvider` (TASK-401 selesai 100%).
- [ ] **D4** `[pra-build]`: TASK-405 "icon" belum ada — `resources/` tanpa file icon dan `config/nativephp.php` masih default. Persiapan sudah dibuat di `docs/APP_ICON_GUIDE.md` (5 file target di `public/` + tabel branding). Lengkapi sebelum TASK-904.
- [ ] **D5** `[opsional]`: TASK-308 belum ada bukti uji koneksi OpenRouter yang tercatat. Catat tanggal + hasil uji live terakhir di sini saat dilakukan.
