# DEVArchitect: Existing Project Introspection, Reverse ERD & AI Documentation Sync

Dokumen spesifikasi arsitektur dan panduan implementasi fitur **Smart Detection Database**, **Reverse-Engineering ERD (0 Token)**, dan **Sinkronisasi Otomatis ke Asisten Dokumen AI** untuk proyek yang sudah berjalan (*existing projects*).

---

## 1. Latar Belakang & Masalah Utama

### 1.1. Kasus Penggunaan Riil (Problem Statement)
Saat ini, ketika pengguna memasukkan proyek yang sudah jadi atau sedang berjalan (misalnya proyek Express.js dengan Prisma ORM dan PostgreSQL):
1. **Deteksi Database Tidak Akurat:**
   * DEVArchitect hanya mendeteksi framework umum dari `package.json`.
   * Sistem belum membaca file konfigurasi database seperti `prisma/schema.prisma` atau `.env`.
   * Akibatnya, proyek Express + Prisma dengan PostgreSQL terdeteksi sebagai `Raw SQL · SQL` atau fallback hardcoded yang tidak mencerminkan database aslinya.
2. **Skema Existing Tidak Terbaca (Blank Slate Problem):**
   * Saat proyek dibuka di halaman Generator, aplikasi mengasumsikan pengguna ingin membuat skema dari nol.
   * Tabel-tabel dan relasi yang sudah ada di dalam proyek tidak ditampilkan ke dalam Canvas / diagram ERD.
3. **Ketiadaan Jembatan ke Asisten Dokumen:**
   * Pengguna yang ingin membuat Software Requirement Document (SRS), Product Requirement Document (PRD), atau arsitektur teknis dari proyek yang sudah berjalan harus mengetik ulang seluruh struktur datanya secara manual di Asisten AI.

### 1.2. Solusi yang Dihadirkan (*Value Proposition*)
* **Smart Database Detection (0 Token):** Membaca berkas konfigurasi lokal secara otomatis untuk menentukan database engine yang akurat (PostgreSQL, MySQL, SQLite, SQL Server).
* **Local Schema Reverse-Engineering (0 Token):** Mem-parse skema lokal (`schema.prisma`, migrasi Laravel, dll.) langsung di mesin klien/backend lokal tanpa memanggil LLM.
* **Instant Interactive ERD:** Menampilkan diagram relasi database yang sudah ada secara visual dan real-time.
* **Smart Documentation Generator (~1.000 Token / Free Model):** Mengirim ringkasan metadata struktur data ke Asisten Dokumen AI untuk menyusun dokumen arsitektur dan PRD secara instan dan hemat biaya.

---

## 2. Arsitektur Teknis & Token Economics

```
+-----------------------------------------------------------------------------------+
|                            KOMPUTER LOKAL (0 TOKEN)                               |
|                                                                                   |
|  [ Folder Project User ]                                                          |
|         │                                                                         |
|         ├─► prisma/schema.prisma  ──► [ Local Prisma Parser ]                     |
|         ├─► .env / database.php   ──► [ Local Config Parser ]                     |
|         │                                    │                                    |
|         │                                    ▼                                    |
|         │                        1. Deteksi DB: "PostgreSQL"                      |
|         │                        2. Model, Kolom & Relasi (JSON)                  |
|         │                        3. Mermaid ERD Code                              |
|         │                                    │                                    |
|         │                                    ▼                                    |
|         │                        [ Visualisasi ERD Canvas ]                       |
|         │                               (0 Token)                                 |
+─────────┼────────────────────────────────────┼────────────────────────────────────+
          │                                    │
          │                                    ▼ (Hanya kirim ringkasan metadata)
          │                        [ Context Extractor ] (~400 token)
          │                                    │
          │                                    ▼
          │                        [ OPENROUTER API (AI) ]
          │                        Model: nemotron-3.5-lightning:free
          │                        (Total: ~1.000 token / Biaya: Rp 0)
          │                                    │
          ▼                                    ▼
[ Dashboard & Generator ]           [ Asisten Dokumen AI ]
Status: Express + Prisma · PG       Dokumen: PRD, Arsitektur & Outline Fitur
```

### 2.1. Mengapa Visualisasi ERD Membutuhkan 0 Token?
File `schema.prisma` dan migrasi framework memiliki sintaksis deklaratif yang deterministik (baku). Dengan menggunakan regular expression parser atau parser AST lokal berbasis PHP, sistem dapat mengurai:
* Nama model (`model User`, `model Device`)
* Field dan tipe data (`id Int`, `name String`, `createdAt DateTime`)
* Atribut penting (`@id`, `@unique`, `@default`)
* Relasi antartabel (`@relation(fields: [userId], references: [id])`)

Proses ini berjalan dalam hitungan milidetik secara offline, tidak memerlukan internet, dan **100% bebas biaya token**.

### 2.2. Perhitungan Token untuk Ringkasan & Saran Dokumen
Alih-alih mengirimkan seluruh berkas kode aplikasi (yang dapat memakan 50.000+ token), sistem hanya mengekstrak intisari arsitektur:
* **Metadata Proyek (`package.json`):** Nama aplikasi, dependencies, versi (~50 token).
* **Ringkasan Entitas & Relasi:** Daftar nama tabel dan relasinya yang sudah diekstrak oleh parser lokal (~300 – 600 token).
* **Instruksi Analisis (System Prompt):** Format permintaan ringkasan teknis dan rekomendasi dokumen (~150 token).
* **Hasil Analisis AI (Output):** Ringkasan kapabilitas sistem dan saran modul dokumen (~400 – 600 token).

> **Total Estimasi:** ~1.000 – 1.200 token per proyek.  
> **Model AI yang Digunakan:** Menggunakan `OPENROUTER_MODEL_DOCS` (`nvidia/nemotron-3.5-lightning:free` yang sudah terpasang di `.env`), sehingga operasional fitur ini **gratis**.

---

## 3. Rincian Alur Pengguna (User Experience Flow)

1. **Import Proyek di Dashboard:**
   * Pengguna mengklik `+ Tambah Project` -> tab *Buka Folder yang Ada*.
   * Pengguna memilih folder proyek (misalnya: `C:\...\Project_Iot\backend`).
   * **Smart Detection Aktif:**
     * Sistem mendeteksi `Express + Prisma` dari `package.json`.
     * Sistem membaca `prisma/schema.prisma`, menemukan `provider = "postgresql"`, lalu otomatis memilih opsi **PostgreSQL**.
     * Pesan validasi menampilkan: *"Terdeteksi: Express.js (Prisma ORM) dengan database PostgreSQL"*.
2. **Tampilan Kartu di Dashboard:**
   * Kartu proyek langsung menampilkan identitas presisi:  
     `Express + Prisma · PostgreSQL` (bukan sekadar `SQL` generik).
3. **Buka Proyek (Halaman Generator / Workspace):**
   * Di panel informasi proyek, muncul tombol dan status baru:  
     `[ 👁️ Lihat ERD Proyek Saat Ini ]` dan `[ 📄 Buat Dokumentasi AI ]`.
   * Mengklik *Lihat ERD Proyek* akan merender diagram tabel lengkap yang sudah ada di proyek tersebut secara visual.
4. **Pembuatan Dokumen AI Otomatis:**
   * Mengklik *Buat Dokumentasi AI* akan membawa pengguna ke halaman `/assistant`.
   * AI langsung menyapa dengan ringkasan:  
     *"Saya mendeteksi proyek backend IoT dengan 8 model database (User, Device, SensorData, Alert, dll.). Apakah Anda ingin saya buatkan PRD fitur, kamus data arsitektur, atau spesifikasi endpoint API?"*
   * Pengguna dapat memilih template dokumen yang diinginkan dalam satu klik.

---

## 4. Rincian Teknis Implementasi

### 4.1. Modifikasi Database (`projects` table)
Menambahkan kolom `database_dialect` ke tabel `projects` agar jenis database disimpan secara permanen di tingkat proyek, tidak hanya mengandalkan riwayat `generations`.

```sql
ALTER TABLE projects ADD COLUMN database_dialect VARCHAR(50) DEFAULT 'mysql';
```

### 4.2. Local Config & Dialect Detectors (`ProjectService.php`)
Menambahkan deteksi spesifik berdasarkan berkas konfigurasi:

```php
// Deteksi database untuk Prisma
if (File::exists($realPath . '/prisma/schema.prisma')) {
    $content = File::get($realPath . '/prisma/schema.prisma');
    if (preg_match('/provider\s*=\s*["\'](postgresql|postgres)["\']/i', $content)) {
        return 'pgsql';
    } elseif (preg_match('/provider\s*=\s*["\'](mysql)["\']/i', $content)) {
        return 'mysql';
    } elseif (preg_match('/provider\s*=\s*["\'](sqlite)["\']/i', $content)) {
        return 'sqlite';
    } elseif (preg_match('/provider\s*=\s*["\'](sqlserver)["\']/i', $content)) {
        return 'sqlsrv';
    }
}

// Deteksi database untuk Laravel (.env)
if (File::exists($realPath . '/.env')) {
    $envContent = File::get($realPath . '/.env');
    if (preg_match('/^DB_CONNECTION=(.*)$/m', $envContent, $matches)) {
        return trim($matches[1]);
    }
}
```

### 4.3. Parser Skema Prisma Lokal (`PrismaSchemaParser.php`)
Membuat parser independen di `app/Services/Parsers/PrismaSchemaParser.php`:
* **Input:** String isi berkas `schema.prisma`.
* **Output:**
  * Struktur Array/JSON berisi daftar model, kolom, tipe, atribut, dan relasi.
  * String kode diagram Mermaid ERD (`erDiagram ...`).

---

## 5. Tabel Checklist Pengerjaan (Implementation Checklist)

Berikut adalah daftar tugas terstruktur untuk mengeksekusi fitur ini secara bertahap:

| No | Fase | Task ID | Uraian Tugas | File / Komponen Target | Biaya Token | Status | Prioritas |
|:--:|:----:|:-------:|:-------------|:-----------------------|:-----------:|:------:|:---------:|
| **1** | **Fondasi & Deteksi** | `TASK-DET-01` | Tambah kolom `database_dialect` pada tabel `projects` via migration Laravel. | `database/migrations/*_add_database_dialect_to_projects.php`, `app/Models/Project.php` | 0 Token | 🟡 To Do | **P0 (Critical)** |
| **2** | **Fondasi & Deteksi** | `TASK-DET-02` | Implementasi `detectDatabaseDialect(string $path)` di `ProjectService` untuk memeriksa `schema.prisma`, `.env`, dan `application.properties`. | `app/Services/ProjectService.php` | 0 Token | 🟡 To Do | **P0 (Critical)** |
| **3** | **UI Tambah Project** | `TASK-UI-01` | Tambah dropdown pilihan Jenis Database di modal *"Buka Folder yang Ada"*, dengan nilai default terisi otomatis dari hasil auto-detection. | `resources/views/dashboard/partials/add-project-modal.blade.php`, `resources/js/dashboard.js` | 0 Token | 🟡 To Do | **P0 (Critical)** |
| **4** | **UI Dashboard** | `TASK-UI-02` | Update kartu Dashboard (Hero, Grid, List) untuk memprioritaskan kolom `database_dialect` dari model proyek langsung. | `resources/views/dashboard.blade.php` | 0 Token | 🟡 To Do | **P1 (High)** |
| **5** | **Local Parser** | `TASK-PRS-01` | Buat service `PrismaSchemaParser` untuk mengekstrak model, kolom, dan relasi Prisma tanpa LLM. | `app/Services/Parsers/PrismaSchemaParser.php` | 0 Token | 🟡 To Do | **P0 (Critical)** |
| **6** | **Local Parser** | `TASK-PRS-02` | Buat converter dari hasil parse Prisma ke format Mermaid ERD diagram string. | `app/Services/Parsers/PrismaSchemaParser.php` | 0 Token | 🟡 To Do | **P1 (High)** |
| **7** | **Visualisasi ERD** | `TASK-ERD-01` | Buat endpoint API `/api/projects/{id}/existing-schema` untuk mereturn skema hasil parse lokal proyek aktif. | `app/Http/Controllers/ProjectController.php`, `routes/web.php` | 0 Token | 🟡 To Do | **P1 (High)** |
| **8** | **Visualisasi ERD** | `TASK-ERD-02` | Tampilkan modal/panel preview *"ERD Proyek Existing"* di halaman Generator menggunakan engine Mermaid yang sudah ada. | `resources/views/generator.blade.php`, `resources/js/erd-viewer.js` | 0 Token | 🟡 To Do | **P1 (High)** |
| **9** | **Asisten AI Sync** | `TASK-DOC-01` | Buat service `ProjectSummaryService` untuk membuat ringkasan arsitektural padat (~400 token) dari hasil parser lokal. | `app/Services/Doc/ProjectSummaryService.php` | 0 Token | 🟡 To Do | **P2 (Medium)** |
| **10** | **Asisten AI Sync** | `TASK-DOC-02` | Tambahkan tombol *"Kirim ke Asisten Dokumen"* yang membuka `/assistant` dengan preloaded context dari proyek yang sedang berjalan. | `resources/views/generator.blade.php`, `resources/views/assistant.blade.php`, `resources/js/doc-assistant.js` | ~1.000 Token (Free Model) | 🟡 To Do | **P2 (Medium)** |
| **11** | **Pengujian & QA** | `TASK-QA-01` | Buat unit test PHPUnit untuk pengujian deteksi dialek Prisma/Laravel dan keakuratan parsing tabel. | `tests/Feature/ProjectIntrospectionTest.php` | 0 Token | 🟡 To Do | **P1 (High)** |

---

## 6. Rencana Pengujian & Validasi

### 6.1. Unit Testing
* **Uji Kasus 1 (Express + Prisma):**
  * Folder dengan `package.json` (prisma) dan `prisma/schema.prisma` yang memiliki `provider = "postgresql"`.
  * Hasil yang diharapkan: Framework terdeteksi `express_prisma`, dialect terdeteksi `pgsql`.
* **Uji Kasus 2 (Laravel):**
  * Folder dengan `artisan` dan `.env` yang memiliki `DB_CONNECTION=mysql`.
  * Hasil yang diharapkan: Framework `laravel`, dialect `mysql`.
* **Uji Kasus 3 (Prisma Local Parser):**
  * File `schema.prisma` dengan 3 model berelasi (User, Post, Comment).
  * Hasil yang diharapkan: 3 tabel diekstrak dengan tipe data yang tepat, dan Mermaid code valid terbuat dalam 0 token.

### 6.2. Validasi Visual & Konsumsi Token
* Pastikan badge di Dashboard berubah menjadi:  
  `Express + Prisma · POSTGRESQL`.
* Diagram ERD dapat dirender secara visual di browser tanpa eror parser.
* Panggilan ke Asisten Dokumen memverifikasi penggunaan model `nvidia/nemotron-3.5-lightning:free` dengan penggunaan token di bawah 1.500 token.
