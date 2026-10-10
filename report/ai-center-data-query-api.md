# PLAN — AI Center: Data Query API (AI membaca data ERP lewat API)

> Plan lanjutan dari [ai-center-module.md](ai-center-module.md), yang saat ini sengaja membatasi AI agar **tidak** membaca data ERP (Fase 3).
> Dokumen ini adalah rancangan untuk membuka akses baca itu dengan aman.
> Draft: 10 Okt 2026. Revisi terakhir: 10 Okt 2026 (jalur A jadi jalur pertama; masukan council ditambahkan).

---

## 1. Tujuan

User bisa bertanya ke AI Center tentang data di dalam aplikasi, misalnya:

- "Department mana yang kurang perform?"
- "Berikan report dan perbandingan performa Nakama bulan lalu vs bulan ini, buatkan ringkasannya."

AI tidak punya akses langsung ke database. Semua data lewat **satu engine query terkontrol** yang aturannya diatur dari sisi kita, sehingga data sensitif tidak pernah terekspos.

**Prinsip utama:** keamanan dijamin oleh server (whitelist + otorisasi), bukan oleh instruksi ke AI. Prompt tidak bisa diandalkan sebagai pengaman.

## 2. Keputusan yang sudah diambil

| Topik | Keputusan |
|---|---|
| Cara AI mengakses data | Lewat engine query milik aplikasi, bukan akses DB langsung dan bukan SQL bebas dari AI |
| Bentuk | **Katalog dataset** di config + satu service query generik |
| Jalur pertama | **Jalur A**: Laravel yang memanggil engine query untuk user yang sedang login. Tanpa endpoint publik, tanpa token, tanpa assertion |
| Jalur B | Peningkatan **bersyarat**: hanya dikerjakan bila gateway `nakama-ai` terbukti bisa (1) menjalankan tool custom dan (2) menyisipkan header assertion dari kodenya sendiri, tanpa melewati model. Rancangan auth-nya disimpan di 4.7 |
| Otorisasi | Per user yang bertanya: permission per dataset + scope departemen, dicek di server (4.6). Gemini tidak pernah menjadi penentu akses |
| Cakupan departemen | Semua departemen, tetapi dibatasi per user sesuai 4.6 |
| Data yang boleh | Informasi kerja dasar: nama, NIS (ID karyawan, kolom `employees.nis`), departemen, posisi, KPI/capaian |
| Data sensitif | Tidak pernah didaftarkan di katalog: NIK (nomor KTP, kolom `employees.nik`), NPWP, status kepegawaian, status pernikahan, tanggal lahir, gender, telepon, email, alamat, gaji, rekening |

## 3. Status implementasi

### Sudah dibuat (belum di-commit; migration **belum dijalankan** di environment mana pun)

**Engine query (milestone 1, 2b, sebagian 3):**

- Katalog: [config/ai_datasets.php](../config/ai_datasets.php). Dua dataset: `ai_usage` (pemakaian AI Center) dan `fo_achievement` (capaian Funding Officer, per orang).
- Interface dataset: [Dataset.php](../app/Services/AiData/Dataset.php); implementasi [AiUsageDataset.php](../app/Services/AiData/Datasets/AiUsageDataset.php) dan [FoAchievementDataset.php](../app/Services/AiData/Datasets/FoAchievementDataset.php). Ekspresi bulan ([SqlMonth.php](../app/Services/AiData/SqlMonth.php)) disiapkan untuk PostgreSQL (produksi), MySQL, dan SQLite (test); kedua dataset sudah dicek dengan query baca di PostgreSQL lokal.
- Command testing: `php artisan ai-data:query {email} '{json}'` dan `--catalog` ([AiDataQuery.php](../app/Console/Commands/AiDataQuery.php)). Menjalankan permintaan persis seperti AI nanti: sebagai user itu, dengan izin dan scope-nya, dan tercatat di audit log.
- [DatasetCatalog.php](../app/Services/AiData/DatasetCatalog.php): siapa boleh memakai dataset apa, dan katalog yang boleh dilihat AI per user.
- [DataQueryService.php](../app/Services/AiData/DataQueryService.php): validasi whitelist, otorisasi, scope departemen, ambang kelompok minimum, perbandingan periode di server, batas baris, audit log.
- Audit log: tabel `ai_data_query_logs` + model [AiDataQueryLog.php](../app/Models/AiDataQueryLog.php).
- Permission: grup `ai-data` di [resource_permissions.php](../config/resource_permissions.php) (`ai-data-ai-usage.view`, `ai-data-fo-achievement.view`, `ai-data-all-departments.view`), dengan modul `manage-ai-center`. Migration `ensure_ai_data_permissions_exist` hanya membuat permission, tidak memberikannya ke siapa pun.
- `ResourcePermissions::grantLegacyAccess()` sekarang melewati grup ber-`legacy_grant => false`, supaya seeder tidak otomatis memberi akses data (terutama "semua departemen") ke pemegang `manage-ai-center`.
- Test: folder [tests/Feature/AiData](../tests/Feature/AiData).

**Sisa dari tahap token (untuk jalur B, belum dipakai):** middleware `api.token` ([AuthenticateApiToken.php](../app/Http/Middleware/AuthenticateApiToken.php)), config `ai_center.data_api.token`, dan test-nya. Sistem banyak token (tabel, halaman admin, command) sudah dihapus.

**Integrasi ke AI Center (milestone 4):**

- [DataAssistant.php](../app/Services/AiCenter/DataAssistant.php): untuk user yang punya minimal satu dataset, setiap pesan melewati tiga langkah: (1) panggilan AI terpisah yang hanya memilih query (JSON, maks. 3), diberi katalog milik user itu saja; thread-nya di layanan AI langsung dihapus; (2) Laravel menjalankan query lewat `DataQueryService` sebagai user tersebut; (3) hasilnya ditempel di pesan untuk panggilan jawaban, dengan system prompt mode data (`ai_center.data.system_prompt`), lalu baris "Sumber data: ..." ditambahkan di bawah jawaban.
- Pesan user yang tersimpan di riwayat tetap teks aslinya (tanpa blok data).
- Batas: maks. 3 query per pesan, 30 query per user per jam (`AI_DATA_QUERIES_PER_HOUR`), default 50 baris per query. Saklar `AI_DATA_ENABLED`.
- Gagal di langkah data (error AI, rencana tidak terbaca) → jawaban biasa tanpa data; tidak pernah menggagalkan chat.
- User tanpa dataset: alur chat lama, tanpa perubahan.
- Test: [AiCenterDataModeTest.php](../tests/Feature/AiCenter/AiCenterDataModeTest.php).
- Konsekuensi biaya: user dengan akses data memakai 2 panggilan AI per pesan (rencana + jawaban).

### Belum dibuat

- Uji dengan Gemini sungguhan (yang sudah diuji: AI tiruan `FakeAiGateway`). Perlu dicek apakah Gemini konsisten membalas JSON rencana yang valid.
- Dataset "performa per departemen": menunggu definisi metrik (keputusan terbuka #1).
- Halaman untuk melihat audit log.

## 4. Rancangan

### 4.1 Katalog dataset

Setiap dataset menyebut secara eksplisit apa yang boleh dilihat. Yang tidak tercantum tidak bisa diminta.

```php
'ai_usage' => [
    'label'       => 'Pemakaian AI Center',
    'description' => '...',
    'permission'  => 'ai-data-ai-usage.view',
    'source'      => AiUsageDataset::class,   // class yang menyusun query agregat
    'dimensions'  => ['month' => '...', 'department' => '...'],   // boleh di-group_by
    'metrics'     => ['questions' => '...', 'active_users' => '...'],
    'filters'     => ['department'],
    // opsional: 'min_group_size' => 10  (hanya bisa menaikkan, tidak menurunkan)
],
```

- **Agregat dibatasi per metrik.** AI tidak memilih kolom maupun fungsi agregat (`sum`/`avg`). Setiap metrik sudah didefinisikan cara hitungnya di class dataset.
- Menambah data baru berarti menambah satu entri + satu class dataset, tanpa endpoint baru.
- Katalog ini pada dasarnya **semantic layer**: nanti bisa dipakai juga untuk dashboard, laporan otomatis, atau ekspor `.pptx`.

### 4.2 Bentuk permintaan dan hasil

Permintaan (dibuat oleh AI, divalidasi server):

```json
{ "dataset": "ai_usage", "group_by": ["department"], "metrics": ["questions"],
  "filters": { "department": "Finance" }, "from": "2026-09", "to": "2026-09",
  "compare_previous": true, "limit": 50 }
```

Parameter lain: `all_time: true` (dari data pertama sampai bulan ini; tidak bisa digabung dengan `from`/`to` atau `compare_previous`), `sort` (nama metrik, termasuk `_previous`/`_delta`/`_delta_pct` saat membandingkan) dan `direction` (`desc` default). Pengurutan dilakukan **sebelum** `limit`, jadi "5 teratas" benar-benar 5 teratas.

Contoh pertanyaan "siapa FO dengan capaian tertinggi sepanjang waktu":

```json
{ "dataset": "fo_achievement", "group_by": ["employee"], "all_time": true,
  "metrics": ["nilai_closing", "closing", "target_nilai_closing", "achievement_pct"],
  "sort": "nilai_closing", "limit": 10 }
```

Hasil (contoh dengan `compare_previous`):

```json
{ "dataset": "ai_usage", "period": { "from": "2026-09", "to": "2026-09" },
  "compared_to": { "from": "2026-08", "to": "2026-08" }, "scope": "all_departments",
  "rows": [ { "department": "Finance", "questions": 15, "questions_previous": 10,
              "questions_delta": 5, "questions_delta_pct": 50.0 } ],
  "suppressed_rows": 1, "truncated": false,
  "notes": ["1 baris disembunyikan karena berisi kurang dari 5 orang (menjaga privasi)."] }
```

Bila nanti jalur B dikerjakan, service yang sama dibungkus rute HTTP (`GET /api/ai/datasets`, `POST /api/ai/query`).

### 4.3 Keamanan (wajib)

- **Whitelist**: dataset, dimensi, metrik, filter, dan nama parameter harus ada di katalog; selain itu ditolak (422). Contoh yang ditolak: `columns: ["email"]`, `group_by: ["email"]`, `filters: {npwp: ...}`.
- **Tanpa SQL dari AI**: hanya parameter terstruktur.
- **Read-only**.
- **Ambang kelompok minimum** (default 5 orang): baris yang dibentuk dari kurang dari 5 orang disembunyikan dan dihitung di `suppressed_rows`. Pada perbandingan periode, baris disembunyikan bila salah satu periode di bawah ambang. Dataset yang memang per orang dan hanya berisi data kerja yang boleh (mis. `fo_achievement`) memasang `min_group_size => 1`.
- **Perhitungan di server**: selisih dan persentase perubahan dihitung server, bukan oleh Gemini.
- Batas periode (24 bulan), batas baris (200), `limit` per permintaan.
- **Otorisasi per user** (4.6).
- **Audit log** untuk setiap permintaan, termasuk yang ditolak: user, dataset, parameter, jumlah baris, baris yang disembunyikan, hasil (`ok`/`denied`). Tidak menyimpan isi data.
- Hasil query diperlakukan sebagai **data**, bukan instruksi. Dataset tidak memuat kolom teks bebas (judul, catatan) yang bisa berisi prompt injection.

### 4.4 Jalur pemanggil

| Jalur | Cara kerja | Status |
|---|---|---|
| **A. Laravel yang memanggil** | Laravel menjalankan loop: Gemini memilih query dari katalog → Laravel mengeksekusi lewat `DataQueryService` dengan user yang login → hasil dikirim kembali ke Gemini → ulangi bila perlu → Gemini merangkum | **Jalur pertama.** Engine sudah ada; integrasi belum |
| B. Gemini memanggil via tool calling | Gateway `nakama-ai` memanggil API HTTP dengan Bearer token + assertion user | Bersyarat, menunggu konfirmasi gateway (0b) |

Karena loop tool calling juga bisa dijalankan di Laravel, keunggulan jalur B hanya soal *di mana* loop berjalan, sementara jalur A jauh lebih sedikit permukaan serangannya.

### 4.5 Perubahan pada AI Center (milestone 4)

- System prompt saat ini menyatakan AI "tidak memiliki akses ke data ERP". Untuk mode "tanya data", diganti dengan instruksi: hanya memakai dataset di katalog yang diberikan; **dataset di luar daftar mungkin ada tetapi user ini tidak punya akses**, jadi jawab "Anda tidak punya akses ke data ini", bukan "datanya tidak ada".
- Tampilkan dataset dan parameter yang dipakai di bawah jawaban agar hasil bisa diperiksa.
- Kuota harian dan limit yang ada tetap berlaku; pertimbangkan hitungan terpisah untuk query data.

### 4.6 Otorisasi per user

1. **Katalog difilter per user** (`DatasetCatalog::describeFor`). Dataset yang tidak boleh dilihat user itu tidak dikirim ke Gemini.
2. **Eksekusi dicek ulang.** Dataset terlarang ditolak dan dicatat sebagai `denied`.
3. **Syarat dasar**: user `active` (suspended selalu ditolak), punya `manage-ai-center`, dan punya permission dataset.
4. **Row-level scope.** Tanpa `ai-data-all-departments.view`, data dibatasi ke `department_id` user; user tanpa departemen tidak mendapat baris. Filter departemen hanya bisa mempersempit, tidak memperluas.

Aturan dicentang per role di halaman Role Management (kartu "AI Center Data"). Super Admin lolos lewat `Gate::before`. Pemberian otomatis lewat `grantLegacyAccess()` dimatikan untuk grup ini.

Opsi lanjutan bila diperlukan: pembatasan posisi jabatan lewat `position_id` pada Employee.

### 4.7 Autentikasi untuk jalur B (disimpan, belum dikerjakan)

Dua lapis, keduanya wajib:

1. **Bearer token tunggal** dari `.env` (`AI_DATA_API_TOKEN`) membuktikan pemanggilnya gateway yang kita percaya. Middleware-nya sudah ada.
2. **Assertion user bertanda tangan** (HMAC-SHA256, secret terpisah `AI_DATA_ASSERTION_SECRET`, masa berlaku pendek ±5 menit) berisi `user_id` dan `conversation_id`, diterbitkan Laravel untuk user yang login.

Syarat keras:

- **Assertion tidak boleh lewat model.** Gateway harus menyisipkan header `X-AI-User-Assertion` dari kodenya sendiri. Assertion tidak boleh ditaruh di prompt atau di argumen tool yang ditulis Gemini, karena bisa bocor lewat prompt injection. Bila gateway tidak bisa, jalur B tidak aman dan tidak dikerjakan.
- Server memverifikasi `conversation_id` memang milik user tersebut, bukan sekadar membaca nilainya.
- Pemeriksaan berurutan: token → tanda tangan dan masa berlaku → user ada, `active`, tidak dihapus → kepemilikan percakapan → otorisasi dataset (4.6).

## 5. Output laporan / presentasi

Gemini hanya menghasilkan teks atau struktur. File `.pptx` harus dibuat Laravel: AI menghasilkan JSON struktur slide, Laravel merendernya. Butuh library baru (mis. PhpPresentation) dan **perlu persetujuan**. Dikerjakan setelah query stabil.

## 6. Milestone

0. ~~Rapikan komponen token~~ — selesai.
0b. **Tanya Mas Faizal** (tidak menghambat jalur A): apakah gateway `nakama-ai` (1) bisa menjalankan tool custom, dan (2) bisa menyisipkan header assertion dari kodenya sendiri tanpa lewat model.
1. ~~Katalog + engine query~~ — selesai (lihat bagian 3).
2b. ~~Otorisasi per user~~ — selesai.
3. **Dataset**: `ai_usage` dan `fo_achievement` selesai. Berikutnya "performa per departemen" setelah metriknya didefinisikan.
4. ~~Integrasi AI Center (jalur A)~~ — selesai dengan AI tiruan; **uji dengan Gemini sungguhan adalah langkah berikutnya.**
5. **Halaman audit log** dan dokumentasi.
6. **Jalur B** (bila 0b terpenuhi): rute HTTP, middleware token, assertion HMAC.
7. **Opsional**: ekspor `.pptx`.

Setiap milestone diakhiri test hijau dan commit terpisah.

## 7. Dataset

| Dataset | Status |
|---|---|
| `ai_usage` — pertanyaan ke AI Center dan pengguna aktif, per bulan/departemen | Selesai |
| `fo_achievement` — capaian Funding Officer per orang: leads, QL, pitching, won, closing, nilai closing, target nilai closing (dari target tahunan tersimpan, dibagi rata per bulan), capaian % | Selesai. Aturan realisasi sama dengan halaman FO Management; bedanya, target "saran" (tebakan) tidak dipakai |
| Performa per departemen (target vs realisasi) | Menunggu definisi metrik |
| Pemakaian Nakama Chat | Usulan |
| Pengguna per departemen (jumlah, tanpa data pribadi) | Usulan |
| Funnel / partnership agregat, tanpa kontak pribadi | Usulan |

Data **finance dan investor ditunda** sampai ditentukan bagian mana yang boleh dikirim ke Gemini.

## 8. Keputusan terbuka

1. **Definisi "performa" per departemen**: metrik apa, dari tabel mana. Ini yang dibutuhkan pertanyaan utama "departemen mana yang kurang perform".
2. Jawaban Mas Faizal soal gateway (0b).
3. Role mana yang diberi akses data dan dataset mana untuk role mana; siapa yang boleh "semua departemen".
4. Apakah data finance dan investor boleh dikirim ke Gemini, dan bagian mana.
5. **Dasar pemrosesan data (UU PDP)** untuk data agregat yang dikirim ke Gemini (pihak ketiga), dicatat sebelum dataset bertambah.
6. Persetujuan menambah library untuk `.pptx`.

## 9. Risiko

| Risiko | Mitigasi |
|---|---|
| Kolom sensitif ikut keluar | Whitelist dimensi/metrik per dataset, tanpa serialisasi model, test bahwa nama/email tidak muncul |
| Agregat kelompok kecil membuka data individu | Ambang kelompok minimum (default 5) |
| **Differencing**: total keseluruhan dikurangi baris yang terlihat bisa menyingkap baris yang disembunyikan | Belum ditangani penuh. Bila dataset berisi data yang lebih sensitif dari jumlah pemakaian, tambahkan pembulatan atau sembunyikan total saat ada baris tersembunyi |
| Prompt injection lewat isi data | Tanpa kolom teks bebas; hasil diperlakukan sebagai data |
| AI salah menghitung | Selisih dan persentase dihitung server; tampilkan dataset dan parameter sumber |
| AI menjawab "data tidak ada" padahal user tidak berhak | Instruksi di system prompt (4.5) |
| User melihat data di luar haknya | Otorisasi di server, katalog difilter per user, scope departemen |
| Akses data terberikan otomatis lewat seeder | `legacy_grant => false` untuk grup `ai-data` |
| Assertion bocor lewat model (jalur B) | Assertion disisipkan gateway, tidak pernah lewat model; tanpa itu jalur B tidak dikerjakan |
| Biaya dan beban query | Batas baris, batas periode, kuota harian |
