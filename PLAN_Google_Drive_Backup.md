# 📋 Rencana Pengembangan: Integrasi Google Drive Backup & Sync
### DEVArchitect (Universal AI ERD & Database Schema Generator)

Dokumen ini adalah cetak biru teknis (*technical blueprint*) untuk mengimplementasikan fitur **Penyimpanan Cloud & Sinkronisasi Proyek menggunakan Google Drive API** pada aplikasi desktop DEVArchitect.

---

## 🎯 1. Tujuan & Manfaat

1. **Biaya Server Rp 0 (Nol Rupiah)**: Menggunakan kuota penyimpanan Google Drive masing-masing pengguna (15 GB gratis). Pengembang tidak perlu menyewa VPS atau cloud storage berbayar.
2. **Keamanan & Privasi 100%**: Data arsitektur sistem pengguna tersimpan di akun Google pribadi mereka, bukan di server pihak ketiga.
3. **Multi-Perangkat (Cross-Device Portability)**: Pengguna dapat menginstal DEVArchitect di laptop mana pun, lalu cukup klik *"Pulihkan dari Drive"* untuk melanjutkan pekerjaannya.
4. **Backup Otomatis & Riwayat Versi**: Mencegah kehilangan data jika laptop rusak atau terformat.

---

## 🏛️ 2. Arsitektur & Alur Autentikasi (Google OAuth 2.0 Desktop Flow)

Integrasi menggunakan alur **Google OAuth 2.0 for Desktop/Installed Applications (Loopback Flow)**:

```
[DEVArchitect Desktop]                                        [Google Server]
       │                                                             │
       ├─ 1. Buka Browser (Loopback / System Browser) ──────────────>│
       │    URL: https://accounts.google.com/o/oauth2/v2/auth        │
       │    Scope: drive.file (Hanya file buatan DEVArchitect)       │
       │                                                             │
       │<─ 2. Pengguna Klik 'Izinkan' & Redirect ke Port Lokal ──────┤
       │    http://127.0.0.1:port/oauth/callback?code=AUTH_CODE      │
       │                                                             │
       ├─ 3. Tukar AUTH_CODE dengan Token ──────────────────────────>│
       │    Mendapatkan: access_token & refresh_token                │
       │                                                             │
       └─ 4. Simpan Token Terenkripsi di SQLite Lokal (app_settings)─┘
```

### Lingkup Izin (*Scope*) yang Digunakan:
* `https://www.googleapis.com/auth/drive.file`
> **Catatan Keamanan Penting**: Scope ini **HANYA** memberi izin kepada DEVArchitect untuk membaca dan menulis file/folder yang dibuat oleh DEVArchitect itu sendiri. Aplikasi **TIDAK BISA** melihat foto, dokumen pribadi, atau file lain di Google Drive pengguna, sehingga aman dan terpercaya.

---

## 📂 3. Struktur Data Backup di Google Drive

Aplikasi secara otomatis membuat dan mengelola folder khusus di root Google Drive pengguna:

```
📁 Google Drive
└── 📁 DEVArchitect_Backups/
    ├── 📄 devarchitect-metadata.json             (Index seluruh proyek & waktu sync)
    ├── 📁 db_schemas/
    │   ├── ecommerce_v1_2026-09-05.devarch.json  (Skema, ERD Mermaid, Migrasi)
    │   └── pos_system_2026-09-05.devarch.json
    └── 📁 doc_architectures/
        ├── sistem_erp_brief_urd_prd.devarch.json (Dokumen, Chat History, Versi)
        └── exports/
            ├── sistem_erp_PRD_v1.0.pdf           (File ekspor PDF)
            └── sistem_erp_SRS_v1.0.docx
```

### Format File `.devarch.json` (Snapshot Mandiri)
Setiap file backup berisi data lengkap dalam format JSON terstruktur:
* **Versi Skema**: `schema_version: "1.0"`
* **Metadata**: Judul proyek, tanggal dibuat, framework target, dialek database.
* **Payload Skema DB**: Array seluruh file migrasi dan teks Mermaid ERD.
* **Payload Dokumen**: Seluruh dokumen URD/PRD/SRS, aturan konteks, dan riwayat obrolan AI.
* **Checksum**: Hash MD5/SHA256 untuk memastikan file tidak korup saat diunduh.

---

## 🛠️ 4. Komponen Teknis yang Akan Dibuat

### A. Backend (Laravel & NativePHP)
1. **Dependencies**:
   * Memasang SDK resmi Google: `composer require google/apiclient:^2.15`
2. **Service Layer**:
   * `app/Services/Cloud/GoogleDriveService.php`:
     * Menghandle koneksi OAuth 2.0 (generate Auth URL, tukar code ke token).
     * Otomatis melakukan *token refresh* jika `access_token` kedaluwarsa.
     * Operasi file: `ensureFolderExists()`, `uploadSnapshot()`, `downloadSnapshot()`, `listBackups()`, `deleteBackup()`.
   * `app/Services/Cloud/ProjectBackupSerializer.php`:
     * Mengemas Model `Project`, `Generation`, `DocProject`, `DocMessage`, `DocVersion` menjadi JSON snapshot.
     * Mengimpor dan memulihkan (*restore*) JSON snapshot kembali ke tabel database SQLite lokal.
3. **Controller & API Endpoints**:
   * `app/Http/Controllers/GoogleDriveController.php`:
     * `GET /api/cloud/gdrive/status` $\to$ Cek apakah sudah terhubung, nama akun Google, sisa kuota drive.
     * `GET /api/cloud/gdrive/auth-url` $\to$ Membuka browser untuk otorisasi Google.
     * `POST /api/cloud/gdrive/callback` $\to$ Menerima kode otorisasi dan menyimpan token.
     * `POST /api/cloud/gdrive/disconnect` $\to$ Menghapus token Google Drive dari SQLite.
     * `POST /api/cloud/gdrive/backup/project/{id}` $\to$ Cadangkan 1 proyek skema ke Drive.
     * `POST /api/cloud/gdrive/backup/docs/{id}` $\to$ Cadangkan 1 proyek dokumen ke Drive.
     * `POST /api/cloud/gdrive/backup/all` $\to$ Cadangkan seluruh workspace.
     * `GET /api/cloud/gdrive/backups` $\to$ Mengambil daftar file cadangan di Google Drive.
     * `POST /api/cloud/gdrive/restore` $\to$ Mengunduh dan memulihkan proyek terpilih.

### B. Frontend & Tampilan Antarmuka
1. **Menu Pengaturan Cloud (Settings Modal / Page)**:
   * Bagian baru: **"Penyimpanan Cloud (Google Drive)"**.
   * Jika belum terhubung: Kartu promo fitur backup + tombol *"Hubungkan Akun Google"*.
   * Jika sudah terhubung: Kartu profil Google (Foto profil, Nama, Email, kuota terpakai) + tombol *"Putuskan Koneksi"*.
2. **Aksi Cadangkan di Halaman Proyek**:
   * Tombol *"☁️ Cadangkan ke Drive"* di halaman ERD Viewer dan Dokumen Canvas.
   * Tooltip status terakhir: *"Terakhir dicadangkan: 5 Sep 2026, 18:30"*.
3. **Modal Pemulihan (Restore Modal) di Dashboard Utama**:
   * Tombol *"📥 Pulihkan dari Google Drive"*.
   * Membuka daftar file backup yang ditemukan di Google Drive pengguna beserta tombol *"Impor ke Lokal"*.

---

## 📅 5. Rencana Tahapan Eksekusi (Roadmap)

| Fase | Tugas | Status |
| :--- | :--- | :---: |
| **Fase 0** | **Kustomisasi Tampilan UI & Uji Coba Fitur Eksisting** *(Fokus Saat Ini)* | ⏳ *Sedang Berjalan* |
| **Fase 1** | Registrasi Project di Google Cloud Console (OAuth Client ID Desktop) | 📋 *Menunggu* |
| **Fase 2** | Install `google/apiclient` & Bangun `GoogleDriveService.php` | 📋 *Menunggu* |
| **Fase 3** | Bangun `ProjectBackupSerializer.php` (Export/Import JSON) | 📋 *Menunggu* |
| **Fase 4** | Integrasi UI Settings, Tombol Backup, & Modal Restore | 📋 *Menunggu* |
| **Fase 5** | Pengujian End-to-End (Upload, Token Refresh, Restore di PC lain) | 📋 *Menunggu* |

---

## 💡 6. Kebutuhan dari Sisi Pengembang Nanti (Saat Eksekusi)

Saat Anda siap mengeksekusi fitur ini nanti, Anda hanya perlu menyiapkan 2 nilai dari [Google Cloud Console](https://console.cloud.google.com/):
1. **`GOOGLE_DRIVE_CLIENT_ID`**
2. **`GOOGLE_DRIVE_CLIENT_SECRET`**

*(Panduan langkah demi langkah cara mendapatkan kredensial gratis tersebut akan dipandu secara rinci saat fase eksekusi dimulai).*
