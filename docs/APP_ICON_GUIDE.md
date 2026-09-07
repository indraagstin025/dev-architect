# Panduan Icon Aplikasi DEVArchitect (NativePHP / Electron)

> Status: ⏳ Menunggu file icon dari desainer. Struktur sudah disiapkan — cukup taruh file jadi ke folder `public/`.

## Darimana ketentuan ini

`vendor/nativephp/desktop/src/Drivers/Electron/Traits/InstallsAppIcon.php`
(`installIcon()`) menyalin file berikut dari `public/` ke build Electron.
Tanpa file ini, installer memakai icon default Electron.

## File yang harus disediakan (di `public/`)

| File | Platform | Spesifikasi |
|------|----------|-------------|
| `icon.png` | Linux + fallback umum | PNG, minimal 512×512 (disarankan 1024×1024) |
| `icon.ico` | Windows (installer + taskbar) | ICO multi-resolusi: 16, 24, 32, 48, 64, 128, 256 px |
| `icon.icns` | macOS (dock + finder) | ICNS lengkap 16→1024 px termasuk varian `@2x` |
| `IconTemplate.png` | Tray macOS (mode terang) | PNG monokrom, ~18×18 px, transparan |
| `IconTemplate@2x.png` | Tray macOS retina | PNG monokrom, ~36×36 px, transparan |

Catatan:
- `public/favicon.ico` yang ada saat ini hanya untuk browser, **tidak** dipakai installer desktop.
- Siapkan 1 master `icon-master-1024.png` (1024×1024, background solid/sesuai brand), lalu generate turunannya.

## Cara generate (1 perintah, disarankan)

```powershell
npm i -D electron-icon-builder
npx electron-icon-builder --input=./icon-master-1024.png --output=./public --flatten
```

Lalu rename hasil ke nama tabel di atas dan buat manual `IconTemplate*.png`
(sederhanakan logo jadi siluet satu warna).

## Branding pendamping (wajib sebelum `native:build`)

Di `config/nativephp.php` masih nilai default — isi sebelum build:

| Key | Nilai sekarang | Ganti dengan |
|-----|---------------|--------------|
| `app_id` | `com.nativephp.app` | ID unik, mis. `com.devarchitect.app` |
| `version` | `1.0.0` | Versi rilis, mis. `2.0.0` |
| `author` / `copyright` | kosong | Nama + copyright Anda |
| `description` / `website` | generik | Deskripsi + URL DEVArchitect |

## Verifikasi

```powershell
php artisan native:install   # menyalin icon ke proyek Electron
php artisan native:build     # installer memakai icon baru
```

Checklist: installer Windows menampilkan icon di Add/Remove Programs + taskbar,
tray memakai menu + tooltip `DEVArchitect - Universal AI Database Architect`,
macOS (jika dibuild) menampilkan `.icns` di Dock.
