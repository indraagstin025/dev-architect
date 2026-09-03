# User Requirement Document (URD)
## DEVArchitect: Aplikasi Desktop Generator ERD & Skema Database Multi-Framework Berbasis AI

| Informasi Dokumen | Keterangan |
|---|---|
| Nama Produk | **DEVArchitect** (Universal AI ERD & Multi-Framework Schema Generator) |
| Platform | Desktop (Windows/macOS) berbasis NativePHP |
| Versi Dokumen | 2.0 |
| Status | Disetujui (Approved) |

### Riwayat Perubahan Dokumen

| Versi | Perubahan |
|---|---|
| 1.0 | Draf awal URD untuk generator migrasi Laravel |
| 1.1 | Target database diubah ke Supabase (PostgreSQL) |
| 1.2 | Multi-proyek & Project History Dashboard, mode dry-run wajib via Supabase/PostgreSQL |
| 1.3 | Dukungan multi-database dialect (MySQL, PostgreSQL, SQLite, SQL Server) |
| 2.0 | **Evolusi Universal Multi-Framework & Multi-ORM**: Memperluas cakupan dari khusus Laravel menjadi platform arsitek database universal yang mendukung **Laravel (Eloquent)**, **Express.js / Node.js (Prisma & Drizzle)**, **Java Spring Boot (Hibernate/JPA)**, dan **Raw SQL DDL**. Penyempurnaan deteksi proyek cerdas dan injeksi file multi-ekosistem. |

---

## 1. Pendahuluan

### 1.1 Tujuan Dokumen
Dokumen ini mendefinisikan kebutuhan pengguna (*user requirements*) untuk aplikasi desktop **DEVArchitect**. Aplikasi ini membantu pengembang perangkat lunak (*software engineers*) dari berbagai ekosistem backend merancang struktur database secara visual (ERD) dan menghasilkan skema/kode migrasi secara otomatis menggunakan kecerdasan buatan (AI).

### 1.2 Ruang Lingkup
Aplikasi berjalan sebagai aplikasi desktop native menggunakan engine **NativePHP**, dengan tiga pilar utama:
1. **Desktop Shell & Project Manager**: Pengelolaan multi-proyek lintas framework (Laravel, Express/Node, Spring Boot, Standalone) dengan native dialog dan notifikasi sistem operasi.
2. **AI Playground & Universal Prompt Engine**: Interaksi natural language dengan LLM (via OpenRouter - model GPT OSS 120B, OpenAI, Claude, maupun Ollama lokal) untuk menghasilkan skema tabel dan diagram ERD terstruktur.
3. **Visualizer ERD & Multi-ORM Code Manager**: Kanvas diagram relasi interaktif (Mermaid dengan Pan/Zoom/Export) dan generator kode ORM presisi yang dapat disuntikkan langsung ke folder proyek pengguna.

### 1.3 Target Framework & ORM yang Didukung

| Ekosistem Backend | ORM / Format Target | Output File yang Dihasilkan | Jalur Injeksi Proyek Default |
|---|---|---|---|
| **Laravel** | Eloquent Migrations | File PHP Migrasi (`.php`) | `database/migrations/` |
| **Express.js / Node.js** | **Prisma ORM** | Prisma Schema (`schema.prisma`) | `prisma/schema.prisma` |
| **Express.js / Node.js** | **Drizzle ORM** | TypeScript Schema (`schema.ts`) | `src/db/schema.ts` atau `db/schema.ts` |
| **Java / Spring Boot** | **Hibernate / JPA** | Java Entity Classes (`.java`) | `src/main/java/.../model/*.java` |
| **Universal / Standalone** | **Raw SQL DDL** | Berkas SQL Script (`schema.sql`) | Root folder proyek / Custom folder |

### 1.4 Target DBMS / Dialek Database
1. **PostgreSQL** (Supabase, Neon, AWS RDS, lokal)
2. **MySQL / MariaDB**
3. **SQLite**
4. **SQL Server (MSSQL)**

### 1.5 Target Pengguna
- **Fullstack / Backend Developer** yang bekerja di berbagai stack teknologi (Laravel, Node.js/Express, Java Spring Boot).
- **Software Architect** yang membutuhkan alat visualisasi ERD instan dan ekspor skema multi-bahasa.
- **Freelancer / Agensi** yang menangani beragam proyek klien dengan standar teknologi berbeda.

---

## 2. Deskripsi Umum Sistem

DEVArchitect memungkinkan pengguna mendeskripsikan kebutuhan aplikasi dalam bahasa natural. AI kemudian menghasilkan rancangan struktur entitas yang divisualisasikan dalam bentuk diagram ERD interaktif (Mermaid.js). Pengguna dapat memilih target **Framework/ORM** dan target **Database Dialect** yang diinginkan.

Seluruh proses mengikuti prinsip **Dry-Run (Pratinjau Aman)**:
1. Hasil rancangan AI disimpan terlebih dahulu sebagai **draft berstatus `Draft`** di database aplikasi (PostgreSQL).
2. Pengguna dapat menggeser kanvas ERD, meninjau tab kode, dan melakukan penyesuaian/edit manual inline.
3. **Tidak ada file fisik yang ditulis ke disk lokal** sebelum pengguna secara eksplisit menekan tombol **"Inject Schema to Project"**.
4. Setelah tombol injeksi ditekan, file fisik ditulis ke struktur folder proyek pengguna sesuai konvensi framework masing-masing, dan status draft di database diperbarui menjadi `Injected`.

---

## 3. Kebutuhan Pengguna (User Requirements)

### 3.1 Modul 1 — Integrasi Desktop & Project Manager (NativePHP)

| ID | Prioritas | Kebutuhan Pengguna |
|---|---|---|
| UR-INT-01 | Must Have | Sebagai pengguna, saya ingin memilih folder proyek melalui dialog folder native Windows/macOS, agar saya tidak perlu mengetik path secara manual. |
| UR-INT-02 | Must Have | Sistem harus secara cerdas mendeteksi jenis framework proyek dari file penandanya: <br>• Laravel: mendeteksi `artisan` & `composer.json`<br>• Express/Node: mendeteksi `package.json`<br>• Spring Boot: mendeteksi `pom.xml` atau `build.gradle`<br>• Standalone: folder umum tanpa signature khusus. |
| UR-INT-03 | Must Have | Sebagai pengguna, saya ingin lokasi folder tiap proyek tersimpan permanen di database lokal/cloud, agar saya dapat beralih antar proyek tanpa mencari folder dari nol. |
| UR-INT-04 | Must Have | Sebagai pengguna, saya ingin melihat **Project History Dashboard** saat aplikasi dibuka yang merangkum daftar seluruh proyek yang pernah didaftarkan beserta tipe framework-nya. |
| UR-INT-05 | Should Have | Sebagai pengguna, saya ingin menerima notifikasi native OS (Windows Notification) ketika AI selesai memproses skema atau saat file berhasil diinjeksi ke folder lokal. |
| UR-INT-06 | Should Have | Sebagai pengguna, saya ingin memunculkan jendela aplikasi dari mana saja menggunakan shortcut keyboard global (`Ctrl+Alt+A`). |
| UR-INT-07 | Should Have | Sebagai pengguna, saya ingin aplikasi dapat diminimalkan ke System Tray agar tidak memenuhi taskbar. |
| UR-INT-08 | Should Have | Sebagai pengguna, saya ingin dapat mengganti nama atau menghapus proyek dari daftar dashboard. |

### 3.2 Modul 2 — Generator & AI Playground

| ID | Prioritas | Kebutuhan Pengguna |
|---|---|---|
| UR-GEN-01 | Must Have | Sebagai pengguna, saya ingin menuliskan deskripsi kebutuhan database dalam kolom prompt yang luas dan nyaman. |
| UR-GEN-02 | Must Have | Sebagai pengguna, saya ingin memilih target **Framework / ORM** dari dropdown: <br>• Laravel (Eloquent)<br>• Express.js (Prisma)<br>• Express.js (Drizzle)<br>• Spring Boot (Hibernate/JPA)<br>• Universal (Raw SQL). |
| UR-GEN-03 | Must Have | Sebagai pengguna, saya ingin memilih target **Dialek Database** dari dropdown: <br>• PostgreSQL (Supabase)<br>• MySQL / MariaDB<br>• SQLite<br>• SQL Server. |
| UR-GEN-04 | Must Have | Sistem harus menggunakan driver **OpenRouter** (dengan model default seperti `openai/gpt-oss-120b` atau model OSS pilihan) serta mendukung provider lain (OpenAI, Claude, dan Ollama lokal). |
| UR-GEN-05 | Must Have | Sebagai pengguna, saya ingin memasukkan dan menyimpan API Key OpenRouter secara aman dan terenkripsi di pengaturan aplikasi. |
| UR-GEN-06 | Must Have | Sistem harus mengarahkan AI menggunakan prompt builder khusus agar sintaks kode ORM dan tipe data kolom presisi sesuai pasangan Framework dan DBMS yang dipilih. |
| UR-GEN-07 | Must Have | Proses generate AI dijalankan secara asynchronous dengan indikator animasi loading yang tidak membuat tampilan freeze. |
| UR-GEN-08 | Should Have | Pengguna dapat membatalkan (*cancel*) proses generate AI yang sedang berjalan. |
| UR-GEN-09 | Must Have | Setiap hasil generate otomatis disimpan sebagai **draft berstatus `Draft`** di database relasional, terhubung dengan `project_id` aktif. |
| UR-GEN-10 | Should Have | Menampilkan pesan error yang ramah dan jelas jika API Key salah, saldo OpenRouter tidak mencukupi, atau koneksi terputus. |

### 3.3 Modul 3 — Visualizer ERD & Manajemen Kode (Mode Dry-Run)

| ID | Prioritas | Kebutuhan Pengguna |
|---|---|---|
| UR-VIS-01 | Must Have | Sebagai pengguna, saya ingin melihat hasil rancangan AI dalam bentuk diagram ERD interaktif (Mermaid) yang menampilkan entitas, kolom, primary key, dan relasi. |
| UR-VIS-02 | Must Have | Sebagai pengguna, saya ingin dapat menggeser (*pan*) dan memperbesar/memperkecil (*zoom in/out*) kanvas ERD dengan kontrol mouse/tombol. |
| UR-VIS-03 | Should Have | Sebagai pengguna, saya ingin mengekspor diagram ERD ke format gambar (**PNG** atau **SVG**) untuk dokumentasi proyek atau `README.md`. |
| UR-VIS-04 | Must Have | Sebagai pengguna, saya ingin melihat pratinjau file kode skema dalam tab terpisah (file `.php` untuk Laravel, `schema.prisma` untuk Prisma, `schema.ts` untuk Drizzle, file `.java` untuk Hibernate, atau `.sql` untuk Raw SQL). |
| UR-VIS-05 | Must Have | Sebagai pengguna, saya ingin dapat mengedit langsung kode skema di dalam aplikasi (*inline editor*) sebelum file disimpan ke komputer lokal. |
| UR-VIS-06 | Must Have | Sistem harus memastikan seluruh tampilan ERD dan kode skema berstatus **Dry-Run**, dan tidak ada file yang ditulis ke disk sebelum tombol injeksi ditekan. |
| UR-VIS-07 | Must Have | Sebagai pengguna, saya ingin menekan tombol **"Inject Schema to Project"** untuk menuliskan seluruh file ke lokasi yang sesuai standar framework proyek aktif. |
| UR-VIS-08 | Must Have | Proses injeksi file harus bersifat **atomik** — jika terjadi kegagalan saat menulis salah satu file, seluruh file yang sempat tertulis harus di-rollback untuk mencegah kerusakan direktori proyek. |
| UR-VIS-09 | Should Have | Sistem harus mendeteksi dan memperingatkan pengguna jika file skema berpotensi menimpa file yang sudah ada di proyek target. |
| UR-VIS-10 | Must Have | Setelah injeksi sukses, status record draft di database otomatis diperbarui menjadi `Injected`. |
| UR-VIS-11 | Should Have | Sebagai pengguna, saya ingin melihat riwayat generasi sebelumnya per proyek, agar saya dapat membandingkan atau melanjutkan rancangan terdahulu. |

---

## 4. Kebutuhan Non-Fungsional

| ID | Kategori | Deskripsi |
|---|---|---|
| NFR-01 | Performa | Waktu respons pemanggilan AI (OpenRouter) diharapkan < 15 detik untuk skema menengah (5–10 tabel). Kanvas ERD harus responsif saat pan dan zoom. |
| NFR-02 | Kompatibilitas | Berjalan optimal pada sistem operasi Windows 10/11 dan macOS versi terbaru. |
| NFR-03 | Keamanan | API Key OpenRouter disimpan dalam bentuk terenkripsi lokal dan tidak pernah dibagikan ke pihak ketiga. |
| NFR-04 | Keandalan Data | Perubahan status `Draft` menjadi `Injected` harus konsisten antara database metadata dan disk fisik komputer pengguna. |
| NFR-05 | Modularitas Arsitektur | Penambahan framework baru (mis. Go/GORM, Python/SQLAlchemy) dapat dilakukan dengan menambahkan strategi prompt dan injector tanpa merombak arsitektur utama. |

---

## 5. Asumsi dan Batasan

**Asumsi:**
- Pengguna memiliki koneksi internet aktif untuk pemanggilan API OpenRouter.
- Pengguna memiliki hak akses tulis (*write permission*) pada direktori folder proyek yang dipilih di komputernya.

**Batasan Versi 1.0:**
- Aplikasi hanya merancang skema database, relasi, dan model entity — belum mencakup pembuatan Business Logic / Controller CRUD secara otomatis.
- Eksekusi migrasi database ke server aktif (seperti menjalankan `php artisan migrate`, `npx prisma migrate`, dll.) tetap dilakukan manual oleh pengguna melalui terminal masing-masing.

---

## 6. Kriteria Penerimaan (Acceptance Criteria Ringkas)

- Pengguna dapat memilih proyek dari berbagai framework (Laravel, Express/Prisma, Express/Drizzle, Spring Boot, Standalone).
- Pengguna dapat mengetikkan deskripsi database dan memilih target framework serta dialek database.
- AI (OpenRouter) sukses menghasilkan ERD interaktif dan file kode yang sesuai sintaks ORM target.
- Pengguna dapat meninjau dan mengedit kode secara dry-run tanpa menyentuh disk lokal.
- Saat tombol Inject ditekan, file fisik tertulis rapi di folder yang benar dan status berubah menjadi `Injected`.
