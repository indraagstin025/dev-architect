# Software Requirements Specification (SRS)
## Aplikasi Desktop Generator Migrasi & ERD Laravel Berbasis AI

| Informasi Dokumen | Keterangan |
|---|---|
| Nama Produk | Laravel AI Migration & ERD Generator (Desktop App) |
| Platform | Desktop (Windows/macOS), berbasis NativePHP |
| Database Aplikasi & Target Migrasi | Supabase (PostgreSQL) |
| Versi Dokumen | 1.0 |
| Status | Draft |
| Dokumen Acuan | URD_Laravel_Migration_AI_Generator.md (v1.2), PRD_Laravel_Migration_AI_Generator.md (v1.2) |

---

## 1. Pendahuluan

### 1.1 Tujuan
Dokumen ini menerjemahkan kebutuhan pengguna (URD) dan kebutuhan produk (PRD) menjadi spesifikasi teknis yang cukup rinci untuk digunakan tim engineering dalam merancang arsitektur, skema database, antarmuka, dan logika sistem. SRS ini menjadi acuan pengembangan dan pengujian (QA).

### 1.2 Ruang Lingkup Perangkat Lunak
Perangkat lunak berupa aplikasi desktop native (Windows & macOS) berbasis **NativePHP** yang:
- Mengambil input prompt dari pengguna dan mengirimkannya ke penyedia AI (OpenAI, Anthropic, atau Ollama lokal).
- Menyimpan hasil generate sebagai draft di **Supabase (PostgreSQL)**.
- Menampilkan hasil sebagai diagram ERD interaktif dan kode migrasi Laravel yang dapat ditinjau/diedit (mode dry-run).
- Menulis file migrasi `.php` ke folder `database/migrations/` proyek Laravel lokal milik pengguna saat pengguna mengonfirmasi (Inject).
- Mendukung pengelolaan multi-proyek melalui Project History Dashboard yang datanya tersimpan di Supabase.

### 1.3 Definisi, Akronim, dan Istilah

| Istilah | Definisi |
|---|---|
| SRS | Software Requirements Specification |
| FR | Functional Requirement |
| NFR | Non-Functional Requirement |
| NativePHP | Framework desktop shell berbasis Laravel/PHP |
| Supabase | Backend-as-a-Service berbasis PostgreSQL, digunakan sebagai database aplikasi & target migrasi |
| Draft | Record hasil generate AI yang tersimpan di Supabase dengan `status = draft`, belum ditulis ke disk lokal |
| Injected | Status record setelah file migrasi berhasil ditulis ke disk lokal |
| Dry-run | Mode pratinjau hasil generate tanpa menulis file fisik ke disk |
| LLM | Large Language Model |
| IPC | Inter-Process Communication (komunikasi antara proses UI dan proses backend PHP pada NativePHP) |

### 1.4 Referensi
- URD_Laravel_Migration_AI_Generator.md (v1.2)
- PRD_Laravel_Migration_AI_Generator.md (v1.2)
- Dokumentasi resmi NativePHP
- Dokumentasi resmi Supabase (PostgreSQL, REST/PostgREST, Realtime, Auth)
- Dokumentasi API OpenAI, Anthropic, dan Ollama

### 1.5 Gambaran Umum Dokumen
Bagian 2 menjelaskan deskripsi umum sistem. Bagian 3 memuat kebutuhan fungsional per modul (dipetakan dari ID URD ke ID SRS). Bagian 4 memuat kebutuhan antarmuka eksternal. Bagian 5 memuat kebutuhan non-fungsional. Bagian 6 memuat desain data (skema Supabase). Bagian 7 memuat arsitektur sistem. Bagian 8 memuat use case. Bagian 9 memuat matriks keterlusuran (traceability).

---

## 2. Deskripsi Umum

### 2.1 Perspektif Produk
Aplikasi berjalan sepenuhnya di sisi klien (desktop), dengan dua dependensi eksternal utama:
1. **Penyedia AI** (OpenAI/Anthropic via API online, atau Ollama yang berjalan lokal) — untuk menghasilkan rancangan skema.
2. **Supabase** — sebagai database aplikasi (metadata proyek, draft hasil generate) sekaligus representasi dari database target migrasi PostgreSQL milik pengguna.

Penulisan file migrasi `.php` dilakukan langsung ke sistem berkas lokal komputer pengguna melalui kapabilitas filesystem NativePHP — bukan dikirim melalui jaringan.

### 2.2 Fungsi Utama Produk
1. Manajemen proyek (tambah, pilih, hapus, ubah nama) melalui Project History Dashboard.
2. Menerima prompt pengguna dan meneruskannya ke driver AI yang dipilih.
3. Menyimpan hasil AI sebagai draft di Supabase.
4. Menampilkan draft sebagai ERD interaktif dan kode migrasi (mode dry-run).
5. Memfasilitasi edit manual atas kode migrasi.
6. Memvalidasi kompatibilitas kode terhadap dialek PostgreSQL.
7. Menulis file migrasi ke disk lokal dan memperbarui status draft menjadi `injected`.
8. Integrasi sistem operasi: tray, hotkey global, notifikasi native.

### 2.3 Karakteristik Pengguna
Pengguna adalah developer Laravel (individu, tim kecil, atau pelajar) dengan pemahaman dasar–menengah tentang struktur proyek Laravel dan migrasi database. Pengguna diasumsikan sudah memiliki akun Supabase (untuk proyek target) dan/atau API key penyedia AI online jika memilih mode tersebut.

### 2.4 Batasan Umum
- Tidak mendukung dialek database selain PostgreSQL pada v1.0.
- Tidak menjalankan `php artisan migrate` secara otomatis.
- Tidak menghasilkan seeder/factory pada v1.0 (dijadwalkan v1.1).
- Membutuhkan koneksi internet aktif untuk Project History Dashboard, penyimpanan draft, dan driver AI online (mode Ollama lokal tidak memerlukan internet untuk proses generate-nya sendiri, namun tetap membutuhkan internet untuk sinkronisasi draft/dashboard ke Supabase).

### 2.5 Asumsi dan Dependensi
- Pengguna memiliki proyek Supabase aktif dan kredensial koneksi yang valid.
- Pengguna memiliki lingkungan PHP/Laravel/Composer terpasang di komputernya.
- Versi Ollama dan model lokal (DeepSeek/Llama) sudah terpasang bila memilih mode AI lokal.

---

## 3. Kebutuhan Fungsional (Functional Requirements)

Setiap kebutuhan fungsional (**SRS-FR-xxx**) dipetakan ke kebutuhan pengguna terkait di URD (kolom "Sumber URD").

### 3.1 Modul Manajemen Proyek & Integrasi Desktop

**SRS-FR-001 — Menampilkan Project History Dashboard**
- Sumber URD: UR-INT-07
- Input: Permintaan buka aplikasi.
- Proses: Sistem melakukan query `SELECT * FROM projects ORDER BY updated_at DESC` ke Supabase.
- Output: Daftar proyek (nama, path, tanggal terakhir diakses) ditampilkan sebagai layar awal.
- Exception: Jika koneksi ke Supabase gagal, tampilkan pesan error dan opsi "Coba Lagi" atau "Kerja Offline dengan proyek terakhir yang di-cache" (jika NFR caching diterapkan).

**SRS-FR-002 — Menambahkan Proyek Baru**
- Sumber URD: UR-INT-01, UR-INT-06, UR-INT-08
- Input: Klik tombol "Tambah Proyek" → dialog folder native OS.
- Proses:
  1. Pengguna memilih folder via native directory dialog.
  2. Sistem memvalidasi keberadaan `artisan` dan `composer.json` di root folder.
  3. Jika valid, sistem menyimpan record baru ke tabel `projects` (`project_name`, `absolute_path`).
  4. Jika tidak valid, tampilkan pesan error "Folder ini bukan proyek Laravel yang valid".
- Output: Proyek baru muncul di Project History Dashboard.

**SRS-FR-003 — Memilih/Membuka Proyek**
- Sumber URD: UR-INT-02, UR-INT-08
- Proses: Saat pengguna memilih proyek dari dashboard, sistem menetapkan `project_id` tersebut sebagai konteks aktif untuk seluruh operasi generate/inject berikutnya.

**SRS-FR-004 — Mengelola Proyek (Ubah Nama/Hapus)**
- Sumber URD: UR-INT-09
- Proses: Update atau delete record pada tabel `projects`. Penghapusan proyek disertai dialog konfirmasi karena akan menyembunyikan/menghapus riwayat draft terkait (soft-delete direkomendasikan, lihat 6.1).

**SRS-FR-005 — System Tray & Minimize**
- Sumber URD: UR-INT-03
- Proses: Saat pengguna menutup jendela (klik "X"), aplikasi tidak keluar sepenuhnya melainkan diminimalkan ke tray/top bar, dan proses background NativePHP tetap berjalan.

**SRS-FR-006 — Global Hotkey Activator**
- Sumber URD: UR-INT-04
- Proses: Sistem mendaftarkan global keyboard shortcut (default `Ctrl+Alt+A`) melalui API native OS saat aplikasi pertama kali dijalankan. Kombinasi dapat dikustomisasi di halaman pengaturan.

**SRS-FR-007 — Native OS Notification**
- Sumber URD: UR-INT-05
- Proses: Sistem memicu notifikasi native OS pada dua event: (a) proses generate AI selesai, (b) proses inject file migrasi selesai.

### 3.2 Modul Generator & AI Playground

**SRS-FR-008 — Input Prompt**
- Sumber URD: UR-GEN-01, UR-GEN-02
- Proses: Kolom teks multiline menampilkan placeholder contoh prompt saat kosong; placeholder hilang otomatis saat pengguna mulai mengetik.

**SRS-FR-009 — Pemilihan Target Laravel**
- Sumber URD: UR-GEN-03
- Proses: Dropdown versi Laravel memengaruhi template prompt sistem (system prompt) yang dikirim ke AI, agar sintaks migrasi sesuai konvensi versi terpilih.

**SRS-FR-010 — Konfigurasi Driver AI**
- Sumber URD: UR-GEN-04, UR-GEN-05
- Proses: Pengguna memilih salah satu driver (OpenAI, Anthropic, Ollama). Untuk driver online, sistem meminta API key dan menyimpannya terenkripsi (lihat NFR-Keamanan). Untuk Ollama, sistem memeriksa apakah endpoint lokal (`http://localhost:11434` atau sesuai konfigurasi) aktif.

**SRS-FR-011 — Penguncian Target Database ke PostgreSQL**
- Sumber URD: UR-GEN-09
- Proses: System prompt AI menyertakan instruksi eksplisit bahwa seluruh output harus menggunakan sintaks Laravel Schema Builder yang menghasilkan DDL kompatibel PostgreSQL, serta daftar mapping tipe data (lihat 6.3).

**SRS-FR-012 — Konfigurasi Koneksi Supabase**
- Sumber URD: UR-GEN-10
- Proses: Form pengaturan menyimpan host, port, nama database, user, dan password koneksi Supabase milik proyek pengguna (terpisah dari kredensial Supabase milik aplikasi itu sendiri untuk metadata).

**SRS-FR-013 — Proses Generate Asynchronous**
- Sumber URD: UR-GEN-06, UR-GEN-08
- Proses:
  1. Sistem mengirim request ke API driver AI terpilih secara asynchronous (non-blocking terhadap UI thread).
  2. Indikator loading (spinner) ditampilkan selama menunggu respons.
  3. Jika request gagal (timeout, API key salah, koneksi terputus), sistem menampilkan pesan error spesifik sesuai jenis kegagalan.

**SRS-FR-014 — Cancel Generate**
- Sumber URD: UR-GEN-07
- Proses: Tombol "Batal" menghentikan request HTTP yang sedang berjalan dan mengembalikan UI ke kondisi siap generate.

**SRS-FR-015 — Penyimpanan Draft ke Supabase**
- Sumber URD: UR-GEN-11
- Proses: Setelah respons AI diterima dan diparsing (kode migrasi + teks ERD/Mermaid), sistem melakukan `INSERT` ke tabel `generations` dengan `status = 'draft'`, `project_id` sesuai konteks aktif.

### 3.3 Modul Visualizer ERD & Manajemen Kode

**SRS-FR-016 — Render ERD Interaktif (Dry-Run)**
- Sumber URD: UR-VIS-01, UR-VIS-02, UR-VIS-11
- Proses: Sistem membaca `erd_mermaid_text` dari record draft di Supabase dan me-render-nya di kanvas interaktif yang mendukung pan & zoom. Tidak ada interaksi dengan filesystem pada tahap ini.

**SRS-FR-017 — Smart Code Review Tab**
- Sumber URD: UR-VIS-03
- Proses: Sistem membaca `migration_files` (jsonb) dari record draft dan menampilkan setiap file sebagai tab terpisah dengan syntax highlighting PHP.

**SRS-FR-018 — Inline Code Editor**
- Sumber URD: UR-VIS-04
- Proses: Perubahan pada editor kode disimpan sementara di state aplikasi, dan disinkronkan (`UPDATE migration_files`) ke record draft di Supabase saat pengguna berpindah tab atau menekan simpan-draft.

**SRS-FR-019 — Validasi Tipe Data PostgreSQL**
- Sumber URD: UR-VIS-10
- Proses: Sebelum tombol Inject diaktifkan, sistem menjalankan validator yang memeriksa setiap definisi kolom terhadap daftar tipe data valid PostgreSQL (lihat 6.3). Jika ditemukan tipe tidak valid/sintaks MySQL, tombol Inject dinonaktifkan dan pesan kesalahan ditampilkan per baris kode.

**SRS-FR-020 — Deteksi Konflik Nama File**
- Sumber URD: UR-VIS-08
- Proses: Sebelum inject, sistem membandingkan nama tabel/nama file yang akan dibuat dengan daftar file yang sudah ada di `database/migrations/` folder aktif. Jika ditemukan potensi duplikat, tampilkan peringatan dan minta konfirmasi pengguna.

**SRS-FR-021 — Migration Injector**
- Sumber URD: UR-VIS-06, UR-VIS-07
- Proses:
  1. Sistem menghasilkan nama file dengan format `YYYY_MM_DD_HHMMSS_nama_tabel.php`, dengan urutan timestamp yang inkremental logis antar file dalam satu batch.
  2. Sistem menulis seluruh file ke `{project.absolute_path}/database/migrations/` menggunakan operasi tulis atomik (tulis ke file sementara, lalu rename).
  3. Jika seluruh file berhasil ditulis, sistem melakukan `UPDATE generations SET status = 'injected'` pada record terkait.
  4. Jika terjadi kegagalan di tengah proses (mis. permission error), sistem melakukan rollback (menghapus file yang sudah sempat ditulis pada batch tersebut) dan status draft **tidak** diubah menjadi `injected`.

**SRS-FR-022 — ERD Exporter**
- Sumber URD: UR-VIS-05
- Proses: Tombol ekspor mengonversi kanvas ERD menjadi berkas PNG atau SVG dan menyimpannya ke lokasi yang dipilih pengguna via native save dialog.

**SRS-FR-023 — Riwayat Generate per Proyek**
- Sumber URD: UR-VIS-09
- Proses: Sistem menampilkan daftar seluruh record `generations` (status `draft` maupun `injected`) untuk `project_id` aktif, diurutkan dari yang terbaru, dapat dibuka kembali untuk ditinjau atau dilanjutkan.

---

## 4. Kebutuhan Antarmuka Eksternal

### 4.1 Antarmuka Pengguna (UI)
- Layar Project History Dashboard (grid/list kartu proyek).
- Layar Generator (Smart Prompt Area, dropdown target Laravel, dropdown driver AI).
- Layar Visualizer (split-view: kanvas ERD di satu sisi, tab kode migrasi di sisi lain).
- Layar Pengaturan (API key, koneksi Supabase, hotkey kustom).

### 4.2 Antarmuka Perangkat Keras
- Tidak ada kebutuhan perangkat keras khusus di luar spesifikasi minimum menjalankan aplikasi desktop modern (RAM ≥ 8GB direkomendasikan, lebih tinggi jika menjalankan model Ollama lokal).

### 4.3 Antarmuka Perangkat Lunak
| Komponen Eksternal | Protokol/Metode | Keterangan |
|---|---|---|
| OpenAI API | HTTPS REST | Autentikasi via API key, endpoint chat/completions |
| Anthropic API | HTTPS REST | Autentikasi via API key, endpoint messages |
| Ollama (lokal) | HTTP REST (localhost) | Tidak memerlukan API key, memerlukan runtime Ollama aktif |
| Supabase | HTTPS REST (PostgREST) dan/atau koneksi PostgreSQL langsung | Digunakan untuk tabel `projects` dan `generations`; kredensial dikelola terpisah dari kredensial Supabase target migrasi pengguna |
| Filesystem OS | NativePHP Filesystem API | Operasi baca (validasi proyek, deteksi konflik) dan tulis (injeksi file migrasi) |
| OS Native APIs | NativePHP Tray/Hotkey/Notification API | Tray icon, global shortcut registration, notification API |

### 4.4 Antarmuka Komunikasi
- Seluruh komunikasi ke API eksternal (AI online, Supabase) menggunakan HTTPS/TLS.
- Komunikasi ke Ollama lokal menggunakan HTTP pada `localhost` (tidak melewati jaringan publik).

---

## 5. Kebutuhan Non-Fungsional

| ID | Kategori | Deskripsi | Sumber URD |
|---|---|---|---|
| SRS-NFR-01 | Performa | UI tidak boleh blocking selama proses generate AI (operasi dijalankan di thread/proses terpisah dari UI thread). | NFR-01 |
| SRS-NFR-02 | Kompatibilitas | Berjalan pada Windows 10/11 dan 2 versi terakhir macOS. | NFR-02 |
| SRS-NFR-03 | Keamanan | API key AI dan kredensial koneksi Supabase target disimpan terenkripsi (mis. AES-256) di local secure storage; tidak pernah dikirim ke pihak selain endpoint resminya. | NFR-03 |
| SRS-NFR-04 | Keandalan | Operasi tulis file migrasi bersifat atomik dan dapat di-rollback bila gagal di tengah proses. | NFR-04 |
| SRS-NFR-05 | Usabilitas | Alur utama (dashboard → prompt → generate → dry-run review → inject) dapat diselesaikan pengguna baru tanpa training tambahan. | NFR-05 |
| SRS-NFR-06 | Ketersediaan | Mode generate via Ollama tetap berfungsi tanpa internet; fitur yang bergantung pada Supabase (dashboard, draft) memerlukan koneksi aktif. | NFR-06 |
| SRS-NFR-07 | Portabilitas Data | Data proyek dan draft tersimpan terpusat di Supabase sehingga dapat diakses lintas sesi/perangkat dengan akun yang sama. | NFR-07 |
| SRS-NFR-08 | Konsistensi | Status `injected` hanya boleh diset setelah seluruh file dalam satu batch berhasil ditulis; kegagalan sebagian batch tidak boleh menghasilkan status `injected` parsial. | NFR-08 |
| SRS-NFR-09 | Skalabilitas | Sistem harus tetap responsif untuk pengguna dengan >20 proyek tersimpan dan riwayat generate >100 record per proyek (gunakan pagination pada query Supabase). | — |
| SRS-NFR-10 | Observability | Setiap kegagalan (API AI, koneksi Supabase, penulisan file) harus dicatat ke log lokal aplikasi untuk keperluan debugging/dukungan pengguna. | — |

---

## 6. Desain Data

### 6.1 Skema Tabel Supabase (PostgreSQL)

**Tabel `projects`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | `uuid` (PK, default `gen_random_uuid()`) | Identitas unik proyek |
| `project_name` | `varchar(255)` | Nama proyek yang ditampilkan di dashboard |
| `absolute_path` | `text` | Path folder proyek Laravel di komputer pengguna |
| `is_deleted` | `boolean` (default `false`) | Soft-delete flag agar riwayat `generations` tidak ikut hilang |
| `created_at` | `timestamptz` (default `now()`) | Waktu proyek ditambahkan |
| `updated_at` | `timestamptz` (default `now()`) | Waktu terakhir proyek diakses/diubah |

**Tabel `generations`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | `uuid` (PK, default `gen_random_uuid()`) | Identitas unik hasil generate |
| `project_id` | `uuid` (FK → `projects.id`) | Relasi ke proyek terkait |
| `prompt_text` | `text` | Prompt asli yang dikirim pengguna |
| `laravel_target` | `varchar(20)` | Versi Laravel target saat generate |
| `ai_driver` | `varchar(50)` | Driver AI yang digunakan (openai/anthropic/ollama) |
| `erd_mermaid_text` | `text` | Representasi diagram ERD dalam format Mermaid |
| `migration_files` | `jsonb` | Array objek `{file_name, content}` untuk setiap file migrasi |
| `status` | `varchar(20)` (check: `draft`,`injected`) | Status siklus hidup draft |
| `injected_at` | `timestamptz` (nullable) | Waktu file berhasil disuntikkan ke disk |
| `created_at` | `timestamptz` (default `now()`) | Waktu draft dibuat |
| `updated_at` | `timestamptz` (default `now()`) | Waktu terakhir draft diubah (mis. hasil inline edit) |

### 6.2 Relasi
`projects (1) ────< (N) generations` — satu proyek dapat memiliki banyak record hasil generate/draft.

### 6.3 Mapping Tipe Data (Laravel Schema Builder → PostgreSQL)

| Tipe Laravel (Schema Builder) | Tipe PostgreSQL Dihasilkan |
|---|---|
| `$table->string()` | `varchar` |
| `$table->text()` | `text` |
| `$table->uuid()` | `uuid` |
| `$table->json()` / `$table->jsonb()` | `jsonb` |
| `$table->timestamp()` / `$table->timestamps()` | `timestamptz` |
| `$table->boolean()` | `boolean` |
| `$table->integer()` / `$table->bigInteger()` | `integer` / `bigint` |
| `$table->foreignUuid()` | `uuid` dengan constraint `FOREIGN KEY` |
| `$table->enum()` | Direkomendasikan menggunakan `check constraint` atau native PostgreSQL `ENUM` type, bukan sintaks `ENUM` khas MySQL |

Validator (SRS-FR-019) menggunakan tabel ini sebagai referensi untuk menandai tipe data yang tidak sesuai (mis. penggunaan sintaks MySQL `ENUM(...)` langsung).

---

## 7. Arsitektur Sistem (Tingkat Tinggi)

```
┌─────────────────────────────────────────────────────────────┐
│                     Aplikasi Desktop (NativePHP)              │
│                                                                 │
│  ┌───────────────┐   ┌─────────────────┐   ┌────────────────┐│
│  │ UI Layer       │   │ Application      │   │ Native OS       ││
│  │ (Dashboard,    │──▶│ Core / Services   │──▶│ Integration     ││
│  │ Generator, ERD │   │ (AI Adapter,      │   │ (Tray, Hotkey,  ││
│  │ Visualizer)    │   │ Validator,        │   │ Notification,   ││
│  │                │   │ File Writer)      │   │ Filesystem)     ││
│  └───────────────┘   └────────┬─────────┘   └────────────────┘│
└────────────────────────────────┼───────────────────────────────┘
                                  │
                 ┌────────────────┼─────────────────┐
                 ▼                ▼                 ▼
        ┌───────────────┐ ┌───────────────┐ ┌────────────────┐
        │ AI Provider    │ │ Supabase       │ │ Filesystem Lokal│
        │ (OpenAI /      │ │ (projects,     │ │ (database/      │
        │ Anthropic /    │ │ generations)   │ │ migrations/)    │
        │ Ollama lokal)  │ │                │ │                 │
        └───────────────┘ └───────────────┘ └────────────────┘
```

**Komponen inti (Application Core / Services):**
- **AI Adapter** — abstraksi driver AI (pattern adapter) yang menyeragamkan format request/response antar OpenAI, Anthropic, dan Ollama.
- **Draft Manager** — menangani penyimpanan dan pembaruan record `generations` di Supabase.
- **PostgreSQL Type Validator** — memvalidasi kode migrasi terhadap mapping tipe data pada bagian 6.3.
- **File Writer** — menangani penulisan atomik file migrasi ke disk dan pembaruan status draft.
- **Conflict Detector** — membandingkan nama file/tabel baru dengan isi folder `database/migrations/` yang sudah ada.

---

## 8. Use Case

### UC-01: Generate Skema Baru untuk Proyek Aktif
- **Aktor:** Developer
- **Prasyarat:** Proyek sudah dipilih/aktif; driver AI sudah dikonfigurasi.
- **Alur Utama:**
  1. Pengguna menulis prompt dan menekan Generate.
  2. Sistem mengirim request ke AI Adapter.
  3. AI mengembalikan rancangan skema (ERD + kode migrasi).
  4. Sistem menyimpan hasil sebagai draft (`status: draft`) di Supabase.
  5. Sistem menampilkan hasil dalam mode dry-run.
- **Alur Alternatif:** Jika AI gagal merespons → tampilkan pesan error (SRS-FR-013).

### UC-02: Meninjau dan Menyuntikkan Migrasi
- **Aktor:** Developer
- **Prasyarat:** Terdapat draft dengan `status: draft`.
- **Alur Utama:**
  1. Pengguna meninjau ERD dan kode migrasi.
  2. Pengguna (opsional) mengedit kode secara inline.
  3. Sistem memvalidasi tipe data PostgreSQL dan mendeteksi konflik nama file.
  4. Pengguna menekan tombol Inject.
  5. Sistem menulis file ke disk dan mengubah status draft menjadi `injected`.
- **Alur Alternatif:** Validasi gagal → tombol Inject nonaktif hingga kode diperbaiki.

### UC-03: Mengelola Multi-Proyek
- **Aktor:** Developer
- **Alur Utama:**
  1. Pengguna membuka aplikasi → melihat Project History Dashboard.
  2. Pengguna memilih proyek existing, atau menambah proyek baru via folder picker.
  3. Sistem menetapkan proyek terpilih sebagai konteks aktif untuk sesi kerja berikutnya.

---

## 9. Matriks Keterlusuran (Traceability Matrix) — Ringkasan

| Kebutuhan URD | Kebutuhan SRS Terkait |
|---|---|
| UR-INT-01, UR-INT-06, UR-INT-08 | SRS-FR-002 |
| UR-INT-02, UR-INT-08 | SRS-FR-003 |
| UR-INT-03 | SRS-FR-005 |
| UR-INT-04 | SRS-FR-006 |
| UR-INT-05 | SRS-FR-007 |
| UR-INT-07 | SRS-FR-001 |
| UR-INT-09 | SRS-FR-004 |
| UR-GEN-01, UR-GEN-02 | SRS-FR-008 |
| UR-GEN-03 | SRS-FR-009 |
| UR-GEN-04, UR-GEN-05 | SRS-FR-010 |
| UR-GEN-06, UR-GEN-08 | SRS-FR-013 |
| UR-GEN-07 | SRS-FR-014 |
| UR-GEN-09 | SRS-FR-011 |
| UR-GEN-10 | SRS-FR-012 |
| UR-GEN-11 | SRS-FR-015 |
| UR-VIS-01, UR-VIS-02, UR-VIS-11 | SRS-FR-016 |
| UR-VIS-03 | SRS-FR-017 |
| UR-VIS-04 | SRS-FR-018 |
| UR-VIS-05 | SRS-FR-022 |
| UR-VIS-06, UR-VIS-07 | SRS-FR-021 |
| UR-VIS-08 | SRS-FR-020 |
| UR-VIS-09 | SRS-FR-023 |
| UR-VIS-10 | SRS-FR-019 |

*Dokumen ini bersifat living document dan akan diperbarui seiring detail teknis lebih lanjut ditentukan bersama tim engineering (mis. pemilihan library ERD rendering, strategi caching offline, dan detail skema `check constraint` PostgreSQL).*
