# Plan SSO SmartID — Login + Sinkronisasi Akses

## Tujuan

Aplikasi internal lain ("aplikasi klien") memakai **Login with SmartID**:

- Hanya orang yang memenuhi aturan (departemen / posisi / role tertentu, misalnya PMO) yang bisa login.
- Perubahan di SmartID langsung sampai ke aplikasi klien:
  - Resign / disuspend → ter-logout dari semua aplikasi klien.
  - Pindah PMO → Finance → ter-logout dari aplikasi PMO dan tidak bisa login lagi.
  - Pindah **masuk** ke PMO → otomatis mendapat akses.
  - Naik jabatan tapi tetap di PMO → **tidak** di-logout, cukup datanya diperbarui.

```
Admin daftarkan "PMO App" di /tech/sso
  └─ Aturan: hanya Departemen PMO (dan/atau posisi tertentu)

Karyawan PMO   → bisa login ke PMO App ✅
Karyawan lain  → ditolak ❌

Budi resign / dipindah PMO → Finance
  └─ SmartID kabari PMO App: "Budi sudah tidak punya akses"
       └─ PMO App logout Budi saat itu juga
```

## Prinsip penting

1. **Aturan "siapa boleh masuk" diatur oleh admin SmartID di `/tech/sso`**, bukan oleh aplikasi klien. Tim aplikasi
   klien cukup menyampaikan kebutuhannya ("kami butuh orang PMO"). Kontrol tetap di satu tempat.
2. **SmartID tidak bisa me-logout user di aplikasi lain secara langsung.** SmartID mengirim kabar (webhook), lalu
   **aplikasi klien yang me-logout**. Karena itu, memasang penerima webhook adalah **syarat wajib** sebelum aplikasi
   boleh terhubung.
3. **Semua login di aplikasi klien wajib lewat SmartID.** Form login lokal (email + password) di aplikasi klien harus
   dimatikan. Kalau tidak, karyawan yang resign masih bisa masuk lewat situ dan SSO jadi percuma.
4. **Data tidak dihapus.** User yang kehilangan akses cukup dikunci. Data yang pernah dibuatnya tetap ada (sama
   seperti `OffboardUser` di SmartID).
5. **Role internal & aturan jabatan ditentukan aplikasi klien** dari data departemen/jabatan/role yang dikirim
   SmartID. SmartID hanya menjaga gerbang kasar (user aktif + pembatasan role/departemen).

## Catatan & batasan yang perlu disadari

- **Resign tidak selalu manual oleh admin.** Bisa juga dari sync Master SDM (`app:sync-employees-master-sdm`):
  status karyawan berubah → `EmployeeObserver` → `OffboardUser` → akun disuspend → kabar terkirim. Artinya
  kecepatan pencabutan akses **bergantung pada seberapa cepat data HR diperbarui**.
- **Sync Master SDM berjalan di dalam transaksi database.** Webhook baru boleh dikirim **setelah transaksi selesai**
  (`afterCommit`), supaya tidak ada kabar palsu kalau sync gagal/rollback.
- **SmartID menjadi satu-satunya pintu masuk.** Kalau SmartID mati, tidak ada yang bisa login ke aplikasi klien mana
  pun (user yang sudah login tetap jalan). Server SmartID perlu dijaga lebih (monitoring, backup).
- **Kalau aplikasi klien sedang mati** saat kabar dikirim → SmartID retry otomatis, plus jaring pengaman di
  aplikasi klien (cek `/api/me` dan sync harian).

## Kondisi saat ini (sudah ada)

- OAuth2 authorization code via Passport, aplikasi dikelola di `/tech/sso` (`SsoClientManager`, audit log).
- Aplikasi confidential (secret) dan public (PKCE).
- Pembatasan akses per aplikasi berdasarkan **role** dan **departemen** (`OAuthClient::allowsUser`), dicek di layar
  authorize dan di `/api/me`.
- Scope: `profile`, `email`, `department`, `roles`.
- Departemen user tersinkron otomatis dari `Employee` (`EmployeeObserver`); karyawan non-aktif otomatis di-offboard.

**Celah yang ditemukan:**

- `OffboardUser` hanya menghapus session web — token SSO tidak dicabut, refresh token masih bisa dipakai sampai 30 hari.
- Access token berlaku 8 jam (terlalu lama untuk bearer token).
- Belum ada pembatasan berdasarkan **posisi**.
- Belum ada cara memberi tahu aplikasi klien saat ada perubahan.

---

## Fase 1 — Tutup celah keamanan

*Kecil, tanpa migration.* ✅ **Selesai.**

- [x] 1.1 Saat user disuspend **atau dihapus**, cabut semua access token & refresh token SSO-nya. Dipasang di
      `UserObserver` (bukan hanya `OffboardUser`), jadi semua jalur ikut tertangkap: offboarding, sync Master SDM,
      `SyncUserDepartments`, `EmployeeObserver`.
- [x] 1.2 Masa berlaku access token 8 jam → 1 jam (dokumentasi ikut diperbarui).
- [x] 1.3 Rate limit `/oauth/token` — ternyata **sudah ada** dari Passport (`throttle` bawaan, 60/menit per IP).
      Ditambah test supaya tidak hilang.
- [x] 1.4 User pindah departemen → token **hanya** di aplikasi yang tidak lagi mengizinkannya yang dicabut
      (`SsoClientManager::revokeLostAccess`), tercatat di audit sebagai "Sesi user dicabut otomatis (akses hilang)".
- [x] Test untuk semua poin di atas (`tests/Feature/Sso/SsoLoginFlowTest.php`).

**Belum tercakup (masuk Fase 3):** perubahan **role** belum memicu pencabutan token, karena event role Spatie masih
dimatikan. Sementara itu `/api/me` tetap menolak user yang role-nya tidak lagi diizinkan.

**Hasil:** walaupun aplikasi klien belum pasang webhook, user yang kehilangan akses paling lama ±1 jam sudah tidak
bisa memakai tokennya.

## Fase 2 — Data untuk aplikasi klien

✅ **Selesai** (kode & test). Migration data `2026_10_15_103901_allow_client_credentials_for_confidential_oauth_clients`
**belum dijalankan** — menunggu konfirmasi.

**Keputusan:** aturan berdasarkan **jabatan ditentukan aplikasi klien sendiri** (dari data yang dikirim SmartID).
SmartID tetap memegang gerbang kasar: user aktif + pembatasan role/departemen di `/tech/sso`. Tidak ada pengecualian
per orang (minimalis).

- [x] ~~2.1 Pembatasan posisi di `/tech/sso`~~ — dibatalkan, diputuskan di aplikasi klien.
- [x] ~~2.2 Preview anggota di `/tech/sso`~~ — dibatalkan (minimalis).
- [x] 2.3 Scope `department` (label: "Departemen & jabatan") sekarang juga mengirim
      `positions { struktural, fungsional, teknis }` di `/api/me`.
- [x] 2.4 **`GET /api/sso/members`**: daftar user aktif yang boleh login ke aplikasi, paginated, field mengikuti scope
      aplikasi. Pakai token **milik aplikasi** (`client_credentials`, hanya aplikasi confidential); token user ditolak,
      dan token aplikasi tidak bisa dipakai di `/api/me`. Rate limit 30/menit.
- [x] ~~2.5 Pengecualian per orang~~ — dibatalkan (minimalis).
- [x] Test (`tests/Feature/Sso/SsoMembersTest.php`, `SsoLoginFlowTest.php`) & dokumentasi `/tech/documentation`.

**Hasil:** aplikasi PMO menerima departemen & jabatan setiap user dan bisa menarik daftar lengkap orang yang boleh
login, lalu memutuskan sendiri siapa yang diterima.

**Konsekuensi untuk Fase 3:** karena aplikasi klien memutuskan berdasarkan jabatan, perubahan **jabatan** juga harus
dikabarkan (`user.updated`), supaya klien bisa menilai ulang dan me-logout sendiri bila perlu.

## Fase 3 — Webhook (SmartID mengabari otomatis)

*Butuh migration (dijalankan setelah konfirmasi). Bergantung pada Fase 2.*

- [ ] 3.1 Setting **Webhook URL** dan **Webhook secret** per aplikasi di `/tech/sso`.
- [ ] 3.2 Deteksi perubahan: status user (resign/aktif lagi), departemen, posisi, role, profil (nama/email).
      Termasuk perubahan yang datang dari sync Master SDM. Event role Spatie perlu dinyalakan
      (`config/permission.php` → `events_enabled`).
- [ ] 3.3 Untuk **tiap aplikasi**, hitung apakah user masih boleh masuk (sebelum vs sesudah perubahan).
- [ ] 3.4 Kirim kabar **ditandatangani** (HMAC + timestamp, anti-palsu & anti-replay), lewat antrian, **setelah
      transaksi commit**, dengan **retry otomatis** (backoff) kalau aplikasi klien mati.
- [ ] 3.5 Log pengiriman, tombol **Kirim ulang**, dan tombol **Tes webhook** di `/tech/sso`.
- [ ] Test untuk semua poin di atas.

Hanya 3 jenis kabar, supaya aplikasi klien mudah menanganinya:

| Kabar                 | Kapan                                                                 | Aplikasi klien harus       |
|-----------------------|-----------------------------------------------------------------------|----------------------------|
| `user.access_revoked` | Resign, disuspend, pindah ke departemen/posisi yang tidak diizinkan   | **Logout & kunci user**    |
| `user.access_granted` | Baru masuk ke departemen/posisi yang diizinkan, atau aktif lagi       | Buat / buka akun user      |
| `user.updated`        | Nama, email, posisi berubah tapi aksesnya masih boleh                 | Update data user           |

Data yang dikirim mengikuti scope aplikasi (tanpa scope `email`, email tidak ikut terkirim).

**Hasil:** Budi resign / pindah ke Finance → dalam hitungan detik aplikasi PMO mengeluarkannya.

## Fase 4 — Panduan & syarat untuk aplikasi klien

*Lanjutkan panduan copy-paste yang sudah ada (`partials/sso-laravel-guide.blade.php`): format "paste ini di file X"
dengan tombol Salin. Di `/tech/sso` terisi nilai asli (client ID, secret, redirect), di `/tech/documentation` placeholder.*

Sudah ada: `.env`, `config/services.php`, `SmartIdController`, route, tombol login (Blade & Filament).

✅ **Selesai untuk login + sync berkala.** Langkah webhook ditambahkan ke panduan saat Fase 3 selesai.

Format: tanpa teks penjelasan, hanya "file → kode → aksi" dengan tombol Salin, terisi data asli aplikasi. Secret
disensor (`••••`); langkah yang memuat secret tidak bisa disalin sebelum "Tampilkan secret untuk menyalin" diklik
(konfirmasi password + audit). Pilihan tab **Laravel** / **Laravel + Filament**. Semua blok PHP di-parse di test
(`tests/Feature/Sso/SsoGuideTest.php`) supaya hasil copy-paste dijamin valid.

- [x] 4.0 Controller: user dicocokkan berdasarkan **id SmartID** (akun lokal lama dengan email sama ikut ditautkan,
      password lokal diganti acak), profil lengkap disimpan di `smartid_profile` (JSON).
- [x] 4.1 Langkah copy-paste: `.env`, `config/services.php`, migration (`smartid_id`, `smartid_profile`,
      `smartid_locked_at`), trait `HasSmartId` (+ satu baris di `User`), `SmartIdController`, middleware
      `EnsureSmartIdAccess`, command `smartid:sync`, route, jadwal tiap 15 menit, tombol login (Blade / Filament),
      perintah terminal.
  - Aturan jabatan klien: satu method `smartIdAllows(array $profile)` di trait (default `true`).
  - Logout tanpa syarat session driver: middleware mengecek `smartid_locked_at` di setiap request.
  - Sync mengunci user yang hilang dari daftar, **kecuali** daftar kosong (mencegah semua user terkunci karena salah
    konfigurasi).
  - [ ] Penerima webhook (`SmartIdWebhookController`) — menyusul bersama Fase 3.
- [x] ~~4.1b Download starter kit (.zip)~~ — dibatalkan, cukup copy-paste.
- [x] 4.2 Jaring pengaman: `/api/me` saat login + `smartid:sync` tiap 15 menit (jadi harian setelah webhook ada).
- [x] Bug fix: `next_page_url` di `/api/sso/members` sekarang membawa `per_page`.
- [x] Panduan dibagi dua: **Login with SmartID** (wajib, berdiri sendiri) dan **Sync otomatis** (opsional ·
      disarankan: middleware kunci user + command `smartid:sync` + jadwal).
- [x] **Simulasi** di `/tech/sso` (data asli, butuh izin `tech-sso.view`): pilih karyawan → boleh login ✓/✗ +
      alasan + response `/api/me` persis (200/403); "Jalankan simulasi sync" → jumlah user + contoh data.
- [x] **Playground SSO** di `/tech/documentation`: pilih endpoint, scope, dan kondisi user → response contoh.
      Halaman ini sengaja publik (sarana komunikasi dengan developer lain), jadi datanya **contoh** (user fiktif),
      dibangun dengan `SsoUserProfile` + paginator asli sehingga bentuknya dijamin sama dengan API (ditest).
- [ ] 4.3 **Checklist "aplikasi siap terhubung ke SmartID"** — wajib dipenuhi sebelum aplikasi didaftarkan:
  - [ ] Semua langkah panduan di `/tech/sso` dipasang dan `php artisan smartid:sync` berhasil.
  - [ ] Cron `schedule:run` aktif di server aplikasi klien.
  - [ ] Akun lokal lama yang **tidak** terhubung SmartID dihapus/dinonaktifkan (akun yang terhubung otomatis
        kehilangan password lokalnya).
  - [ ] *(Setelah Fase 3)* Penerima webhook terpasang dan lolos **Tes webhook** di `/tech/sso`.
  - [ ] Redirect URI memakai HTTPS (kecuali localhost).

## Urutan

```
Fase 1 ──► Fase 2 ──► Fase 3 ──► Fase 4
(aman)     (aturan)   (sync)     (panduan & syarat)
```

- Fase 1 bisa langsung jalan dan sudah bermanfaat sendiri.
- Fase 3 butuh Fase 2 (webhook perlu tahu aturan "siapa boleh").
- Setiap fase disertai test. Migration hanya dijalankan setelah konfirmasi.

## Keputusan yang masih terbuka

1. ~~Posisi yang dipakai untuk aturan akses~~ → **diputuskan:** aplikasi klien yang menentukan, SmartID mengirim
   ketiga jabatan.
2. ~~Role di dalam aplikasi klien~~ → **diputuskan:** ditentukan aplikasi klien sendiri.
3. Aplikasi klien yang akan terhubung: semuanya **Laravel**, atau ada yang bukan?
4. ~~Pengecualian per orang~~ → **diputuskan:** tidak perlu.

## Di luar cakupan (bisa ditambahkan nanti kalau dibutuhkan)

- OIDC (`id_token`, discovery) — baru perlu kalau menyambungkan tool pihak ketiga (Grafana, Metabase, dll.).
- Halaman "Aplikasi Terhubung" di profil user.
- Paksa login ulang (`prompt=login`) untuk aplikasi sensitif.
- Package Laravel siap pakai untuk aplikasi klien.
