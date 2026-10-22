# Rencana: AI Chat Bubble yang Paham Halaman

Status: **menunggu persetujuan tech lead**. Belum ada kode yang dibuat.

## Ringkasan

Tombol chat AI mengambang (pojok kanan bawah) di semua halaman, untuk user yang sudah punya akses AI Center. AI menerima konteks halaman yang sedang dibuka, sehingga user bisa bertanya "jelaskan data di halaman ini" tanpa menyalin isinya.

Fitur ini memakai ulang infrastruktur AI Center yang sudah ada. Tidak ada library baru dan tidak ada perubahan di layanan Nakama AI.

## Yang dipakai ulang

| Kebutuhan | Sudah ada di |
|---|---|
| Koneksi ke AI + streaming | `NakamaAiGateway`, `POST /ai-center/messages` (SSE, `StreamMessageController`) |
| Riwayat chat | `ai_conversations`, `ai_messages` |
| Kuota & batas | 50 pesan/hari, 10/menit, 8.000 karakter (`config/ai_center.php`) |
| Tolakan data sensitif | 422 `sensitive_data_detected` dari Nakama, input dikembalikan ke kotak teks |
| Akses data Nakama sesuai permission | `DataAssistant` + `config/ai_datasets.php` |
| Log pemakaian | `AiUsageLog`, halaman AI Monitor |
| Logika chat di browser | `window.aiCenterChat()` di `resources/js/app.js` |

## Desain

### 1. Bubble

- Komponen Livewire baru `ai.chat-bubble`. Dipasang di `resources/views/layouts/app/sidebar.blade.php` sebelum `</body>`, di dalam `@can('manage-ai-center')`.
- Dibungkus `@persist('ai-bubble')` supaya panel dan percakapan tidak hilang saat pindah halaman. Aplikasi memakai `wire:navigate`.
- Panel kecil (±380×560 px, layar penuh di HP) berisi kotak chat, jawaban yang streaming, tombol stop, dan link "Buka di AI Center".
- Memakai endpoint stream dan komponen Alpine `aiCenterChat` yang sudah ada, ditambah satu field `page_context`.

### 2. Konteks halaman (dua tingkat)

| Tingkat | Isi | Kapan dikirim |
|---|---|---|
| **A. Info halaman** | Nama route, URL path, judul halaman, query/filter aktif | Selalu, otomatis |
| **B. Isi halaman** | Teks yang tampil di area konten utama (`{{ $slot }}`), mis. isi tabel | **Hanya jika user menekan "Sertakan isi halaman ini"** per pesan |

Aturan untuk tingkat B:
- Hanya mengambil teks dari elemen konten utama, bukan sidebar atau navbar.
- Nilai `input`, `textarea`, `select`, field password, dan elemen bertanda `data-ai-ignore` tidak pernah ikut.
- Dipotong ke batas aman (mis. 6.000 karakter) supaya pesan user tetap muat dalam batas 8.000.
- Ditolak di server untuk halaman di daftar terlarang (lihat Keamanan). Pengecekan di server, bukan cuma di browser.

Screenshot (tingkat C) **tidak** dipakai: berat, butuh model vision, dan paling sensitif.

### 3. Server

- `StreamMessageController`: validasi field opsional `page_context` (`route`, `path`, `title`, `content` maks. N karakter).
- `AiChatRequest`: properti baru `pageContext`.
- Konteks disisipkan sebagai blok `KONTEKS HALAMAN` sebelum pesan user, dengan pola yang sama seperti blok data di `DataAssistant`.
- System prompt diperbarui. Prompt sekarang menyatakan AI tidak punya akses data ERP. Isi blok konteks diperlakukan sebagai **data**, bukan instruksi, sebagai perlindungan dari teks halaman yang berisi perintah (prompt injection).
- Yang disimpan di `ai_messages` hanya pesan user. Isi halaman tidak ikut disimpan; cukup penanda "halaman X disertakan".

### 4. Keamanan & privasi

- **Data keluar ke layanan eksternal.** Tingkat B mengirim apa yang dilihat user ke Nakama AI (provider Gemini). Karena itu B wajib dipicu user, dan ada daftar halaman terlarang.
- **Daftar halaman terlarang** (config, dicek di server): minimal `tech.api-keys` dan `tech.sso`, yang memuat token dan secret. Kandidat lain menunggu keputusan (lihat bawah).
- Filter data sensitif Nakama (NIK, dll.) tetap berlaku untuk konteks halaman.
- Kuota, rate limit, dan log pemakaian sama dengan AI Center. Tidak ada jalur baru yang melewati batas.
- Hanya user dengan permission `manage-ai-center`; user suspended tetap ditolak lewat `Gate::before`.

## Keputusan yang dibutuhkan

1. **Tingkat konteks:** A saja, atau A + B dengan tombol? *Usulan: A + B dengan tombol.*
2. **Riwayat:** percakapan bubble ikut muncul di halaman AI Center, atau terpisah? *Usulan: ikut, supaya satu tempat.*
3. **Halaman terlarang** selain API Keys dan SSO: apakah halaman finance (disbursement, rekonsiliasi), investor documents, dan data karyawan juga dilarang mengirim isi halaman (tingkat B)?
4. **Siapa yang dapat:** semua pemegang `manage-ai-center` dengan kuota yang sama, atau dibatasi role tertentu dulu (pilot)?
5. **Driver production:** pastikan `AI_CENTER_DRIVER=nakama`. Default-nya `fake`, yang hanya mengembalikan jawaban palsu.

## Tahapan kerja

| # | Pekerjaan | Perkiraan |
|---|---|---|
| 1 | `page_context` di controller + `AiChatRequest` + blok prompt + daftar halaman terlarang (config) | 0,5 hari |
| 2 | Komponen bubble (Livewire + Alpine, `@persist`, responsif, tema gelap/terang) | 1 hari |
| 3 | Pengambilan teks halaman di browser (tingkat B) + tombol "Sertakan isi halaman" | 0,5 hari |
| 4 | Update system prompt + uji jawaban di beberapa halaman nyata | 0,5 hari |
| 5 | Feature test + uji manual | 0,5 hari |

Total kira-kira **3 hari kerja**, belum termasuk revisi setelah review.

## Rencana test

- Bubble hanya ter-render untuk user dengan `manage-ai-center`.
- `page_context` divalidasi: field tak dikenal ditolak, isi dipotong sesuai batas.
- Halaman terlarang: konten ditolak server walau browser mengirimnya.
- Konteks masuk ke prompt yang dikirim gateway (dicek lewat `FakeAiGateway`), dan tidak tersimpan di `ai_messages`.
- Kuota harian dan rate limit tetap berlaku untuk pesan dari bubble.
- Respons 422 data sensitif dari halaman: pesan dikembalikan ke input, tidak ada yang tersimpan.

## File yang akan disentuh

- Baru: `app/Livewire/Ai/ChatBubble.php`, `resources/views/livewire/ai/chat-bubble.blade.php`, test feature.
- Diubah: `app/Http/Controllers/AiCenter/StreamMessageController.php`, `app/Services/AiCenter/AiChatRequest.php`, `config/ai_center.php` (system prompt + daftar halaman terlarang), `resources/js/app.js`, `resources/views/layouts/app/sidebar.blade.php`.
