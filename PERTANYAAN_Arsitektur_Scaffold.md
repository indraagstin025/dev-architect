# Plan Implementasi — Blokir Keras ORM (Opsi B) & Scaffold Proyek via Terminal

> Status: ✅ Terjawab & dieksekusi (Opsi B + routing model gratis + scaffold 606/607).
> Terakhir diperbarui: 2026-09-04

---

## 1. Latar belakang

1. **Celah mismatch framework ↔ ORM:** proyek Laravel saat ini bisa di-generate-kan
   output Prisma/Drizzle (cukup centang konfirmasi) lalu di-inject ke folder yang salah
   (`src/db/schema.ts` di dalam proyek Laravel — tidak merusak, tapi menyesatkan
   karena badge menjadi `Injected` seolah sukses).
2. **Kebutuhan scaffold:** saat menambah proyek, user ingin opsi *membuat proyek baru*
   dari terminal (Laravel, Express, Spring Boot) langsung dari aplikasi, bukan hanya
   memilih folder existing.

Hasil riset toolchain (cek langsung di mesin): `composer` 2.9.7 ✅, `node` 24 +
`npm` 11 ✅, `java` 26 ✅, **`mvn` tidak ada** ❌ → Spring Boot di-scaffold via
Spring Initializr HTTP API (unduh `starter.zip` + ekstrak `ZipArchive` PHP,
tanpa shell sama sekali).

---

## 2. Bagian A — Opsi B: blokir keras pasangan silang ORM

### 2.1 Aturan kompatibilitas (final)

| Framework proyek | Target yang diizinkan | Ditolak keras (422) |
|---|---|---|
| Laravel | `laravel`, `raw_sql` | Prisma, Drizzle, Hibernate |
| Express Prisma | `express_prisma`, `raw_sql` | Laravel, Drizzle, Hibernate |
| Express Drizzle | `express_drizzle`, `raw_sql` | Laravel, Prisma, Hibernate |
| Spring Boot | `springboot_hibernate`, `raw_sql` | Laravel, Prisma, Drizzle |
| Tak dikenal (`raw_sql`) | semua (tipenya memang belum diketahui) | — |
| Dialek DB | selalu bebas (semua ORM multi-DB) | — |

### 2.2 Perubahan kode

1. **`app/Http/Requests/GenerateSchemaRequest.php`**
   - Hapus rule `acknowledge_mismatch` (lihat P4 bila dipertahankan parsial).
   - `withValidator`: hapus cabang bypass; mismatch selalu menambah error
     `target_framework` → 422 dengan pesan opsi valid.
2. **`resources/views/generator.blade.php`**
   - Hapus checkbox konfirmasi; banner amber menjadi penolakan keras
     ("Target X tidak didukung untuk proyek Laravel — pilih Laravel atau Raw SQL").
   - Submit tetap diblokir client-side; server-side 422 sebagai jaring pengaman.
   - Dropdown tetap preselect framework proyek (tidak berubah).
3. **`tests/Feature/GenerationAsyncTest.php`**
   - Ubah `test_generate_allows_mismatch_with_explicit_acknowledgement`
     menjadi ekspektasi **422** (atau hapus bila parameter dihapus total).

### 2.3 Acceptance Bagian A

- Proyek Laravel + target Drizzle → 422 + `assertJsonValidationErrors(['target_framework'])`,
  tidak ada job didispatch.
- Suite penuh hijau.

---

## 3. Bagian B — Scaffold proyek baru via terminal

### 3.1 Alur pengguna

Tab **"Buat Proyek Baru"** di modal tambah-proyek dashboard:
kartu template → input nama (slug, validasi live) → parent picker (dialog folder
existing) → opsi per template (khusus Spring: groupId/artifactId/DB) →
submit → polling progres + tail log → sukses: proyek otomatis terdaftar +
menjadi proyek aktif → toast + reload.

### 3.2 Backend

1. **`app/Services/ScaffoldProjectService.php`** (baru)
   - Validasi ketat: nama `^[a-z0-9][a-z0-9-_]{1,60}$`, parent = direktori real +
     writable, target belum ada.
   - Membangun perintah sebagai **array argumen** (Symfony Process, tanpa
     interpolasi string shell):
     - Laravel: `composer create-project laravel/laravel <nama> --prefer-dist --no-interaction` (cwd = parent).
     - Express Prisma/Drizzle: `npm init -y` + `npm i ...` + tulis starter dari
       `resources/stubs/` (lihat P2).
     - Spring: unduh `starter.zip` Initializr API via HTTP client Laravel →
       ekstrak `ZipArchive` → hapus zip (lihat P1 untuk driver DB).
     - Raw: `mkdir` saja.
   - Timeout per template (±600 dtk), output dipotong untuk log.
2. **`app/Jobs/ScaffoldProjectJob.php`** + tabel **`scaffold_jobs`**
   (status `queued/processing/ready/failed/cancelled`, log, `project_id` hasil) —
   pola sama seperti `GenerateSchemaJob` Fase 7.
3. **Endpoint** (grup `/api`, throttle seperti generate):
   - `GET /api/scaffold/prerequisites` → `{composer, node, npm, java, php-zip, internet}`
     agar UI men-disable template tak didukung beserta alasannya.
   - `POST /api/projects/scaffold` (202) → validasi template/nama/parent/opsi.
   - `GET /api/scaffold/{id}/status`, `POST /api/scaffold/{id}/cancel`.
   - Sukses → auto `registerProject()` (deteksi menemukan framework-nya) +
     set proyek aktif.
4. **Tests:** validasi slug/target (422), `buildCommand` per template tanpa eksekusi,
   job sukses dengan runner ter-mock, prerequisites memuat flag tool.

### 3.3 Frontend

- Tab baru di modal dashboard + kartu template + slug checker + parent picker +
  opsi Spring + polling progres/log + toast hasil.
- Template yang prasyaratnya gagal tampil disabled dengan alasan
  (mis. "Butuh koneksi internet" untuk Spring).

### 3.4 TASKS

- Tambah `TASK-606` (service + job + endpoint + tests) dan `TASK-607` (UI tab +
  polling) di Fase 6.

### 3.5 Acceptance Bagian B

- Laravel: folder terisi proyek valid (terdeteksi `laravel` saat auto-register).
- Express: `npm install` sukses + starter jalan (`node index.js` / `prisma validate`).
- Spring: zip terunduh & terekstrak, `pom.xml` berisi dependensi preset.
- Nama invalid / target sudah ada / tool hilang → 422 + pesan jelas, tanpa
  perintah dieksekusi.

---

## 4. Pertanyaan — jawab di sini sebelum eksekusi

### P1. Database default untuk scaffold Spring Boot

Preset dependensi: `web,data-jpa` + **satu** driver di bawah.

- [x] **H2** (disarankan) — embedded, zero-setup, aplikasi langsung jalan.
- [ ] **MySQL** — butuh server MySQL + kredensial dari user.
- [ ] **PostgreSQL** — butuh server PostgreSQL + kredensial dari user.

_Jawaban P1:_ **H2 (Embedded)** — Zero-setup dan aplikasi Spring Boot langsung bisa dijalankan tanpa server database eksternal. Pengguna dapat mengubah dialek database target (MySQL/PostgreSQL) kapan saja saat merancang skema di DEVArchitect.

### P2. Metode scaffolding Express (Prisma & Drizzle)

- [x] **Template minimal milik kita + `npm install`** (disarankan) — starter dari
  `resources/stubs/` + instalasi dependensi via npm. Deterministik & mudah di-test.
- [ ] **Tool resmi** (`express-generator` + `prisma init` / `drizzle-kit`) —
  mengikuti ekosistem resmi, tetapi bergantung unduhan `npx` (tidak deterministik,
  wajib internet penuh).

_Jawaban P2:_ **Template minimal milik kita + `npm install`** — Menggunakan starter bersih dari `resources/stubs/` agar cepat, deterministik, tidak rentan perubahan interaktif `npx`, dan struktur foldernya dijamin 100% kompatibel dengan injektor skema Prisma/Drizzle DEVArchitect.

### P3. Direktori parent default untuk proyek baru

- [x] **Direktori kerja khusus** (disarankan), contoh: `~/DEVArchitect-projects`
  (dibuat otomatis bila belum ada).
- [ ] **Bebas pilih tiap kali** (tanpa default).

_Jawaban P3:_ **Direktori kerja khusus**: `C:\Users\Indra\Documents\DEVArchitect-Projects` (dibuat otomatis jika belum ada) dengan opsi dialog tombol "Pilih Folder..." agar pengguna tetap bebas memilih lokasi lain kapan saja.

### P4. Mekanisme `acknowledge_mismatch` pada Opsi B

- [x] **Hapus total** (disarankan) — tidak ada parameter bypass & checkbox;
  semua mismatch selalu 422. (Proyek `RAW_SQL` tak terpengaruh karena memang
  membolehkan semua target.)
- [ ] **Pertahankan khusus proyek RAW_SQL** — praktisnya tidak pernah terpakai.

_Jawaban P4:_ **Hapus total** — Hapus checkbox dan parameter `acknowledge_mismatch`. Validasi backend 422 ketat diberlakukan untuk pasangan framework ↔ target ORM yang tidak kompatibel. Proyek tipe `raw_sql` tetap bebas memilih target apa pun.

### Catatan tambahan (opsional)

1. Eksekusi proses CLI (seperti `composer create-project` dan `npm install`) dijalankan asinkron melalui Queue Job (`ScaffoldProjectJob`) dengan pemantauan status & log real-time agar UI NativePHP tidak membeku (*freeze*).
2. Tetap mematuhi arahan tegas: **SEMUA PERUBAHAN HANYA DI LOKAL, JANGAN DI-PUSH KE GITHUB**.
