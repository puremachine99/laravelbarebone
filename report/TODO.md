# TODO — Mas Nopal · Modul J: Nakama AI Center (MVP Chatbot Gemini)

> Sisi **Laravel**: akun, hak akses, tampilan, riwayat, batas & log pemakaian, upload S3.
> Sisi Gemini / Layanan AI dikerjakan **Mas Faizal**. Titik temu: kontrak JSON `AiGateway` (lihat `PLAN-ai-center-mvp.md` bagian 4).
> Jadwal ±9 minggu. Tanda 🤝 = dikerjakan bersama Mas Faizal. Tanda ⛔ = menunggu keputusan pimpinan.
> Tanda ⚠️ = sudah dikerjakan tapi **berbeda dari rencana** (lihat catatan di item). Status per 9 Okt 2026.
> **Update:** AI sungguhan sekarang lewat **Nakama LLM API** (`nakama-ai.smartid.or.id`, driver `nakama`), bukan `GeminiGateway` langsung. Chat teks + hapus percakapan sudah jalan di lokal.

---

## Minggu 0 — Kickoff (2–3 hari)

- [ ] 🤝 Sepakati kontrak JSON request/response/stream + kode error dengan Mas Faizal
  - Kontrak sudah ditulis di kode (`AiChatRequest`, `AiStreamEvent`, enum `AiErrorCode`) mengikuti plan bagian 4, tapi **belum dikonfirmasi ke Faizal**
- [x] ⚠️ 🤝 Putuskan: Layanan AI di dalam Laravel (Laravel AI SDK / `GeminiGateway`) atau layanan terpisah
  - Dipakai **layanan terpisah: Nakama LLM API** (`NakamaAiGateway`, `POST /v1/chat` SSE). Kontraknya milik Nakama, dipetakan ke `AiStreamEvent` / `AiErrorCode`. Perlu konfirmasi ke Faizal bahwa ini jalur final
- [ ] 🤝 Putuskan: PDF dibaca via temporary URL S3 atau isi file dikirim Laravel (untuk Fase 2)
- [x] Eksplorasi repo SmartID ERP (tanpa ubah kode), catat:
  - [x] Versi Laravel & PHP, ada/tidaknya Laravel AI SDK → Laravel 13, PHP 8.4, **tidak ada** `laravel/ai`
  - [x] Stack frontend (Blade/Livewire/Inertia) & Vite → Livewire 4 + Volt, Alpine, Tailwind 4 + DaisyUI + MaryUI, Vite
  - [x] Lokasi placeholder `/ai-center` (route, controller, view) & menu sidebar Nakama Hub → `App\Livewire\Ai\AiCenter`, route `ai-center`, sidebar grup Nakama Hub
  - [x] Sistem role/permission yang dipakai → spatie/laravel-permission; izin `manage-ai-center` sudah ada
  - [x] Konfigurasi S3 (disk yang sudah ada) → disk `s3`, `temporaryUrl()` sudah dipakai di Chat & Investor
  - [x] Pola modul lain (Nakama Chat): naming tabel, UUID/auto-increment, soft delete → bigint auto-increment, soft delete
  - [x] Test framework (Pest/PHPUnit), queue, Redis → Pest 4; queue & cache driver `database`; Redis tidak wajib
  - [ ] Web server produksi (nginx/php-fpm) — penting untuk SSE streaming → tidak terlihat dari repo, perlu konfirmasi infra
- [ ] Laporkan hasil eksplorasi ke Mas Faizal & pemilik produk (bahan: `how.md` bagian 14)
- [ ] ⚠️ Tanyakan teks halaman "Upcoming Feature" boleh diubah sesuai MVP (pertanyaan terbuka #7) → sudah diubah mengikuti default di plan, **belum ditanyakan**

---

## Minggu 1 — Fondasi (M1)

- [x] Buat `config/ai_center.php` + key di `.env.example` (`GEMINI_MODEL`, `AI_CENTER_DRIVER`, `AI_SERVICE_URL`, batas harian, batas panjang pesan, history window)
- [ ] Migration + model + factory:
  - [x] `ai_conversations`
  - [x] `ai_messages` (⚠️ FK bernama `ai_conversation_id`; ada tambahan kolom `error_code`; `attachment_id` menunggu Fase 2)
  - [ ] `ai_usage_logs`
- [ ] ⚠️ Permission `ai-center.use` dan `ai-center.admin`
  - `ai-center.use` → memakai izin yang sudah ada `manage-ai-center`
  - `ai-center.admin` → **belum ada** (tambahkan sebagai resource permission di `config/resource_permissions.php`)
- [x] Route group `/ai-center` dengan middleware auth + permission
- [x] Menu sidebar hanya tampil bagi yang punya izin AI Center
- [x] ⚠️ `ConversationPolicy`: hanya pemilik yang bisa lihat/ubah/hapus → diganti query terbatas pemilik (`$user->aiConversations()`, orang lain → 404), karena `Gate::before` meloloskan Super Admin di semua policy
- [ ] ⚠️ Terima `AiGateway` + `FakeAiGateway` dari Mas Faizal (target hari 1–2), pasang binding di service provider
  - Interface, DTO, dan `FakeAiGateway` **dibuat sendiri** di `app/Services/AiCenter/`; perlu dicocokkan dengan versi Faizal
  - Binding mendukung driver `fake` dan `nakama`; `gemini` langsung dan `http` belum (tidak dipakai)
  - ✅ `NakamaAiGateway`: stream SSE, mapping error, cancel saat klien putus, hapus thread remote (`forget`)
- [x] Test:
  - [x] Tamu → redirect login
  - [x] User tanpa permission → 403
  - [x] User A tidak bisa akses percakapan user B (termasuk Super Admin)

---

## Minggu 2 — Endpoint chat & mulai UI (M3 + awal M4)

- [x] ⚠️ Route & controller percakapan: dikerjakan sebagai aksi Livewire (`AiCenter`), bukan route REST seperti di plan
  - [x] Daftar, buat, lihat, ganti judul, hapus percakapan
  - [x] Feedback suka / tidak suka
- [ ] Endpoint kirim pesan dengan streaming SSE (`POST /ai-center/messages`, `StreamMessageController`):
  - [x] Validasi login, permission, policy, panjang pesan (`input_too_long`)
  - [x] Cek batas harian; bila habis → event `rate_limited`, **Gemini tidak dipanggil**
  - [x] Simpan pesan user + pesan assistant berstatus `streaming`
  - [x] Teruskan stream dari `AiGateway` ke browser (header SSE, flush tiap chunk)
  - [ ] Blok `finally`: simpan isi akhir + status ✅, tulis `ai_usage_logs` ❌ (100% tercatat, termasuk saat error)
  - [x] Tangani klien putus (`cancelled`) — kode ada, belum ada test khusus
  - [x] Judul otomatis dari pesan pertama
- [ ] Test feature memakai `FakeAiGateway`: stream sukses ✅, error tersimpan rapi ✅, rate limit tidak memanggil gateway ✅, **log selalu tertulis ❌**
- [x] Mulai UI chat: layout dua kolom (sidebar riwayat + area chat)

---

## Minggu 3 — UI selesai & integrasi (M4 + M5) 🤝

- [x] UI chat:
  - [x] Sidebar riwayat: baru, daftar, ganti judul, hapus (dengan konfirmasi)
  - [x] Kotak input: Enter kirim, Shift+Enter baris baru, counter karakter, tombol stop
  - [x] Render jawaban mengalir + Markdown yang **disanitasi** (cegah XSS) — ada test
  - [x] Indikator "AI sedang mengetik", pesan error ramah per kode error, tombol coba lagi
  - [x] **Peringatan tetap** di kotak chat (jawaban perlu dicek, jangan masukkan data di luar aturan) ⛔ teks final menunggu keputusan pimpinan
  - [x] Label "Jawaban AI, perlu dicek" di tiap balasan
  - [x] Tombol salin + suka/tidak suka (P1)
  - [x] Pilihan cepat: Ringkas teks, Rapikan surat/laporan, Buat draf email, Jelaskan istilah (P1)
  - [x] Cek tampilan langsung di browser (lokal, dengan Nakama — berjalan)
- [x] Ganti teks placeholder "Upcoming Feature"; hapus contoh "Update proyek Pemkab Malang jadi 50 juta…"
- [ ] Batas & log pemakaian (M5):
  - [x] Batas pesan per pengguna per hari (zona waktu Asia/Jakarta) — dihitung dari `ai_messages` (⚠️ plan: dari `ai_usage_logs`)
  - [x] Tampilkan sisa kuota di UI (mis. "12/50 pesan hari ini")
  - [ ] Hitung `est_cost_usd` dari token × tarif
  - [ ] (Opsional) batas anggaran bulanan
- [ ] 🤝 Integrasi: ganti Fake → AI asli di staging
  - [x] Lokal: Fake → Nakama LLM API (Gemini) berjalan; kolom `ai_conversations.remote_conversation_id` ditambah
  - [ ] Staging/produksi: set `AI_CENTER_DRIVER=nakama` + `NAKAMA_AI_API_KEY`, jalankan migration
- [ ] 🤝 Cek nginx: `proxy_buffering off`, `X-Accel-Buffering: no` (header sudah dikirim oleh `eventStream`), timeout PHP-FPM ≥ 120 dtk
- [ ] 🤝 Ukur waktu sampai token pertama (target < 3 detik)

---

## Minggu 4 — Hardening & Gerbang 1 (M6)

- [ ] Halaman ringkasan pemakaian Super Admin `/ai-center/admin/usage`:
  - [ ] Pengguna aktif, jumlah pesan, token, perkiraan biaya (harian/bulanan)
  - [ ] Rasio suka vs tidak suka, rata-rata latensi token pertama, error per kode
  - [ ] Filter tanggal; hanya angka per pengguna, **bukan isi chat**
- [ ] Checklist keamanan:
  - [x] API key tidak muncul di response, view, JS, atau log — key hanya di header server-ke-server; ada test (body request & pesan error tidak memuat key)
  - [ ] ⚠️ Satu API key dipakai semua pegawai dan jadi satu-satunya "pemilik" percakapan di Nakama → key wajib hanya di `.env`; tanyakan ke tim Nakama soal key per aplikasi/lingkungan
  - [x] Semua tool Nakama (web search/fetch, memory, gambar, dokumen) dimatikan di request; hanya `user_id` samaran `smartid-{id}` yang dikirim (tanpa nama/email)
  - [ ] CSRF di semua POST/PATCH/DELETE ✅ (route di grup `web`, `fetch` mengirim token) + throttle per menit ❌ (`AI_PER_MINUTE_LIMIT` sudah di `.env.example`, belum dipakai)
  - [x] Output Markdown disanitasi
  - [ ] Test arsitektur: modul AI tidak query tabel ERP lain
- [ ] Simulasi Gemini mati / kuota habis → menu ERP lain tetap normal (jalur error sudah teruji dengan Fake; simulasi nyata menunggu Gemini)
- [ ] Semua test otomatis hijau (saat ini 39 test AI Center hijau, termasuk 9 test `NakamaAiGateway`; belum lengkap)
- [ ] **Gerbang 1:** 30 pertanyaan lolos (dari Mas Faizal), 0 data bocor, disetujui pimpinan

---

## Minggu 5–6 — Pilot Fase 1

- [ ] ⛔ Pastikan sudah terjawab: siapa pengguna pilot, anggaran bulanan, batas harian, data yang dilarang
- [ ] Deploy ke produksi: env, migration, seed permission
- [ ] Buka bertahap: Super Admin dulu, lalu pengguna pilot (lewat role/permission)
- [ ] Pantau tiap minggu: pemakaian, rasio suka (target ≥ 70%), biaya, error
- [ ] Perbaiki masukan dari pilot
- [ ] (P2, bila ada waktu) `ai_settings` + `ai_setting_audits` + form admin untuk model & system prompt (M7)

---

## Minggu 7–8 — Fase 2: Dokumen PDF (M8)

- [ ] Migration `ai_attachments`
- [ ] ⚠️ Disk S3 privat, path per user: `ai-center/{id_user}-{2 kata nama}/{Y-m-d_His}_{nama-file}.pdf` (menggantikan `ai-center/{user_id}/{uuid}.pdf`; env `AI_PDF_*` sudah disiapkan, detail di `how.md` bagian 8)
- [ ] Dropzone di area chat (drag & drop + tombol) + indikator progres
- [ ] Validasi server: hanya PDF (cek `%PDF`), ≤ 10 MB, tolak file rusak/terenkripsi (`file_invalid`)
- [ ] Kirim file ke Layanan AI (temporary URL 5 menit atau isi file, sesuai keputusan)
- [ ] PDF yang sama bisa ditanya berulang dalam satu percakapan
- [ ] Hapus percakapan ikut menghapus file di S3
- [ ] Test: validasi file, akses privat, penghapusan file

---

## Minggu 9 — Uji PDF & rilis 🤝

- [ ] 🤝 Uji 10 PDF contoh
- [ ] Uji tampilan, batas pemakaian, dan riwayat untuk alur PDF
- [ ] Rilis Fase 2 ke pengguna pilot
- [ ] Review data pilot untuk **Gerbang 2** (≥ 70% jawaban dinilai membantu)

---

## Aturan yang tidak boleh dilanggar

- [x] AI **tidak** membaca/menulis tabel ERP selain tabel `ai_*` (belum dikunci dengan test arsitektur)
- [x] **Tidak ada** function/tool calling ke Gemini (semua tool Nakama dimatikan; `ai-center-testcase.md` TC-02…TC-05 — gambar, dokumen, analisis file — **belum diimplementasikan**, menunggu keputusan)
- [x] API key hanya di server (`NAKAMA_AI_API_KEY` di `.env`; teruji tidak bocor ke body request/pesan error)
- [x] Riwayat chat privat per pengguna (ada test, termasuk Super Admin)
- [ ] Gemini gagal ≠ ERP lain error (jalur error teruji dengan Fake; simulasi Gemini nyata belum)

## Di luar cakupan (jangan dikerjakan)

- Perintah yang mengubah data ERP, AI membaca database ERP, konfirmasi aksi (Fase 3–4)
- Menggantikan Nakama Chat
- Model AI selain Gemini

## Tergantung orang lain

| Yang ditunggu | Dari | Dibutuhkan sebelum |
|---|---|---|
| `AiGateway` + `FakeAiGateway` | Mas Faizal | Minggu 1 (hari 2) — ⚠️ sudah dibuat sendiri, perlu dicocokkan |
| ~~`GeminiGateway`~~ → Nakama LLM API (sudah tersedia) + konfirmasi batas laju per key & system prompt | Mas Faizal | Minggu 3 (integrasi) |
| 30 pertanyaan uji lolos | Mas Faizal | Gerbang 1 (minggu 4) |
| Teks peringatan, data terlarang, batas harian, anggaran, daftar pilot | Pimpinan | Pilot (minggu 5) |
| Pengolahan PDF ke Gemini | Mas Faizal | Minggu 8 |

## Langkah berikutnya (urutan usulan)

1. ~~Migration lokal + cek tampilan + sambung AI asli~~ ✅ (Nakama LLM API)
2. `ai_usage_logs` + pencatatan di blok `finally` + izin `ai-center.admin` (token sudah tersedia dari event `done`)
3. Estimasi biaya + throttle per menit (`AI_PER_MINUTE_LIMIT`) + baca `Retry-After` dari respons 429 Nakama
4. Halaman pemakaian Super Admin (M6)
5. Tanyakan ke tim Nakama: batas laju per key, key per lingkungan, dan retensi riwayat di server mereka
6. Putuskan TC-02…TC-05 (gambar, dokumen, unggah file): aktifkan tool Nakama atau tetap chat teks saja
