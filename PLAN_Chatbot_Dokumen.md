# Plan Alur Kerja Baru — Chatbot Dokumen (URD → PRD → SRS → System Design)

> Status: ✅ Seluruh 20 pertanyaan §7 telah dijawab & disepakati — Siap dieksekusi.
> Keputusan terkunci: dashboard = ringkasan saja • chat = halaman khusus `/assistant` •
> chatbot dibangun dulu • model picker dari OpenRouter • lokal saja (tanpa push GitHub).
> Terakhir diperbarui: 2026-09-04

---

## 1. Visi alur end-to-end

```
Buka aplikasi → Dashboard ringkas → /assistant (chat + pilih model)
  → Brief → URD → PRD → SRS → System Design (approve per tahap)
  → Handoff ke Generator Skema (Fase 7) → ERD → Scaffold/Register Project
  → Export ERD ke Models (Fase 11)
```

Setiap tahap memakai pola Human-in-the-Loop yang sudah mapan di aplikasi ini:
draf AI → review/edit/regenerasi → approve → kunci versi → lanjut tahap berikut.

---

## 2. Arsitektur data (3 tabel baru)

### 2.1 `doc_projects` — satu baris per ide proyek
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | uuid PK | |
| `title` | string(255) | mis. "Penjualan Barang Bekas" |
| `description` | text nullable | brief awal user |
| `target_framework` | string nullable | enum TargetFramework, boleh diisi belakangan |
| `stage` | string(20), default `brief` | `brief → urd → prd → srs → sysdesign → done` |
| `ai_model` | string(100) nullable | model pilihan user per proyek |
| `status` | string(20), default `active` | `active / archived` |
| timestamps | | |

### 2.2 `doc_messages` — riwayat percakapan (untuk polling ala generasi)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | uuid PK | |
| `doc_project_id` | FK → doc_projects, cascade | |
| `role` | string(20) | `user / assistant / system` |
| `content` | text | isi pesan (Markdown untuk assistant) |
| `stage` | string(20) | tahap saat pesan dibuat |
| `ai_model` | string(100) nullable | model yang dipakai balasan ini |
| `job_status` | string(20), default `ready` | `queued / processing / ready / failed / cancelled` (pesan user langsung `ready`) |
| `job_error` | text nullable | |
| timestamps + index `(doc_project_id, created_at)` | | |

### 2.3 `doc_versions` — snapshot terkunci per tahap (immutable, pola dry-run)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | uuid PK | |
| `doc_project_id` | FK, cascade | |
| `doc_type` | string(20) | `urd / prd / srs / sysdesign` |
| `version` | integer | naik per tipe (v1, v2, …) |
| `content_markdown` | text | isi dokumen final tahap itu |
| `status` | string(20), default `draft` | `draft / approved / rejected` |
| `parent_version_id` | FK nullable → doc_versions | rantai revisi |
| `ai_model` | string(100) nullable | |
| timestamps | | |

Relasi: `doc_projects → hasMany(doc_messages, doc_versions)`; approve = `status approved`
+ `doc_projects.stage` maju satu tingkat.

---

## 3. Backend

### 3.1 Batch B1 — fondasi chat (dikerjakan pertama)
1. Migrasi 3 tabel di atas + model `DocProject`, `DocMessage`, `DocVersion`
   (fillable, casts, relasi, helper `advanceStage()`).
2. `DocChatJob` (queue database, `tries=1`, `timeout=300` — dokumen panjang):
   bangun konteks (brief + versi approved sebelumnya + N pesan terakhir) →
   panggil `AiManager::withDocsModelFallback` (Gemma `:free` default) →
   simpan balasan → `ready`; gagal → `failed` + `job_error`; hormati `cancelled`.
3. `DocChatController` + FormRequests:
   - `POST /api/docs/projects` (buat ide: title, description, framework opsional)
   - `GET /api/docs/projects` (daftar + stage)
   - `POST /api/docs/projects/{id}/messages` (202, dispatch job; validasi panjang pesan)
   - `GET /api/docs/messages/{id}/status` (polling ringan)
   - `POST /api/docs/messages/{id}/cancel`
   - `POST /api/docs/versions` (snapshot dari chat → draft, auto `version+1`)
   - `PUT /api/docs/versions/{id}` (edit manual), `POST approve`, `POST regenerate`
4. Throttle seperti generate (`30,1`; status tanpa throttle).
5. Tests: state machine maju/mundur, snapshot versioning + rantai parent,
   cancel mid-flight, fallback model habis → error jelas, 422 pesan kosong.

### 3.2 Batch B3 — 4 prompt builder dokumen (setelah UI hidup)
- `UrdPromptBuilder`, `PrdPromptBuilder`, `SrsPromptBuilder`, `SysDesignPromptBuilder`
  — struktur meniru `docs/URD|PRD|SRS` v2.0 (few-shot heading), Bahasa Indonesia,
  tabel risiko `| Risiko | Dampak | Mitigasi |` (maks 7, urut severitas),
  `Complexity Low/Med/High + alasan`, seksi Asumsi (maks 5), tanpa estimasi waktu.
- Prompt chaining: tiap builder menerima brief + semua versi approved sebelumnya.
- Handoff: `POST /api/docs/versions/{id}/to-schema` → prefill prompt Generator
  Skema + kolom `doc_version_id` nullable di `generations` (1 migrasi kecil).

---

## 4. UI (Batch B2)

1. **Sidebar**: item "Asisten Dokumen" (`/assistant`) + active-state pola existing.
2. **`/assistant`**: panel chat (bubble user/assistant/system, loading + cancel,
   chips tahap klik-able untuk navigasi ulang), panel samping (model picker §5,
   tombol *Simpan draf tahap ini*, *Regenerasi*, *Setujui & Lanjut*,
   riwayat versi per tahap + tombol *Buka ulang*).
3. **Dashboard**: hanya kartu ringkas — Total Dokumen, Total Project, Generasi
   terakhir, status antrean aktif. Tanpa chat tertanam.
4. Render Markdown aman (escape HTML, code block) — pola `textContent` seperti toast.

---

## 5. Model picker ( OpenRouter yang disediakan )

- Dropdown preset: `google/gemma-4-31b:free` (default, gratis), `qwen/qwen3.8-max`,
  `openai/gpt-oss-120b`, `deepseek/deepseek-v4-flash`, + input custom.
- Tombol refresh: `GET https://openrouter.ai/api/v1/models` (pakai API key user,
  cache 24 jam di `AppSetting:model_catalog_cache`, gagal → preset, offline aman).
- Pilihan tersimpan per `doc_projects.ai_model`; endpoint `GET /api/docs/models`.

---

## 6. TASKS (tambah Fase 10, Milestone Architecture & Model Expansion)

- [x] **TASK-1001**: migrasi + model `doc_projects/doc_messages/doc_versions` (selesai & termigrasi).
- [x] **TASK-1002**: `DocChatJob` + `DocGenerateJob` + controller + request + tests state machine (selesai & aktif).
- [x] **TASK-1003**: halaman `/assistant` + Canvas Panel (Preview/Editor/Diff/Config) + model picker + tests (selesai & aktif).
- [x] **TASK-1004**: dashboard ringkas (kartu total dokumen terintegrasi di atas dashboard).
- [x] **TASK-1005**: 4 prompt builder (URD/PRD/SRS/SysDesign) + handoff `toSchema` ke Generator Skema + tests (selesai & aktif).
- [x] **TASK-1006**: verifikasi end-to-end brief → sysdesign → ERD (selesai & terverifikasi).

---

## 7. Bank pertanyaan — jawab di sini sebelum eksekusi

> Cara menjawab: tulis jawaban di bawah tiap pertanyaan, lalu kabari saya.
> Rekomendasi saya tandai *(disarankan)*.

### Q1. Bahasa dokumen
Output URD/PRD/SRS/System Design berbahasa apa?
- [x] Indonesia saja (disarankan — selaras `docs/` existing)
- [ ] Inggris saja
- [ ] Ikuti bahasa prompt user (deteksi otomatis per proyek)

_Jawaban Q1:_ **Indonesia saja**. Selaras dengan seluruh dokumen acuan di `docs/` (`URD_...`, `PRD_...`, `SRS_...`) dan menjaga konsistensi istilah teknis arsitektur.

### Q2. Panjang respons per balasan chat
Dokumen penuh (URD bisa 5–15 ribu token) berisiko timeout & biaya.
- [x] Generate per-section (disarankan) — assistant mengeluarkan satu seksi per
      balasan (mis. "1. Pendahuluan" dulu), user ketik "lanjut" untuk seksi berikut
- [ ] Satu dokumen penuh per balasan (risiko timeout 300 dtk + biaya besar)
- [ ] Ringkas dulu, detail belakangan (draft 1 halaman → expand per seksi)

_Jawaban Q2:_ **Generate per-section**. Menjamin tidak terpotong batas token output OpenRouter, mencegah timeout 300 detik, dan memberikan kendali Human-in-the-Loop per seksi sebelum disetujui.

### Q3. Batas pesan per proyek / per hari (model gratis berkuota)
Endpoint `:free` dibatasi ±50–1000 request/hari.
- [x] Tanpa batas aplikasi, andalkan error provider + fallback (disarankan v1)
- [ ] Batas lunak: peringatan saat >30 pesan/hari per proyek
- [ ] Batas keras: blokir + arahkan ganti model berbayar

_Jawaban Q3:_ **Tanpa batas aplikasi, andalkan error provider + fallback**. Pengelolaan kuota ditangani via mekanisme fallback `AiManager` dan notifikasi ramah jika provider merespons 429.

### Q4. Edit manual dokumen
Selain chat, apakah user bisa mengedit teks dokumen langsung?
- [x] Ya, editor Markdown inline per versi (disarankan — pola dry-run)
- [ ] Tidak, hanya via instruksi chat ("ubah bagian X menjadi ...")

_Jawaban Q4:_ **Ya, editor Markdown inline per versi**. Sangat esensial agar pengguna dapat langsung mengoreksi detail kecil tanpa harus menghabiskan token AI untuk instruksi chat minor.

### Q5. Regenerasi
Tombol *Regenerasi* membuat apa?
- [x] Versi baru saudara (v2 berdampingan v1, bisa bandingkan) (disarankan)
- [ ] Timpa draf berjalan (v1 hilang)

_Jawaban Q5:_ **Versi baru saudara (v2 berdampingan v1, bisa bandingkan)**. Menjaga riwayat draf sebelumnya (`doc_versions.version + 1`) sehingga revisi lama tidak hilang jika hasil regenerasi baru kurang sesuai.

### Q6. Diff antar versi
Perlu tampilan perbandingan v1 vs v2?
- [x] Ya, diff baris sederhana (disarankan, murah: library JS kecil)
- [ ] Tidak, cukup daftar versi + buka ulang

_Jawaban Q6:_ **Ya, diff baris sederhana**. Memudahkan pengguna melihat poin-poin perubahan atau penambahan antar revisi dokumen secara visual.

### Q7. Approval convivencia
Bisakah tahap dilewati (mis. langsung SRS tanpa approve PRD)?
- [x] Tidak — approve berurutan wajib (disarankan, menjaga chaining)
- [ ] Ya, dengan peringatan konteks hilang

_Jawaban Q7:_ **Tidak — approve berurutan wajib**. URD mendasari PRD, PRD mendasari SRS, dan SRS mendasari System Design. Chaining berurutan wajib untuk mencegah halusinasi arsitektur.

### Q8. Multi-bahasa prompt campuran
Jika user campur Indonesia–Inggris dalam satu proyek?
- [x] Ikuti bahasa dominan brief awal, konsisten sampai done (disarankan)
- [ ] Ikuti bahasa tiap pesan (fleksibel, risiko dokumen campur aduk)

_Jawaban Q8:_ **Ikuti bahasa dominan brief awal, konsisten sampai done**. Dokumen formal tetap seragam dan profesional meskipun pengguna sesekali memberi instruksi chat berbahasa Inggris.

### Q9. Ekspor dokumen
Format unduhan dokumen approved?
- [x] Markdown `.md` saja v1 (disarankan — murah, sesuai format simpan)
- [ ] + PDF (butuh renderer tambahan, mis. DomPDF/wkhtmltopdf)
- [ ] + DOCX (butuh phpoffice/phpword)

_Jawaban Q9:_ **Markdown `.md` saja v1**. Cepat, ringan, selaras dengan penyimpanan basis data, dan kompatibel langsung dengan Obsidian/GitHub. PDF dapat ditambahkan pada iterasi berikutnya via print dialog browser.

### Q10. Keterkaitan dokumen ↔ proyek kode
Satu `doc_project` terhubung ke proyek kode bagaimana?
- [x] 1:1 dibuat saat handoff (disarankan — tombol "Buat proyek dari desain ini"
      → scaffold dengan nama dari judul dokumen)
- [ ] N:1 longgar (dokumen menempel ke proyek existing pilihan user)
- [ ] Tidak terhubung (handoff hanya copy-paste prompt)

_Jawaban Q10:_ **1:1 dibuat saat handoff**. Memuluskan alur dari ide dokumen ke generator skema (Fase 7) hingga pembuatan folder proyek fisik via scaffold runner.

### Q11. Retensi & arsip
Dokumen lama diapakan?
- [ ] Arsip manual per proyek (`status archived`, tetap bisa dibuka)
- [ ] Hapus permanen dengan konfirmasi
- [x] Keduanya (arsip + hapus) (disarankan)

_Jawaban Q11:_ **Keduanya (arsip + hapus)**. Pengguna dapat mengarsipkan proyek selesai agar dashboard tetap bersih, sekaligus memiliki opsi menghapus draf sampah secara permanen via modal konfirmasi.

### Q12. Notifikasi penyelesaian
Saat job dokumen selesai (bisa bermenit-menit), beri tahu via apa?
- [x] Notifikasi Windows saja seperti generate skema (disarankan — konsisten)
- [ ] + bunyi + badge di sidebar
- [ ] Tidak perlu (user menunggu di halaman)

_Jawaban Q12:_ **Notifikasi Windows saja seperti generate skema**. Memanfaatkan `DesktopNotificationService` yang sudah teruji dan tidak mengganggu alur kerja pengguna saat beralih window.

### Q13. Privasi prompt gratis
Endpoint `:free` dapat mencatat prompt untuk training (kebijakan provider).
- [x] Tampilkan peringatan sekali saat user memilih model `:free` (disarankan)
- [ ] Nonaktifkan model `:free` bila proyek ditandai sensitif (butuh flag per proyek)
- [ ] Abaikan (anggap user paham)

_Jawaban Q13:_ **Tampilkan peringatan sekali saat user memilih model `:free`**. Memberi transparansi privasi yang jelas tanpa mempersulit proses input pengguna.

### Q14. Token & biaya transparan
Tampilkan estimasi biaya per balasan?
- [x] Ya, footer kecil "±N token • ±$X" dari usage API (disarankan — mendidik)
- [ ] Tidak, hanya di log

_Jawaban Q14:_ **Ya, footer kecil "±N token • ±$X" dari usage API**. Membantu pengguna memantau konsumsi token dari OpenRouter secara transparan.

### Q15. Mode offline
Tanpa internet, halaman `/assistant` bagaimana?
- [x] Baca + edit draf tersimpan tetap bisa; kirim pesan diblokir ramah (disarankan)
- [ ] Halaman terkunci penuh dengan CTA cek koneksi

_Jawaban Q15:_ **Baca + edit draf tersimpan tetap bisa; kirim pesan diblokir ramah**. Pola aplikasi desktop yang benar: data lokal tetap bisa ditinjau dan diedit saat koneksi terputus.

### Q16. Kolom konteks proyek kode existing
Saat chat untuk proyek yang sudah punya kode, sertakan ringkasan skema existing?
- [x] Ya, lampirkan otomatis daftar tabel/file existing (disarankan — mencegah
      desain ganda; reuse `getExistingSchemaFiles`)
- [ ] Tidak, chat murni dari nol

_Jawaban Q16:_ **Ya, lampirkan otomatis daftar tabel/file existing**. Mencegah AI merancang ulang entitas/tabel yang sudah ada di database proyek.

### Q17. Template awal per tipe proyek
Sediakan template brief pemicu (toko online, absensi, inventaris)?
- [x] Ya, 3–5 contoh klik-isi (disarankan — mempercepat first-run)
- [ ] Tidak, mulai dari kanvas kosong

_Jawaban Q17:_ **Ya, 3–5 contoh klik-isi**. Sangat membantu *first-run experience* sehingga pengguna tidak kebingungan saat memulai ide dari kanvas kosong.

### Q18. Ganti model di tengah jalan
Boleh ganti model saat proyek berjalan?
- [x] Ya, per proyek kapan saja; versi mencatat model pemakai (disarankan —
      transparansi + eksperimen)
- [ ] Dikunci saat proyek dibuat

_Jawaban Q18:_ **Ya, per proyek kapan saja; versi mencatat model pemakai**. Pengguna dapat menghemat kuota dengan model gratis untuk URD/PRD, lalu beralih ke model bernalar tinggi untuk System Design.

### Q19. Prioritas eksekusi batch
Urutan pengerjaan?
- [x] B1 → B2 → B3 berurutan penuh (disarankan — fondasi dulu)
- [ ] B1 + B2 paralel (UI dengan data dummy), B3 terakhir

_Jawaban Q19:_ **B1 → B2 → B3 berurutan penuh**. Membangun fondasi data (B1) terlebih dahulu memastikan saat UI dibangun (B2), komponen langsung terhubung ke API nyata tanpa perlu mock data, dilanjutkan penyempurnaan prompt chaining (B3).

### Q20. Kriteria selesai (Definition of Done) tiap batch
- [x] Migrasi jalan + suite hijau + halaman render 200 + 1x uji manual tercatat (disarankan)
- [ ] Suite hijau saja cukup

_Jawaban Q20:_ **Migrasi jalan + suite hijau + halaman render 200 + 1x uji manual tercatat**. Standar komprehensif untuk memastikan stabilitas kode dan tampilan aplikasi desktop.

### Catatan tambahan (opsional)

- Pada Batch B1, tambahkan relasi `doc_version_id` nullable di tabel `generations` agar dokumen System Design dapat diteruskan langsung ke input prompt AI Schema Generator secara mulus.
- Desain UI `/assistant` mengikuti palet gelap modern (Tailwind zinc/emerald) yang konsisten dengan tema Supabase di Dashboard.