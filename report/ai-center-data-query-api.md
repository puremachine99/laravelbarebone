# PLAN — AI Center: Data Query API (AI membaca data ERP lewat API)

> Plan lanjutan dari [ai-center-module.md](ai-center-module.md), yang saat ini sengaja membatasi AI agar **tidak** membaca data ERP (Fase 3).
> Dokumen ini adalah rancangan untuk membuka akses baca itu dengan aman.
> Draft: 10 Okt 2026. Revisi terakhir: 10 Okt 2026 — layanan AI (Mas Faizal) mengerjakan klasifikasi skill + function calling; katalog API kita menjadi "data master".

---

## 1. Tujuan

User atau pimpinan bisa bertanya ke AI Center tentang data di dalam aplikasi, misalnya:

- "Coba ringkas bagaimana perkembangan partnership dalam sebulan terakhir."
- "Siapa Funding Officer dengan capaian tertinggi sepanjang waktu?"
- "Siapa saja orang di tim Technology?"
- "Department mana yang kurang perform?"

AI tidak punya akses langsung ke database. Semua data lewat **satu API terkontrol** milik aplikasi, yang aturannya diatur dari sisi kita, sehingga data sensitif tidak pernah terekspos.

**Prinsip utama:** keamanan dijamin oleh server (whitelist + otorisasi), bukan oleh instruksi ke AI. Prompt tidak bisa diandalkan sebagai pengaman.

## 2. Keputusan yang sudah diambil

| Topik | Keputusan |
|---|---|
| Cara AI mengakses data | Lewat API milik aplikasi, bukan akses DB langsung dan bukan SQL bebas dari AI |
| Bentuk | **Katalog dataset** di config + satu service query generik ([DataQueryService.php](../app/Services/AiData/DataQueryService.php)) |
| "Data master" | **Katalog kita adalah data master.** Layanan AI membaca daftar dataset, skill, departemen, dimensi, metrik, dan filter dari `GET /api/ai/datasets`. Layanan AI tidak memetakan tabel database sendiri dan tidak pernah tahu nama tabel |
| Pembagian kerja | **Layanan AI (Mas Faizal):** klasifikasi pertanyaan → skill/departemen → tentukan filter → panggil API kita lewat function calling → rangkum jawaban. **Laravel (kita):** sediakan katalog + API, validasi, otorisasi per user, ambil data, audit log |
| Jalur utama | **Jalur B** (layanan AI memanggil API kita). Jalur A (Laravel yang memilih dan menjalankan query, sudah jadi) tetap ada sebagai cadangan lewat saklar config |
| Auth pemanggil | **Satu Bearer token** di `.env` (`AI_DATA_API_TOKEN`) |
| Identitas user | **Assertion bertanda tangan** (HMAC) yang diterbitkan Laravel untuk user yang login, diteruskan layanan AI ke header. Departemen/role yang dikirim ke layanan AI hanya untuk routing, **bukan** dasar izin (4.7) |
| Otorisasi | Per user yang bertanya: permission per dataset + scope departemen, dicek di server menurut database kita (4.6). Gemini dan layanan AI tidak pernah menjadi penentu akses |
| Data yang boleh | Informasi kerja dasar: nama, NIS (ID karyawan, kolom `employees.nis`), departemen, posisi, KPI/capaian |
| Data sensitif | Tidak pernah didaftarkan di katalog: NIK (nomor KTP, kolom `employees.nik`), NPWP, status kepegawaian, status pernikahan, tanggal lahir, gender, telepon, email, alamat, gaji, rekening |

## 3. Status implementasi

Semua di bawah ini **belum di-commit**. Migration `ai_data_query_logs` dan permission `ai-data-*` sudah dijalankan di **lokal**, belum di server.

### Sudah dibuat

**Engine query:**

- Katalog: [config/ai_datasets.php](../config/ai_datasets.php). Dua dataset: `ai_usage` (pemakaian AI Center) dan `fo_achievement` (capaian Funding Officer, per orang).
- Interface dataset: [Dataset.php](../app/Services/AiData/Dataset.php); implementasi [AiUsageDataset.php](../app/Services/AiData/Datasets/AiUsageDataset.php) dan [FoAchievementDataset.php](../app/Services/AiData/Datasets/FoAchievementDataset.php). Ekspresi bulan ([SqlMonth.php](../app/Services/AiData/SqlMonth.php)) untuk PostgreSQL (produksi), MySQL, dan SQLite (test); kedua dataset sudah dicek di PostgreSQL lokal.
- [DatasetCatalog.php](../app/Services/AiData/DatasetCatalog.php): siapa boleh memakai dataset apa, dan katalog per user.
- [DataQueryService.php](../app/Services/AiData/DataQueryService.php): validasi whitelist, otorisasi, scope departemen, ambang kelompok minimum, `all_time`, perbandingan periode di server, pengurutan sebelum `limit`, batas baris, audit log.
- Audit log: tabel `ai_data_query_logs` + model [AiDataQueryLog.php](../app/Models/AiDataQueryLog.php).
- Permission: grup `ai-data` di [resource_permissions.php](../config/resource_permissions.php) (`ai-data-ai-usage.view`, `ai-data-fo-achievement.view`, `ai-data-all-departments.view`), modul `manage-ai-center`, tidak diberikan otomatis (`legacy_grant => false`).
- Command testing: `php artisan ai-data:query {email} '{json}'` dan `--catalog` ([AiDataQuery.php](../app/Console/Commands/AiDataQuery.php)).
- Field `skill` dan `department` per dataset di katalog.
- Data master (read only): dataset `time_based => false` (periode ditolak, `period: null`) dan `department_scoped => false` untuk data referensi bersama (`scope: "global"`). Basis kode bersama [MasterDataset.php](../app/Services/AiData/Datasets/MasterDataset.php); filter dataset dicocokkan sebagian dan tidak peka huruf besar/kecil.
- Departemen user diambil dari data karyawan (`employees.department_id`), dengan `users.department_id` hanya sebagai cadangan ([UserDepartment.php](../app/Services/AiData/UserDepartment.php)). Di lokal ditemukan 180 user yang salinan departemennya tidak sinkron.
- Migration `ensure_ai_master_data_permissions_exist` membuat 7 permission data master; belum dijalankan.
- Filter per tanggal `from_date`/`to_date` (mis. "30 hari terakhir"); `compare_previous` membandingkan dengan jumlah hari yang sama sebelumnya; target FO dihitung proporsional per hari.

**Jalur B (sisi Laravel selesai, menunggu layanan AI):**

- Endpoint `GET /api/ai/datasets` dan `POST /api/ai/query` ([AiDataController.php](../app/Http/Controllers/Api/AiDataController.php), [routes/api.php](../routes/api.php)).
- Lapis 1: Bearer token tunggal `api.token` ([AuthenticateApiToken.php](../app/Http/Middleware/AuthenticateApiToken.php)), `AI_DATA_API_TOKEN`.
- Lapis 2: assertion bertanda tangan ([UserAssertion.php](../app/Services/AiData/UserAssertion.php)) diperiksa middleware `ai.assertion` ([AuthenticateAiUserAssertion.php](../app/Http/Middleware/AuthenticateAiUserAssertion.php)): tanda tangan, kedaluwarsa, user ada dan aktif, percakapan milik user. `AI_DATA_ASSERTION_SECRET`, berlaku 300 detik (`AI_DATA_ASSERTION_TTL`).
- Rate limit `POST /api/ai/query`: `AI_DATA_QUERIES_PER_HOUR` per user (429). `GET /api/ai/datasets`: 120/menit.
- Saklar `AI_DATA_MODE`: `laravel` (jalur A, default) atau `gateway` (jalur B). Pada `gateway`, Laravel tidak memilih query; ia mengirim `user_context` (departemen, role, `available_tools`, assertion) dan system prompt `ai_center.data.gateway_system_prompt` ke layanan AI ([DataAssistant.php](../app/Services/AiCenter/DataAssistant.php), [NakamaAiGateway.php](../app/Services/AiCenter/NakamaAiGateway.php)).
- Token kosong atau secret kosong = API tertutup.
- Test: [AiDataApiTest.php](../tests/Feature/Api/AiDataApiTest.php), [UserAssertionTest.php](../tests/Feature/AiData/UserAssertionTest.php), tambahan di [AiCenterDataModeTest.php](../tests/Feature/AiCenter/AiCenterDataModeTest.php) dan [NakamaAiGatewayTest.php](../tests/Feature/AiCenter/NakamaAiGatewayTest.php).

**Jalur A (cadangan, sudah jalan dengan AI tiruan):**

- [DataAssistant.php](../app/Services/AiCenter/DataAssistant.php): untuk user yang punya dataset, setiap pesan: (1) panggilan AI terpisah memilih query (JSON, maks. 3); (2) Laravel menjalankannya sebagai user itu; (3) hasil ditempel di pesan jawaban + baris "Sumber data: ..." di bawah jawaban.
- Batas: maks. 3 query per pesan, 30 query per user per jam (`AI_DATA_QUERIES_PER_HOUR`), default 50 baris. Saklar `AI_DATA_ENABLED`.
- Test: folder [tests/Feature/AiData](../tests/Feature/AiData) dan [AiCenterDataModeTest.php](../tests/Feature/AiCenter/AiCenterDataModeTest.php).

### Belum dibuat

- Sisi layanan AI (Mas Faizal): membaca `user_context`, klasifikasi skill, function calling ke `/api/ai/*`, meneruskan assertion ke header.
- Uji end-to-end dengan layanan AI sungguhan.
- Dataset untuk departemen selain Funding (bagian 7).
- Halaman audit log.

Dokumentasi `/api/ai/*` sudah ada di halaman `/documentation` (bagian "AI Data API": overview, autentikasi, kedua endpoint, cara testing). Halaman itu **publik**; isinya tidak memuat token atau secret.

## 4. Rancangan

### 4.1 Alur (jalur B)

```
User: "Coba ringkas perkembangan partnership sebulan terakhir"
  │
  ▼
[Laravel] kirim pesan ke layanan AI + user_context (departemen, role, daftar tool, assertion)
  │
  ▼
[Layanan AI] klasifikasi → skill "partnership" → filter 30 hari terakhir
  │
  ▼  function call
[Layanan AI] POST /api/ai/query  (Bearer token + header X-AI-User-Assertion)
  │
  ▼
[Laravel] cek token → cek assertion → user aktif? → izin dataset? → scope departemen → ambil data → audit log
  │
  ▼  JSON
[Layanan AI] Gemini merangkum → jawaban ke user
```

### 4.2 Katalog dataset = data master

Setiap dataset menyebut secara eksplisit apa yang boleh dilihat. Yang tidak tercantum tidak bisa diminta.

```php
'fo_achievement' => [
    'skill'       => 'funding',                 // BARU: dipakai layanan AI untuk routing
    'department'  => 'Departemen Finance',      // BARU: departemen pemilik data
    'label'       => 'Capaian Funding Officer (modul Funding)',
    'description' => '...',                     // dibaca AI untuk memilih tool
    'permission'  => 'ai-data-fo-achievement.view',
    'source'      => FoAchievementDataset::class,
    'min_group_size' => 1,                      // per orang; default global 5
    'dimensions'  => ['employee' => '...', 'department' => '...', 'month' => '...'],
    'metrics'     => ['leads' => '...', 'nilai_closing' => '...', 'achievement_pct' => '...'],
    'filters'     => ['department'],
],
```

- **Agregat dibatasi per metrik.** AI tidak memilih kolom maupun fungsi agregat. Cara hitung setiap metrik ada di class dataset.
- Menambah data baru = satu entri katalog + satu class dataset. Endpoint tidak bertambah.
- Struktur database bisa berubah tanpa mengganggu layanan AI, karena layanan AI hanya mengenal nama dataset.

### 4.3 Kontrak API (untuk Mas Faizal)

Semua endpoint wajib membawa dua header:

```
Authorization: Bearer <AI_DATA_API_TOKEN>
X-AI-User-Assertion: <assertion dari user_context>
```

**`GET /api/ai/datasets`** — daftar tool yang boleh dipakai user pemilik assertion, sudah dalam bentuk siap dipakai untuk function calling:

```json
{ "data": [
  { "skill": "funding", "department": "Departemen Finance", "dataset": "fo_achievement",
    "label": "Capaian Funding Officer (modul Funding)", "description": "...",
    "dimensions": { "employee": "Nama Funding Officer", "department": "...", "month": "..." },
    "metrics": { "nilai_closing": "Total nominal investasi closing (Rp)", "...": "..." },
    "filters": ["department"] }
] }
```

**`POST /api/ai/query`** — satu permintaan data:

```json
{ "dataset": "fo_achievement", "group_by": ["employee"], "metrics": ["nilai_closing", "achievement_pct"],
  "filters": { "department": "Departemen Finance" },
  "from": "2026-09", "to": "2026-10",          // per bulan, ATAU
  "from_date": "2026-09-10", "to_date": "2026-10-10",   // per tanggal (BARU), ATAU
  "all_time": true,
  "compare_previous": true, "sort": "nilai_closing", "direction": "desc", "limit": 10 }
```

Respons:

```json
{ "dataset": "fo_achievement", "label": "...", "period": { "from": "2026-09", "to": "2026-10" },
  "compared_to": null, "scope": "all_departments", "group_by": ["employee"], "metrics": ["..."],
  "rows": [ { "employee": "Budi", "nilai_closing": 500000000.0, "achievement_pct": 66.7 } ],
  "suppressed_rows": 0, "truncated": false, "notes": [] }
```

Kode status:

| Status | Arti |
|---|---|
| 200 | Berhasil |
| 401 | Token salah/kosong, assertion tidak valid atau kedaluwarsa |
| 403 | User tidak aktif, bukan pemilik percakapan, atau tidak punya izin dataset |
| 422 | Permintaan di luar katalog (dataset/dimensi/metrik/filter/parameter tidak dikenal, periode tidak valid) |
| 429 | Batas query per user tercapai |

Aturan permintaan:

- `all_time` tidak bisa digabung dengan `from`/`to`/`from_date`/`to_date` atau `compare_previous`.
- Tanpa periode = bulan berjalan.
- `compare_previous` membandingkan dengan periode sebelumnya yang sama panjang; tidak bisa digabung dengan `group_by: ["month"]`. Saat membandingkan, `sort` boleh memakai `<metrik>_delta`, `_previous`, `_delta_pct`.
- Pengurutan dilakukan **sebelum** `limit`, jadi "5 teratas" benar-benar 5 teratas.
- Metrik berakhiran `_pct` bernilai `null` bila tidak bisa dihitung (mis. target belum diisi).

### 4.4 `user_context` di payload ke layanan AI

Laravel menambahkan ini ke setiap request `POST /v1/chat` ke layanan AI:

```json
"user_context": {
  "department": "Departemen Finance",
  "roles": ["Investor Admin"],
  "available_tools": ["fo_achievement", "ai_usage"],
  "assertion": "eyJ1aWQiOjEyLCJjaWQiOjM0NSwiZXhwIjoxNzkxNjI0NjAwfQ.k3Jx9Qe1v7wq2pPZ0xY4mRtN8cL5aHs6dVbE1uF0gIo"
}
```

- `department` dan `roles`: **hanya untuk routing** (memilih skill lebih dulu, menyesuaikan gaya jawaban). API kita tidak memakainya sebagai dasar izin.
- `available_tools`: dataset yang boleh untuk user ini, supaya layanan AI tidak menawarkan tool yang pasti ditolak.
- `assertion`: **diteruskan apa adanya ke header** `X-AI-User-Assertion` oleh kode layanan AI. **Tidak boleh** masuk ke prompt, argumen tool, atau terlihat oleh Gemini.

### 4.5 Keamanan (wajib)

- **Whitelist**: dataset, dimensi, metrik, filter, dan nama parameter harus ada di katalog; selain itu ditolak (422). Contoh yang ditolak: `columns: ["email"]`, `group_by: ["nik"]`, `filters: {npwp: ...}`.
- **Tanpa SQL dari AI**: hanya parameter terstruktur.
- **Read-only**: tidak ada endpoint tulis.
- **Ambang kelompok minimum** (default 5 orang): baris dari kurang dari 5 orang disembunyikan (`suppressed_rows`). Dataset yang memang per orang dan hanya berisi data kerja yang boleh (mis. `fo_achievement`) memasang `min_group_size => 1`.
- **Perhitungan di server**: selisih dan persentase dihitung server, bukan oleh Gemini.
- Batas periode eksplisit 24 bulan (`all_time` tidak dibatasi), batas baris 200, rate limit per user.
- **Audit log** setiap permintaan, termasuk yang ditolak. Tidak menyimpan isi data.
- Hasil query diperlakukan sebagai **data**, bukan instruksi. Dataset tidak memuat kolom teks bebas (judul, catatan) yang bisa berisi prompt injection.
- Opsional: batasi IP sumber ke server layanan AI.

### 4.6 Otorisasi per user

1. **Katalog difilter per user.** Dataset yang tidak boleh dilihat user itu tidak muncul di `GET /api/ai/datasets` maupun `available_tools`.
2. **Eksekusi dicek ulang.** Dataset terlarang ditolak dan dicatat sebagai `denied`.
3. **Syarat dasar**: user `active` (suspended selalu ditolak), punya `manage-ai-center`, dan punya permission dataset.
4. **Row-level scope.** Tanpa `ai-data-all-departments.view`, data dibatasi ke `department_id` user **menurut database kita**; user tanpa departemen tidak mendapat baris. Filter departemen hanya bisa mempersempit, tidak memperluas.

Aturan dicentang per role di halaman Role Management (kartu "AI Center Data"). Super Admin lolos lewat `Gate::before`.

### 4.7 Assertion user bertanda tangan

Bentuknya mirip JWT versi mini: `<isi>.<stempel>`.

- **Isi** (base64url dari JSON, bisa dibaca siapa saja): `{"uid": 12, "cid": 345, "exp": 1791624600}` — ID user, ID percakapan AI Center, waktu kedaluwarsa (±5 menit).
- **Stempel**: HMAC-SHA256 dari isi, memakai secret `AI_DATA_ASSERTION_SECRET` yang **hanya ada di `.env` Laravel**. Layanan AI dan Gemini tidak tahu secret ini.

Pemeriksaan di API, berurutan: Bearer token benar → stempel cocok (`hash_equals`) dan belum kedaluwarsa → user ada, `active`, tidak dihapus → percakapan milik user tersebut → otorisasi dataset (4.6).

Mengapa perlu: dengan token + `user_id` polos, pemegang token bisa menulis `user_id` siapa saja (termasuk Super Admin). Isi assertion bisa dibaca tetapi tidak bisa diubah tanpa secret, jadi token yang bocor saja tidak cukup untuk menyamar sebagai user lain.

Bila layanan AI lebih nyaman dengan format standar, isinya bisa dibuat sebagai JWT HS256 asli; prinsipnya sama.

### 4.8 Jalur A (cadangan)

Laravel yang memilih query (panggilan AI terpisah), menjalankannya, lalu menempelkan hasilnya ke pesan. Dipakai bila layanan AI belum siap atau sedang bermasalah. Saat jalur B aktif, langkah "pilih query" jalur A dimatikan lewat config agar tidak ada dua sistem yang memanggil data untuk pesan yang sama.

## 5. Output laporan / presentasi

Gemini hanya menghasilkan teks atau struktur. File `.pptx` harus dibuat Laravel: AI menghasilkan JSON struktur slide, Laravel merendernya. Butuh library baru (mis. PhpPresentation) dan **perlu persetujuan**. Dikerjakan setelah query stabil.

## 6. Milestone

Selesai (sisi Laravel):

- ~~Rapikan komponen token~~
- ~~Katalog + engine query~~
- ~~Otorisasi per user~~
- ~~Dataset `ai_usage` dan `fo_achievement`~~
- ~~Jalur A~~ (diuji dengan AI tiruan)

Berikutnya:

- ~~Field `skill`/`department` di katalog + filter per tanggal~~
- ~~Endpoint HTTP + token + assertion + rate limit~~
- ~~`user_context` di payload + saklar `AI_DATA_MODE`~~

Berikutnya:

1. **Kontrak final dengan Mas Faizal**: bagian 4.3–4.4 dan 4.7 sudah sesuai implementasi. Konfirmasi bahwa layanan AI menyisipkan assertion ke header dari kodenya sendiri.
2. **Isi `AI_DATA_API_TOKEN` dan `AI_DATA_ASSERTION_SECRET`** di `.env` (lokal untuk uji, lalu server), kirim token ke Mas Faizal lewat jalur aman. Secret assertion **tidak** dikirim ke siapa pun.
3. **Uji end-to-end** dengan `AI_DATA_MODE=gateway` di lokal/staging.
4. **Dataset per departemen** (bagian 7), dimulai dari Partnership dan daftar karyawan.
5. **Halaman audit log** dan dokumentasi API.
6. **Opsional**: ekspor `.pptx`.

Setiap milestone diakhiri test hijau dan commit terpisah.

## 7. Dataset per departemen

| Skill / departemen | Dataset | Status |
|---|---|---|
| Funding (Departemen Finance) | `fo_achievement` — capaian Funding Officer per orang: leads, QL, pitching, won, closing, nilai closing, target nilai closing (target tahunan tersimpan dibagi rata per bulan), capaian % | Selesai |
| (umum) | `ai_usage` — pertanyaan ke AI Center dan pengguna aktif, per bulan/departemen | Selesai |
| Data master (`master_data`) | `employees` — karyawan aktif: nama, NIS, departemen, posisi (struktural/fungsional/teknis), region; dibatasi departemen user | Selesai |
| Data master | `departments` — departemen, kode, jumlah karyawan aktif | Selesai |
| Data master | `roles` — role aplikasi dan jumlah pengguna (bisa per departemen), tanpa nama | Selesai |
| Data master | `regions` — region dengan jumlah provinsi, kabupaten/kota, OPD | Selesai |
| Data master | `opds` — OPD/instansi: jenis, kabupaten/kota, provinsi, region, bidang urusan | Selesai |
| Data master | `urusans` — bidang urusan, kode, jumlah OPD | Selesai |
| Data master | `products` — produk: nama, kode, kategori, service center, divisi, pelaksana, status | Selesai |
| Partnership | Perkembangan pipeline/pitching/realisasi | Belum. Contoh kebutuhan: "ringkas perkembangan partnership sebulan terakhir" |
| Technology | Daftar karyawan per departemen | Tercakup oleh `employees` (filter departemen) |
| 5 departemen lainnya | — | Menunggu daftar data yang boleh dibuka per departemen |

Data **finance dan investor** di luar capaian FO ditunda sampai ditentukan bagian mana yang boleh dikirim ke Gemini.

## 8. Keputusan terbuka

1. **Daftar 8 departemen dan data apa yang boleh dibuka per departemen.** Setiap baris jadi satu dataset.
2. Arti "**data pcs**" (contoh Mas Faizal: "daftar nama-nama di teknologi melalui data pcs").
3. Konfirmasi Mas Faizal: layanan AI bisa menyisipkan assertion ke header dari kodenya sendiri, tanpa lewat model. Format assertion: versi mini (4.7) atau JWT HS256.
4. Definisi "**performa**" per departemen untuk pertanyaan "departemen mana yang kurang perform".
5. Role mana yang diberi akses data, dataset mana untuk role mana, dan siapa yang boleh "semua departemen".
6. **Dasar pemrosesan data (UU PDP)** untuk data yang dikirim ke Gemini (pihak ketiga), dicatat sebelum dataset bertambah.
7. Persetujuan menambah library untuk `.pptx`.

## 9. Risiko

| Risiko | Mitigasi |
|---|---|
| Kolom sensitif ikut keluar | Whitelist dimensi/metrik per dataset, tanpa serialisasi model, test bahwa NIK/NPWP/email tidak muncul |
| Agregat kelompok kecil membuka data individu | Ambang kelompok minimum (default 5) untuk dataset agregat |
| **Differencing**: total dikurangi baris yang terlihat bisa menyingkap baris yang disembunyikan | Belum ditangani penuh. Sebelum menambah data yang lebih sensitif, tambahkan pembulatan atau sembunyikan total saat ada baris tersembunyi |
| Token bocor | Hanya di `.env`; tanpa assertion yang sah, token saja tidak memberi data; rotasi dengan mengganti nilainya |
| Pemegang token menyamar sebagai user lain | Assertion HMAC dengan secret terpisah, berlaku ±5 menit, terikat satu user dan satu percakapan |
| Assertion bocor lewat model | Disisipkan kode layanan AI ke header, tidak pernah masuk prompt/argumen tool. Tanpa jaminan ini jalur B tidak diaktifkan |
| Layanan AI memakai `department`/`roles` untuk izin | API tidak mempercayai klaim itu; izin selalu dicek dari database kita |
| Endpoint terbuka diserang dari luar | Token + assertion wajib, rate limit, respons error seragam, opsional pembatasan IP |
| Prompt injection lewat isi data | Tanpa kolom teks bebas; hasil diperlakukan sebagai data |
| AI salah menghitung | Selisih dan persentase dihitung server; respons menyertakan periode dan scope |
| User melihat data di luar haknya | Otorisasi di server, katalog difilter per user, scope departemen |
| Akses data terberikan otomatis lewat seeder | `legacy_grant => false` untuk grup `ai-data` |
| Dua sistem memanggil data untuk satu pesan | Saklar mematikan "pilih query" jalur A saat jalur B aktif |
| Biaya dan beban query | Batas baris, batas periode, rate limit per user, kuota harian chat |
