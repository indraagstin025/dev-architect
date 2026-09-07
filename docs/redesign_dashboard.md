# DEVArchitect Dashboard UI/UX Redesign

## Implementation Prompt

> **Scope:** Dashboard saja. Jangan melakukan redesign pada halaman
> lain.

------------------------------------------------------------------------

## 1. Peran

Anda bertindak sebagai:

-   Senior Frontend Engineer
-   Senior UI/UX Designer
-   Product Designer

Saya sedang mengembangkan aplikasi desktop bernama **DEVArchitect**.

Gunakan screenshot Dashboard yang saya lampirkan sebagai **baseline
visual**.

Tujuan utama implementasi bukan sekadar membuat UI lebih cantik, tetapi
memperbaiki **usability, information architecture, terminology, dan
visual hierarchy**.

------------------------------------------------------------------------

## 2. Konteks DEVArchitect

DEVArchitect adalah aplikasi desktop untuk membantu pengguna:

-   mengelola project development
-   membuka project/repository
-   melihat framework dan database
-   mengakses dokumentasi project
-   menggunakan AI Assistant untuk membantu merancang aplikasi

Target pengguna:

1.  Programmer pemula.
2.  Orang awam/non-programmer.
3.  Programmer berpengalaman.

### Masalah utama

Dashboard saat ini terlihat cukup profesional, tetapi pengguna pemula
dan orang awam masih kesulitan memahami:

-   halaman ini sebenarnya untuk apa
-   apa yang harus dilakukan setelah membuka aplikasi
-   apa arti "Project Aktif"
-   apa perbedaan "Buka", "Pilih Aktif", dan "Buka Folder"
-   apa fungsi Asisten Dokumen AI
-   apa hubungan Project, Repository, Framework, dan Database

Masalah utama adalah:

**Information Architecture + User Mental Model + Visual Hierarchy +
Terminology**

------------------------------------------------------------------------

# 3. Tujuan Redesign

Ketika pengguna pertama kali membuka Dashboard, mereka harus dapat
memahami tanpa membaca dokumentasi:

1.  Saya sedang berada di Dashboard.
2.  Ini adalah project-project saya.
3.  Ini project yang sedang saya kerjakan.
4.  Saya bisa membuka project dari sini.
5.  Saya bisa membuat project baru.
6.  Saya bisa menggunakan Asisten Dokumen untuk membantu membuat
    project.
7.  Saya tidak perlu memahami istilah teknis untuk mulai menggunakan
    aplikasi.

Gunakan mental model:

``` text
PROJECT SAYA
     ↓
PILIH PROJECT
     ↓
BUKA PROJECT
     ↓
KERJAKAN PROJECT
```

Jangan membuat pengguna memahami konsep internal aplikasi terlebih
dahulu.

------------------------------------------------------------------------

# 4. Prinsip UX Utama

Gunakan prinsip:

> **User should understand WHAT this is, WHERE they are, and WHAT they
> can do next.**

Setiap elemen Dashboard harus memiliki alasan keberadaan yang jelas.

Tanyakan:

> "Apakah informasi ini benar-benar dibutuhkan pengguna pada saat ini?"

Jika informasi tidak penting untuk keputusan utama pengguna:

-   kecilkan
-   jadikan secondary information
-   pindahkan ke detail
-   atau sembunyikan

Jangan membuat semua informasi memiliki visual importance yang sama.

------------------------------------------------------------------------

# 5. Prioritas Informasi

## Primary

Yang paling terlihat:

-   Project yang sedang dikerjakan
-   Buka Project
-   Tambah Project

## Secondary

-   Project Saya
-   Search
-   Filter
-   Daftar project

## Tertiary

-   Framework
-   Database
-   File path
-   Last opened
-   Technical metadata

------------------------------------------------------------------------

# 6. Struktur Dashboard yang Disarankan

Evaluasi struktur existing dan gunakan struktur terbaik berdasarkan UX.

Struktur konseptual yang disarankan:

``` text
Dashboard

Kelola project dan dokumentasi aplikasi Anda.

[ + Tambah Project ]

────────────────────────────────────

Project yang Sedang Dikerjakan

test_2
Laravel · SQLite

● Sedang Dikerjakan

[ Buka Project ]

────────────────────────────────────

Project Saya

[ Cari project... ] [ Filter ] [ Sort ]

Project cards

────────────────────────────────────

Asisten Dokumen

Bantu merancang project dan dokumentasi aplikasi dengan AI.

[ Buka Asisten ]
```

Struktur ini adalah referensi, bukan aturan mutlak. Gunakan judgment
UI/UX jika ada struktur yang lebih baik.

------------------------------------------------------------------------

# 7. Project yang Sedang Dikerjakan

Bagian ini harus menjadi salah satu fokus utama Dashboard.

Jangan membuat card terlalu kompleks.

Contoh hierarchy:

``` text
Project yang Sedang Dikerjakan

test_2

Laravel · SQLite

● Sedang Dikerjakan

C:\Users\Indra\Documents\DEVArchitect-Projects\test_2

[ Buka Project ]
```

Action teknis seperti:

-   Buka Folder
-   Buka di VS Code

boleh tetap tersedia, tetapi harus menjadi **secondary action**.

Jangan menjadikan VS Code atau file path sebagai fokus utama.

------------------------------------------------------------------------

# 8. Evaluasi Istilah "Project Aktif"

Istilah:

-   "Project Aktif"
-   "Pilih Aktif"

berpotensi membingungkan pengguna baru.

Evaluasi dan gunakan istilah yang lebih natural, misalnya:

-   "Sedang Dikerjakan"
-   "Sedang Dibuka"
-   "Project Saat Ini"

Pilih istilah yang paling mudah dipahami berdasarkan konteks aplikasi.

### Aturan penting

Pengguna tidak boleh dipaksa memahami konsep state internal hanya untuk
membuka project.

Pengguna seharusnya cukup:

``` text
[Buka Project]
```

------------------------------------------------------------------------

# 9. Project List

Pertimbangkan mengganti:

``` text
Projects
```

menjadi:

``` text
Project Saya
```

atau wording Indonesia lain yang lebih natural.

Tujuan utamanya adalah agar pengguna langsung memahami:

> "Ini daftar project saya."

## Project Card

Hierarchy:

1.  Nama project
2.  Framework
3.  Database
4.  Status
5.  Primary action

Contoh:

``` text
test_2

Laravel · SQLite

● Sedang Dikerjakan

[ Buka Project ]
```

Technical path dan metadata harus menjadi informasi sekunder.

------------------------------------------------------------------------

# 10. Asisten Dokumen AI

Evaluasi posisi dan tampilannya.

Pengguna pemula harus langsung memahami:

> "Fitur ini membantu saya melakukan apa?"

Jangan hanya menampilkan:

``` text
Asisten Dokumen AI
```

Berikan penjelasan berdasarkan manfaat.

Contoh:

``` text
Asisten Dokumen

Bantu merancang project dan dokumentasi aplikasi dengan AI.

[ Buka Asisten ]
```

Jangan membuat bagian ini terlalu besar sehingga mengalahkan project
utama.

------------------------------------------------------------------------

# 11. Sidebar

Pertahankan karakter sidebar yang minimal.

Namun icon saja tidak boleh menjadi sumber kebingungan.

Jika icon tidak cukup jelas, gunakan:

-   tooltip
-   label ketika sidebar expanded
-   atau icon + label

Jangan mengorbankan usability hanya demi minimalisme.

------------------------------------------------------------------------

# 12. Visual Design

Pertahankan identitas DEVArchitect:

-   minimal
-   clean
-   modern
-   professional
-   developer-oriented
-   beginner-friendly

## Color Direction

Gunakan pendekatan sekitar **60/30/10**:

-   60% neutral/background
-   30% secondary surface/text/border
-   10% accent

Gunakan:

-   white/off-white background
-   black/dark gray primary text
-   subtle gray borders
-   subtle shadows
-   green sebagai accent utama

### Hindari

-   gradient berlebihan
-   glassmorphism
-   terlalu banyak warna
-   terlalu banyak card
-   shadow berat
-   border radius berlebihan
-   dekorasi yang tidak memiliki fungsi

------------------------------------------------------------------------

# 13. Typography

Gunakan satu font utama secara konsisten.

Prioritas:

**Geist** atau **Inter**

Gunakan:

**JetBrains Mono**

hanya untuk:

-   file path
-   code
-   technical identifier
-   informasi teknis tertentu

Jangan menggunakan monospace untuk seluruh Dashboard.

## Typography hierarchy

Buat hierarchy yang jelas:

``` text
Page Title
Section Title
Card Title
Body
Secondary
Metadata
```

Hindari:

-   terlalu banyak ALL CAPS
-   letter spacing berlebihan
-   font terlalu kecil
-   terlalu banyak variasi font weight

------------------------------------------------------------------------

# 14. Button Hierarchy

Setiap area harus memiliki primary action yang jelas.

## Primary

-   `+ Tambah Project`
-   `Buka Project`

## Secondary

-   `Buka Folder`
-   `Buka di VS Code`
-   `Filter`
-   `Sort`

## Tertiary

-   More / menu

Jangan membuat semua tombol terlihat memiliki tingkat kepentingan yang
sama.

------------------------------------------------------------------------

# 15. Layout

Anda tidak harus mempertahankan layout saat ini.

Evaluasi apakah struktur seperti berikut lebih efektif:

``` text
Header
↓
Project yang Sedang Dikerjakan
↓
Project Saya
↓
Project Cards
↓
Asisten Dokumen
```

atau struktur lain yang lebih baik.

Gunakan UX reasoning untuk menentukan hasil akhir.

Dashboard harus memiliki whitespace yang cukup dan tidak terasa padat.

------------------------------------------------------------------------

# 16. Design System

Pastikan Dashboard menggunakan aturan visual yang konsisten untuk:

-   typography
-   spacing
-   colors
-   buttons
-   badges
-   cards
-   inputs
-   dropdowns
-   status indicators
-   tooltips
-   section headers

Jika project sudah memiliki design tokens atau reusable components:

**Gunakan kembali.**

Jangan membuat style baru yang bertentangan dengan design system
existing.

Jika diperlukan, buat atau rapikan reusable component yang memang
relevan dengan Dashboard.

------------------------------------------------------------------------

# 17. Preserve Existing Functionality

**Jangan mengubah:**

-   backend
-   database
-   API
-   authentication
-   routing
-   business logic

**Jangan menghapus functionality yang sudah bekerja.**

Fokus hanya pada:

**Dashboard UI/UX**

Jika perlu mengubah component structure, lakukan hanya untuk mendukung
UI yang lebih baik dan maintainable.

------------------------------------------------------------------------

# 18. Sebelum Coding

Sebelum mengubah kode:

1.  Cari file/component Dashboard.
2.  Periksa component yang digunakan Dashboard.
3.  Periksa existing design system.
4.  Periksa Tailwind/CSS.
5.  Periksa reusable components.
6.  Periksa state dan event handler.
7.  Identifikasi API/state yang digunakan Dashboard.
8.  Jangan mengubah logic yang tidak berkaitan dengan UI.

Kemudian berikan analisis singkat:

### A. Masalah UI saat ini

Apa yang membuat UI kurang jelas?

### B. Masalah UX saat ini

Apa yang membuat pemula bingung?

### C. Masalah terminology

Istilah apa yang perlu disederhanakan?

### D. Masalah hierarchy

Informasi apa yang terlalu dominan?

### E. Rencana perubahan

Apa yang akan diubah?

### F. Yang dipertahankan

Apa yang tidak perlu diubah?

------------------------------------------------------------------------

# 19. Implementasi

Setelah analisis, implementasikan redesign Dashboard.

Prioritas:

1.  Perbaiki information hierarchy.
2.  Perjelas primary action.
3.  Sederhanakan terminology.
4.  Kurangi cognitive load.
5.  Rapikan project card.
6.  Perjelas current project.
7.  Rapikan Asisten Dokumen.
8.  Konsistenkan typography.
9.  Konsistenkan spacing.
10. Konsistenkan button/status/badge.
11. Baru lakukan visual polish.

**Jangan membalik urutan ini.**

Jangan memulai dari warna dan shadow sebelum hierarchy selesai.

------------------------------------------------------------------------

# 20. Desktop Optimization

DEVArchitect adalah aplikasi desktop.

Optimalkan minimal untuk:

-   1280 × 720
-   1366 × 768
-   1440 × 900
-   1920 × 1080

Jangan membuat content area terlalu lebar.

Gunakan max-width yang masuk akal.

Pastikan layout tetap nyaman pada ukuran desktop yang lebih kecil.

------------------------------------------------------------------------

# 21. Visual Quality Checklist

Setelah implementasi, periksa:

### UX

-   Apakah primary action langsung terlihat?
-   Apakah pengguna baru tahu harus melakukan apa?
-   Apakah current project mudah ditemukan?
-   Apakah istilah mudah dipahami?
-   Apakah informasi teknis terlalu dominan?
-   Apakah user harus memahami konsep internal aplikasi?

### Visual

-   Apakah typography konsisten?
-   Apakah spacing konsisten?
-   Apakah card terlalu banyak?
-   Apakah hierarchy terlihat jelas?
-   Apakah green accent digunakan secara konsisten?
-   Apakah button hierarchy jelas?
-   Apakah UI terlalu padat?
-   Apakah UI terlihat seperti dashboard template generik?

### Functionality

Pastikan:

-   Tambah Project tetap berfungsi.
-   Buka Project tetap berfungsi.
-   Search tetap berfungsi.
-   Filter tetap berfungsi.
-   Sorting tetap berfungsi.
-   Project selection tetap berfungsi.
-   Buka Folder tetap berfungsi.
-   VS Code action tetap berfungsi.
-   Asisten Dokumen tetap dapat dibuka.

------------------------------------------------------------------------

# 22. Validation

Setelah selesai:

1.  Jalankan project.
2.  Jalankan build.
3.  Jalankan test yang relevan.
4.  Periksa console error.
5.  Periksa broken interaction.
6.  Periksa visual consistency.
7.  Periksa accessibility dasar.
8.  Periksa layout pada resolusi desktop yang disebutkan.

Jangan berhenti setelah perubahan visual berhasil compile.

------------------------------------------------------------------------

# 23. Final Report

Setelah implementasi selesai, berikan laporan singkat:

## Files Changed

Daftar file yang diubah.

## UX Changes

Perubahan UX yang dilakukan.

## Visual Changes

Perubahan visual yang dilakukan.

## Functionality Preserved

Fitur existing yang tetap dipertahankan.

## Potential Follow-up

Masalah Dashboard yang masih dapat ditingkatkan di tahap berikutnya.

------------------------------------------------------------------------

# 24. Important Product Design Principle

DEVArchitect harus dirancang berdasarkan:

> **User's mental model, bukan developer's internal data model.**

Jangan menampilkan konsep teknis jika konsep tersebut tidak membantu
pengguna mengambil keputusan.

Jika konsep teknis diperlukan, jelaskan dengan bahasa sederhana.

Setiap screen harus menjawab:

1.  Saya di mana?
2.  Ini apa?
3.  Saya bisa melakukan apa?
4.  Apa yang sebaiknya saya lakukan berikutnya?
5.  Apa yang terjadi setelah saya melakukan action?

Untuk Dashboard, mental model utamanya:

``` text
Saya punya project
        ↓
Saya memilih project
        ↓
Saya membuka project
        ↓
Saya mengerjakan project
```

------------------------------------------------------------------------

# 25. Scope Control

**FOKUS HANYA PADA DASHBOARD.**

Jangan melakukan redesign pada:

-   Assistant
-   Project Detail
-   Architecture
-   Database
-   Settings
-   Authentication
-   halaman lainnya

Jangan melakukan refactor besar-besaran yang tidak diperlukan untuk
Dashboard.

Kita akan mengerjakan halaman lain secara bertahap setelah Dashboard
selesai.

------------------------------------------------------------------------

# Final Goal

Hasil akhir harus membuat pengguna berpikir:

> **"DEVArchitect adalah aplikasi yang membantu saya mengelola
> project."**

Bukan:

> **"DEVArchitect adalah dashboard repository/database yang harus saya
> pahami terlebih dahulu."**

Dashboard harus terasa:

**simple enough for beginners, useful for non-technical users, and
professional enough for developers.**
