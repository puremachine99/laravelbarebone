# PLAN — Modul J: Nakama AI Center (MVP Chatbot Gemini)

> Plan implementasi untuk Claude Code. Sumber: *PRD — Modul J: Nakama AI Center (MVP Chatbot Gemini)*, Draft 2, 9 Okt 2026.
> Aplikasi: SmartID ERP v0.28.1 (nakama.smartid.or.id), Laravel.
> Cakupan plan ini: **Fase 1 (Chatbot)** dan **Fase 2 (Dokumen PDF)**. Fase 3–4 (baca ERP, agentic) **tidak** dikerjakan.

---

## 0. Cara memakai plan ini (instruksi untuk Claude Code)

1. **Jangan langsung menulis kode.** Kerjakan Langkah 0 (eksplorasi repo) dulu dan laporkan temuannya.
2. Ikuti konvensi yang **sudah ada** di repo (struktur folder, stack frontend Blade/Livewire/Inertia, sistem role/permission, style migration, test framework). Plan ini menyebut nama file sebagai *usulan*; sesuaikan dengan pola repo.
3. Kerjakan per milestone (M1 → M8). Tiap milestone diakhiri dengan test hijau dan commit terpisah.
4. Bila menemukan hal yang bertentangan dengan plan ini atau menyentuh **Keputusan Terbuka** (bagian 9), berhenti dan tanyakan — jangan menebak.
5. **Aturan keras (tidak boleh dilanggar):**
   - AI **tidak** boleh membaca/menulis tabel ERP selain tabel modul AI Center sendiri.
   - **Tidak ada** tool/function calling ke Gemini di MVP.
   - API key Gemini **hanya di server** (`.env`), tidak pernah dikirim ke browser atau di-log.
   - Riwayat chat **privat per pengguna**; tidak ada endpoint yang membuka chat milik orang lain.
   - Kegagalan Gemini tidak boleh membuat halaman/menu ERP lain error.

---

## 1. Ringkasan teknis

```
Browser (kotak chat)
   │  POST pesan / GET stream (SSE)
   ▼
Laravel ERP ── cek login + hak akses + batas harian ── simpan riwayat + log pemakaian
   │  kontrak JSON (AiGateway)
   ▼
Layanan AI (GeminiGateway) ── susun system prompt + riwayat ── Gemini API (streaming)
```

- **Laravel (Mas Nopal):** auth, menu, UI chat, riwayat, batas pemakaian, log, dashboard pemakaian, upload S3.
- **Layanan AI (Mas Faizal):** system prompt, pemotongan riwayat, panggilan Gemini, streaming, pengolahan PDF, mapping error.
- **Titik temu:** satu interface `AiGateway` + kontrak JSON (bagian 4). Implementasi awal dibuat **di dalam Laravel** (`GeminiGateway`) supaya bisa diganti ke layanan terpisah (`HttpAiServiceGateway`) tanpa mengubah sisi Laravel lain.

---

## 2. Langkah 0 — Eksplorasi repo (wajib, tanpa mengubah kode)

Cari dan laporkan:

- [ ] Versi Laravel & PHP (`composer.json`), apakah ada `laravel/ai` / AI SDK dan apakah mendukung Gemini.
- [ ] Stack frontend: Blade + Alpine? Livewire? Inertia (Vue/React)? Build tool (Vite)?
- [ ] Lokasi halaman placeholder `/ai-center` (route, controller, view) dan entri sidebar grup **Nakama Hub** (dekat Nakama Chat).
- [ ] Sistem role/permission (spatie/laravel-permission? kolom role? Gate/Policy?) dan bagaimana menu dibatasi untuk Super Admin saat ini.
- [ ] Konfigurasi filesystem S3 (`config/filesystems.php`, disk yang dipakai modul lain).
- [ ] Pola modul lain (mis. Nakama Chat): struktur folder, naming tabel, penggunaan UUID vs auto-increment, soft delete.
- [ ] Test framework (Pest/PHPUnit), factory, dan cara test dijalankan.
- [ ] Apakah ada queue worker / Redis (untuk rate limiter & job).
- [ ] Web server di produksi (nginx/php-fpm/Octane) — penting untuk SSE (buffering).

**Output:** ringkasan temuan + penyesuaian plan bila perlu. Tunggu konfirmasi sebelum M1.

---

## 3. Skema data (usulan)

Semua tabel berprefix `ai_` agar terpisah jelas dari tabel ERP.

### `ai_conversations`
| kolom | tipe | catatan |
|---|---|---|
| id | uuid / bigint | ikuti pola repo |
| user_id | FK users | pemilik, index |
| title | string(150) nullable | default dari pesan pertama, bisa di-rename |
| last_message_at | timestamp nullable | untuk sort sidebar |
| timestamps, deleted_at | | soft delete; hard delete via job terjadwal (lihat Keputusan Terbuka) |

### `ai_messages`
| kolom | tipe | catatan |
|---|---|---|
| id | | |
| conversation_id | FK | index, cascade |
| role | enum: `user`, `assistant` | |
| content | longText | |
| status | enum: `complete`, `streaming`, `error`, `cancelled` | |
| feedback | tinyint nullable | 1 = suka, -1 = tidak suka (FR-1.6) |
| attachment_id | FK nullable → `ai_attachments` | Fase 2 |
| timestamps | | |

### `ai_usage_logs` (FR-3.1 — 100% pemakaian tercatat)
| kolom | tipe | catatan |
|---|---|---|
| id | | |
| user_id | FK | index |
| conversation_id | FK nullable | |
| request_id | uuid | korelasi dengan Layanan AI |
| model | string | |
| input_chars | int | ukuran pesan |
| prompt_tokens, output_tokens, total_tokens | int nullable | dari `usageMetadata` Gemini |
| est_cost_usd | decimal(10,6) nullable | dihitung dari tarif di config |
| status | enum: `success`, `error`, `rate_limited`, `rejected` | |
| error_code | string nullable | |
| latency_first_token_ms, latency_total_ms | int nullable | metrik "< 3 detik" |
| created_at | | index (user_id, created_at) |

> Log **tidak** menyimpan isi pesan (isi sudah di `ai_messages`), cukup ukuran & metadata.

### `ai_settings` (FR-3.4, P2 — boleh dibuat di M7)
key/value: `model`, `system_prompt`, `daily_message_limit`, `max_input_chars`, `history_window`, `monthly_budget_usd`. Tiap perubahan dicatat di `ai_setting_audits` (user_id, key, old_value, new_value, created_at) — sesuai aturan "perubahan instruksi dasar dicatat siapa dan kapan".

### `ai_attachments` (Fase 2)
id, user_id, conversation_id, disk, path, original_name, size_bytes, mime, page_count nullable, status (`uploaded`, `processing`, `ready`, `failed`), timestamps.

---

## 4. Kontrak JSON `AiGateway` (disepakati Faizal ↔ Nopal)

Interface PHP:

```php
interface AiGateway
{
    /** @return iterable<AiStreamEvent> */
    public function stream(AiChatRequest $request): iterable;
}
```

### Request (Laravel → Layanan AI)
```json
{
  "request_id": "uuid",
  "user": { "id": 123, "name": "..." },
  "conversation_id": "uuid",
  "message": "Ringkas surat penawaran ini jadi 5 poin: ...",
  "history": [
    { "role": "user", "content": "..." },
    { "role": "assistant", "content": "..." }
  ],
  "attachment": null,
  "options": { "quick_action": "summarize" }
}
```
Fase 2: `"attachment": { "url": "<S3 temporary URL, 5 menit>", "mime": "application/pdf", "name": "nota.pdf", "size_bytes": 123456 }`.

### Response (stream event, satu per baris / SSE event)
```json
{ "type": "delta", "text": "potongan jawaban" }
{ "type": "done",  "usage": { "prompt_tokens": 512, "output_tokens": 230, "total_tokens": 742 }, "model": "gemini-x", "finish_reason": "STOP" }
{ "type": "error", "code": "upstream_timeout", "message": "Layanan AI sedang lambat, coba lagi." }
```

### Kode error baku
| code | arti | pesan ke pengguna |
|---|---|---|
| `rate_limited` | batas harian pengguna tercapai (dicek Laravel, Gemini tidak dipanggil) | "Batas pemakaian harian Anda sudah tercapai. Coba lagi besok." |
| `input_too_long` | melebihi `max_input_chars` | "Pesan terlalu panjang (maks N karakter)." |
| `upstream_quota` | kuota/429 Gemini | "Kuota layanan AI sedang habis. Coba beberapa saat lagi." |
| `upstream_timeout` | timeout | "Layanan AI sedang lambat, coba lagi." |
| `upstream_blocked` | diblok safety filter | "Permintaan ini tidak dapat diproses oleh AI." |
| `upstream_error` | error lain | "Terjadi gangguan pada layanan AI." |
| `file_invalid` | PDF rusak/tidak valid (Fase 2) | "File tidak dapat dibaca. Pastikan file PDF tidak rusak." |

Timeout: koneksi 5 dtk, first token 15 dtk, total 120 dtk (configurable).

---

## 5. Milestone

### M1 — Fondasi & akses
- [ ] Config `config/ai_center.php` membaca `.env`: `GEMINI_API_KEY`, `GEMINI_MODEL`, `AI_CENTER_DRIVER` (`gemini`|`http`), `AI_SERVICE_URL`, `AI_DAILY_MESSAGE_LIMIT` (usulan 50), `AI_MAX_INPUT_CHARS` (usulan 8000), `AI_HISTORY_WINDOW` (usulan 20 pesan), tarif per 1M token.
- [ ] Tambahkan key ke `.env.example` (tanpa nilai rahasia).
- [ ] Migration tabel `ai_conversations`, `ai_messages`, `ai_usage_logs` + model + factory.
- [ ] Permission `ai-center.use` dan `ai-center.admin`. Super Admin dapat keduanya; pengguna pilot dapat `ai-center.use` (mekanisme penunjukan pilot mengikuti sistem role repo).
- [ ] Middleware/Gate pada route group `/ai-center`. Menu sidebar hanya tampil bila punya `ai-center.use`.
- [ ] `ConversationPolicy`: semua aksi hanya untuk `user_id == auth()->id()`.
- [ ] Test: tamu → redirect login; user tanpa permission → 403; user A tidak bisa melihat/menghapus percakapan user B (404/403).

### M2 — Layanan AI (`GeminiGateway`) — zona Mas Faizal
- [ ] `app/Services/AiCenter/AiGateway.php` (interface), DTO `AiChatRequest`, `AiStreamEvent`.
- [ ] `GeminiGateway`: panggil `POST https://generativelanguage.googleapis.com/v1beta/models/{model}:streamGenerateContent?alt=sse` dengan header `x-goog-api-key`. Gunakan `systemInstruction`, `contents` (role `user`/`model`), parse SSE, map ke event `delta`/`done`/`error`, ambil `usageMetadata`.
  - Bila repo sudah memakai Laravel AI SDK yang mendukung Gemini + streaming, boleh dipakai di balik interface yang sama (konfirmasi dulu, Keputusan Terbuka #2).
- [ ] `PromptBuilder`: system prompt default (FR-1.3) dari `resources/ai-center/system_prompt.md` atau `ai_settings`:
  - asisten internal SmartID, sopan, ringkas, menjawab dalam bahasa pengguna (utamanya Indonesia);
  - mengaku bila tidak yakin, tidak mengarang angka/fakta;
  - menyatakan tidak punya akses ke data ERP dan tidak bisa mengubah data;
  - tidak berisi rahasia apa pun (aman bila bocor via prompt injection).
- [ ] Pemotongan riwayat: kirim N pesan terakhir (`history_window`) + batas total karakter.
- [ ] Mapping error HTTP Gemini (429 → `upstream_quota`, 5xx/timeout → `upstream_timeout`/`upstream_error`, blockReason → `upstream_blocked`).
- [ ] `FakeAiGateway` untuk test & dev lokal (stream teks dummy, bisa disetel untuk melempar error tertentu).
- [ ] `HttpAiServiceGateway` (stub): meneruskan kontrak JSON ke `AI_SERVICE_URL` — untuk opsi layanan terpisah.
- [ ] Binding di service provider berdasarkan `AI_CENTER_DRIVER`.
- [ ] Test unit: parsing SSE, mapping error, pemotongan riwayat, API key tidak pernah muncul di log/exception message.

### M3 — Endpoint chat + streaming
Route (usulan, di bawah middleware auth + permission):

| method | path | fungsi |
|---|---|---|
| GET | `/ai-center` | halaman chat |
| GET | `/ai-center/conversations` | daftar percakapan milik sendiri |
| POST | `/ai-center/conversations` | buat percakapan |
| GET | `/ai-center/conversations/{id}` | pesan dalam percakapan |
| PATCH | `/ai-center/conversations/{id}` | ganti judul |
| DELETE | `/ai-center/conversations/{id}` | hapus |
| POST | `/ai-center/conversations/{id}/messages` | kirim pesan → respons **SSE** stream |
| POST | `/ai-center/messages/{id}/feedback` | suka / tidak suka |

Alur `POST .../messages` (sesuai diagram alur PRD):
1. Validasi: login, permission, policy, `message` wajib & ≤ `max_input_chars` (→ `input_too_long`).
2. Cek batas harian via `RateLimiter`/hitung `ai_usage_logs` hari ini. Bila habis → kirim event `error: rate_limited`, catat log `rate_limited`, **Gemini tidak dipanggil**.
3. Simpan pesan user; buat pesan assistant `status=streaming`.
4. Panggil `AiGateway::stream()`; teruskan tiap `delta` ke browser (`response()->stream()` / `StreamedResponse`, header `Content-Type: text/event-stream`, `Cache-Control: no-cache`, `X-Accel-Buffering: no`, flush tiap chunk).
5. Saat `done`/`error`/klien putus: simpan isi akhir & status, tulis `ai_usage_logs` (di blok `finally` agar 100% tercatat), update `last_message_at`; set judul otomatis dari pesan pertama (potong ~60 karakter) bila kosong.
- [ ] Tangani `connection_aborted()` → status `cancelled`, tetap catat log.
- [ ] Test feature (pakai `FakeAiGateway`): stream sukses, error upstream tersimpan rapi, rate limit tidak memanggil gateway, log selalu tertulis, input terlalu panjang ditolak.

### M4 — UI chat (zona Mas Nopal)
Ganti isi placeholder "Upcoming Feature" dengan UI chat, mengikuti stack & komponen UI repo.
- [ ] Layout dua kolom: sidebar riwayat (baru, daftar, rename, hapus dengan konfirmasi) + area chat.
- [ ] Kotak input (Enter kirim, Shift+Enter baris baru), counter karakter, tombol stop.
- [ ] Render jawaban mengalir (EventSource/`fetch` + ReadableStream), Markdown → HTML yang **disanitasi** (cegah XSS).
- [ ] Indikator "AI sedang mengetik", pesan error yang ramah (tabel kode error bagian 4), tombol coba lagi.
- [ ] **Peringatan tetap** di kotak chat (FR-1.5): *"Jawaban AI bisa keliru — selalu cek sebelum dipakai. Jangan memasukkan data rahasia (misal nominal anggaran, data pegawai, data Pemda) di luar aturan penggunaan."* (teks final menunggu Keputusan Terbuka #4).
- [ ] Label kecil "Jawaban AI, perlu dicek" pada tiap balasan.
- [ ] Tombol salin jawaban + suka/tidak suka (FR-1.6).
- [ ] Pilihan cepat (FR-1.7): Ringkas teks, Rapikan surat/laporan, Buat draf email, Jelaskan istilah → mengisi template prompt di input (`options.quick_action`).
- [ ] Ubah teks halaman agar **tidak** menjanjikan eksekusi perintah ke ERP (hapus contoh "Update proyek Pemkab Malang jadi 50 juta…").
- [ ] Responsif minimal untuk layar laptop & tablet.

### M5 — Batas & log pemakaian (FR-3.1, FR-3.2)
- [ ] Batas pesan per pengguna per hari (zona waktu Asia/Jakarta), batas panjang pesan.
- [ ] Tampilkan sisa kuota harian di UI (mis. "12/50 pesan hari ini").
- [ ] Hitung `est_cost_usd` dari token × tarif di config.
- [ ] (Opsional) Batas anggaran bulanan global: bila tercapai, chat menampilkan pesan dan berhenti memanggil Gemini.

### M6 — Ringkasan pemakaian Super Admin (FR-3.3)
- [ ] Halaman `/ai-center/admin/usage` (permission `ai-center.admin`): pengguna aktif, jumlah pesan, total token, perkiraan biaya (harian/bulanan), rasio suka vs tidak suka, rata-rata latensi first token, jumlah error per kode.
- [ ] Filter rentang tanggal; per pengguna hanya **angka**, bukan isi chat (Keputusan Terbuka #3).

### M7 — Pengaturan tanpa rilis ulang (FR-3.4, P2)
- [ ] Tabel `ai_settings` + `ai_setting_audits`, form admin untuk model, system prompt, batas harian, panjang maksimal, history window.
- [ ] Cache setting; fallback ke `config/ai_center.php`.

### M8 — Fase 2: Dokumen PDF (FR-2.1 – FR-2.3, P1)
> Dibuka setelah gerbang Fase 1 lolos (bagian 7). Bisa dikerjakan di branch terpisah.
- [ ] Migration `ai_attachments`; disk S3 **privat** (`visibility=private`), path `ai-center/{user_id}/{uuid}.pdf`.
- [ ] Dropzone di area chat (drag & drop + tombol), indikator progres unggah & proses.
- [ ] Validasi server: `mimes:pdf`, cek magic bytes `%PDF`, ukuran ≤ 10 MB (configurable), tolak PDF rusak/terenkripsi → `file_invalid`.
- [ ] Laravel kirim `attachment.url` = `Storage::temporaryUrl()` 5 menit (atau isi file, sesuai Keputusan Terbuka #5).
- [ ] `GeminiGateway`: kirim PDF sebagai `inline_data` (`application/pdf`, base64) untuk file kecil, atau Gemini Files API untuk yang lebih besar; tangani file besar/rusak.
- [ ] PDF yang sama bisa ditanya berulang dalam satu percakapan (simpan referensi, tidak unggah ulang).
- [ ] Penghapusan percakapan juga menghapus file S3-nya.
- [ ] P2 (opsional, terpisah): jawaban menyebut halaman (FR-2.4); ekstraksi nota ke tabel tampilan saja, **tanpa** dibandingkan dengan database (FR-2.5).

---

## 6. Keamanan & guardrail (checklist)
- [ ] `GEMINI_API_KEY` hanya di `.env` server; tidak muncul di response, view, JS, log, atau pesan exception. Mudah dirotasi (cukup ganti env + `config:clear`).
- [ ] Tidak ada kode di modul AI yang query tabel ERP lain; tambahkan test arsitektur (mis. Pest `arch()`) bahwa `App\Services\AiCenter` tidak bergantung pada model ERP.
- [ ] Tidak ada function/tool calling dikirim ke Gemini.
- [ ] Output Markdown disanitasi sebelum dirender.
- [ ] CSRF pada semua POST/PATCH/DELETE; throttle tambahan per menit untuk mencegah spam.
- [ ] File S3 privat, akses hanya via temporary URL.
- [ ] Perubahan system prompt tercatat (M7).
- [ ] Pastikan paket API Gemini yang dipakai sesuai kebijakan data yang disetujui (Keputusan Terbuka #1) — **sebelum pilot**.

---

## 7. Pengujian & gerbang rilis

**Otomatis (wajib hijau sebelum merge):**
- Unit: gateway (parsing SSE, mapping error), prompt builder, kalkulasi biaya.
- Feature: akses & privasi, CRUD percakapan, streaming dengan `FakeAiGateway`, batas harian, log 100% tertulis (termasuk saat error & klien putus), feedback, validasi PDF (Fase 2).
- Tidak ada test yang memanggil Gemini sungguhan di CI.

**Manual / evaluasi:**
- [ ] Set uji **30 pertanyaan** pekerjaan rutin (ringkas, rapikan surat, draf email, jelaskan istilah) — simpan di `tests/fixtures/ai-center/prompts.json`, dijalankan via command `php artisan ai-center:eval` terhadap Gemini sungguhan, hasil disimpan untuk direview.
- [ ] **10 PDF contoh** sebelum Fase 2.
- [ ] Waktu sampai token pertama < 3 detik (cek dari `latency_first_token_ms`).
- [ ] Simulasi Gemini mati / kuota habis: menu ERP lain tetap normal.

**Gerbang (dari PRD):**
- Fase 1 → Fase 2: 30 pertanyaan lolos, 0 data bocor.
- Fase 2 → Fase 3: ≥ 70% pilot menilai jawaban membantu.
- Metrik lain: ≥ 50% pilot memakai chat min. 1×/minggu, 100% pemakaian tercatat, biaya < anggaran.

---

## 8. Deployment
- [ ] Nginx: `proxy_buffering off` / header `X-Accel-Buffering: no` untuk route stream; cek `fastcgi_buffering` dan timeout PHP-FPM (≥ 120 dtk) untuk route stream.
- [ ] Set env produksi, jalankan migration, seed permission.
- [ ] Feature flag: akses awal hanya Super Admin, lalu tambah pilot.
- [ ] Monitoring: alert bila error rate tinggi atau biaya harian melewati ambang.

---

## 9. Keputusan terbuka (jangan diasumsikan — tanyakan)
| # | Pertanyaan | Untuk | Default sementara di kode |
|---|---|---|---|
| 1 | Model Gemini & paket API; kebijakan data Google diterima? | Faizal, pimpinan | `GEMINI_MODEL` dari env |
| 2 | Laravel AI SDK dipakai, atau Layanan AI terpisah milik Faizal? | Faizal, Nopal | `GeminiGateway` di Laravel, di balik `AiGateway` |
| 3 | Siapa boleh melihat isi riwayat chat (hanya pemilik / Super Admin juga)? | Pimpinan | Hanya pemilik; admin hanya angka |
| 4 | Data apa yang dilarang dimasukkan ke chat? | Pimpinan | Teks peringatan generik |
| 5 | Anggaran bulanan API & batas harian per pengguna? | Pimpinan | 50 pesan/hari, 8000 karakter |
| 6 | Siapa pengguna pilot? | Pimpinan | Hanya Super Admin |
| 7 | Teks halaman "Upcoming Feature" diubah sesuai MVP? | Nopal | Diubah (M4) |
| 8 | Pemilik produk & penyetuju rilis pilot? | Pimpinan | — |
| 9 | Cara PDF dibaca Layanan AI: temporary URL S3 atau isi file dikirim Laravel? | Faizal, Nopal | Temporary URL 5 menit |
| 10 | Hapus percakapan = soft delete atau hard delete? Retensi berapa lama? | Pimpinan | Soft delete + purge 30 hari |

---

## 10. Di luar cakupan (jangan dikerjakan)
- Perintah yang mengubah data ERP, function/tool calling, AI membaca database ERP, konfirmasi aksi, validasi output terhadap database (Fase 3–4).
- Menggantikan Nakama Chat (chat antar pengguna).
- Model AI selain Gemini.

---

## 11. Definition of Done (MVP Fase 1)
- [ ] Super Admin & pilot bisa chat dengan jawaban mengalir dalam bahasa Indonesia.
- [ ] Riwayat bisa dilanjutkan, di-rename, dihapus; privat per pengguna.
- [ ] Peringatan tetap & label "perlu dicek" tampil.
- [ ] Batas harian & panjang pesan berjalan; pesan error jelas; ERP lain tidak terganggu saat Gemini gagal.
- [ ] 100% pemakaian tercatat; ringkasan pemakaian tersedia untuk Super Admin.
- [ ] Semua test otomatis hijau; eval 30 pertanyaan lolos; checklist keamanan terpenuhi.