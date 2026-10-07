# Core Module (Fitur Utama ERP Nakama / SmartID Dashboard)

Fitur di luar module bisnis (Funding, Partnership, Finance, Products, Technology). Ini fondasi yang dipakai semua module: autentikasi, akses & role, data master internal, profil, notifikasi, chat, export CSV, dan tools developer. Untuk module Funding lihat [funding-module.md](funding-module.md).

## 1. Autentikasi & Keamanan Akun

Dibangun di atas Laravel Fortify. Halaman auth ada di `resources/views/pages/auth/`.

| Fitur | Keterangan |
|---|---|
| Login | Bisa pakai **email**, **secondary email** (email pribadi), atau **username**. Rate limit 5x/menit per kombinasi identifier+IP. |
| Penolakan berdasarkan status | Login ditolak di layer Fortify (`authenticateUsing`) untuk akun `invited` dan `suspended`, dengan pesan khusus. |
| Middleware `active` | Pengaman tambahan (`EnsureUserAccountIsActive`): user yang statusnya berubah jadi non-aktif otomatis di-logout, termasuk lewat remember-me atau 2FA challenge. |
| Registrasi | Tidak ada self-service. User dibuat lewat **undangan admin** (lihat bagian 3). |
| Set password (undangan) | `/set-password/{token}`: user undangan membuat password sendiri lewat token (broker `invites`). |
| Lupa / reset password | Link reset sekali pakai, ada tombol peek dan **panduan kekuatan password** (`password-strength-field`). |
| Email verification | Route group memakai middleware `verified`. |
| Two-factor authentication (2FA) | Enable/disable, setup modal, dan recovery codes. Rate limit 5x/menit. |
| Passkeys | Daftar dan login dengan passkey (WebAuthn), rate limit 10x/menit. |
| Konfirmasi password | Halaman `/profile/security` dilindungi `password.confirm`. |
| Security headers | `AddSecurityHeaders`: `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, dan HSTS di production. |
| Force HTTPS | Aktif otomatis di production. |

## 2. Role & Permission (Hak Akses)

Memakai `spatie/permission` (trait `HasRoles` di `User`). **Super Admin** otomatis lolos semua gate (`Gate::before`).

### Struktur permission (3 lapis)
1. **Module access** (`access-*`): boleh masuk module atau tidak. Daftar module di `config/modules.php`: Finance, Partnership, Products, Investors/Funding, Internal Org (admin only), Technology.
2. **Resource view** (`{resource}.view`): boleh buka sub-menu tertentu.
3. **Resource action** (`{resource}.{create|update|delete|export|restore}`): aksi spesifik di dalam sub-menu. Definisi lengkap di `config/resource_permissions.php`.

Permission global lain: `view-executive-dashboard`, `manage-talent-okr`, `manage-chat`, `manage-ai-center`.

### Halaman Role Management (`/admin/roles`)
- Daftar role dengan pencarian dan ringkasan.
- Buat, edit, hapus role (ada konfirmasi hapus).
- Editor berbentuk **kartu per module**: aktifkan module, lalu centang sub-menu dan aksi (matriks CRUD).
- Dua seksi: **Application Modules** dan **Data Master**; ada checkbox "Pilih Semua" dan pencarian module/sub-menu.
- Role di-assign saat admin mengundang atau mengedit user, **bukan** saat user mendaftar.

### Role bawaan (seeder)
Super Admin, Internal Org Admin, Partnership Admin, PMO, Product Admin, Investor Admin, Funding Officer, Technology, Direksi (read-only di banyak module). Role lain bisa dibuat lewat Role Management.

## 3. Data Master Internal (module Internal Org)

Hanya untuk yang punya `access-internal-org` (`admin_only`).

### Departments (`/departments`)
- CRUD departemen dengan **kode** dan **warna** (dipakai badge `department-code`).
- **Mapping Department → Module** (tabel `department_module`): module yang boleh dipakai satu departemen. Sidebar dan KPI user mengikuti mapping ini.

### Positions (`/positions`)
- CRUD posisi dengan tipe posisi (fungsional, struktural, teknis).

### Employees / Users (`/employees`)
Satu halaman untuk data karyawan sekaligus akun login.
- Tambah, edit, hapus (soft delete), dan **Pulihkan** dari "Data Terhapus".
- Field: nama, email, departemen, jabatan fungsional/struktural/teknis, status akun, **status kepegawaian** (Active, Resign, Discontinued, PHK, Inactive) lengkap dengan tanggal dan keterangan nonaktif.
- **Undang user** (`InviteUser`): akun dibuat berstatus `invited`, email undangan berisi link set password; ada tombol **Kirim Ulang**.
- Status akun: `active`, `invited`, `suspended`. Tombol Aktifkan/Nonaktifkan.
- Detail karyawan menampilkan siapa yang mengundang. Filter departemen dan statistik status.
- Export CSV.
- Sinkron data SDM: command `SyncEmployeesFromMasterSdm` (CSV Master SDM, merge data lama + baru).

## 4. Data Master Pipeline & Produk

Untuk yang punya `access-partnership` atau `access-products`:

| Halaman | Route |
|---|---|
| Region | `/region` |
| OPD | `/opd` |
| Urusan | `/urusan` |
| Contact Positions | `/contact-positions` |
| Products | `/products` |

Data wilayah (Province dan Regency) tersedia lewat API. Halaman OPD dan Contact (Partnership) bisa **export CSV**.

## 5. Profil & Akun Pribadi

| Halaman | Route | Isi |
|---|---|---|
| My Profile | `/profile` | Data diri, foto profil (avatar), data kepegawaian, ganti password, KPI Funding (heatmap target vs realisasi, hanya jika departemen punya module investors). |
| Security | `/profile/security` | 2FA, passkeys, dan hapus akun (butuh konfirmasi password). |
| Profil user lain | `/users/{user}` | Read-only: identitas, role, info kepegawaian. Tanpa data sensitif (NIK, NPWP, tgl lahir, status nikah). Membuka diri sendiri redirect ke `/profile`. |

Tambahan: **dark mode toggle**, status kehadiran chat (online/away/busy/offline).

## 6. Notifikasi (`/notifications`)
- Notifikasi in-app (tabel `notifications`) dengan **bell** di navbar.
- Realtime lewat Laravel Reverb/Echo.
- Sumber: Funding (deal closing/akad/lost), Partnership, dan undangan user.
- Penerima diatur lewat `NotificationRecipients`.

## 7. Chat Internal (`/chat`)
Permission `manage-chat`.
- Percakapan 1-on-1 antar user, daftar kontak dan modal "Chat Baru".
- **Realtime** (Reverb + Echo), jumlah unread, pemisah tanggal.
- Kirim **lampiran** dengan preview gambar (disimpan di S3 `chat-attachments/`).
- **Presence**: Online, Away (Pergi Sebentar), Busy (Sibuk), Offline.
- Klik kanan kontak untuk "Lihat Profil".

## 8. Export CSV Dinamis
- Registry di `AppServiceProvider::registerCsvExports()` (`CsvExportRegistry`). Menambah halaman exportable cukup satu `register()`.
- Route tunggal `/export/csv/{slug}` (`CsvExportController`), dicek permission per definisi.
- Streaming untuk data besar, dengan **popup progress** ala download browser.
- Sudah terdaftar (6 slug): `opd`, `contacts` (Partnership), `contact-positions`, `urusan`, `products`, `employees`. Halaman Funding belum terdaftar.

## 9. API Publik Lookup
`routes/api.php`, dokumentasi di `/documentation`:
- `GET /api/provinces`
- `GET /api/regencies`
- `GET /api/opds` (filter provinsi, kabupaten, keyword)

Dipakai untuk cascading dropdown Provinsi → Kabupaten/Kota → OPD.

## 10. Tools Developer & Operasional (khusus Super Admin)
Semua di bawah `/dev`, dilindungi `dev.super-admin` (bukan Super Admin dapat 404, bukan 403) dan password panel kedua (`dev.session`).

- **Error Tracker** (`/dev/errors`): daftar error dengan kode, detail, resolve, hapus, purge. Menangkap error server dan JS browser (`POST /api/error-track`).
- **Force Login** (`/dev/force-login`): Super Admin login sebagai user lain untuk debugging.
- **Artisan commands sinkronisasi**: `sync:master`, `sync:contacts`, `sync:cards`, `SyncUserDepartments`, `SyncFoFunnelStageDates`, `ReassignOrphanedFoRecords`.

## 11. Layout & Komponen Bersama
- Sidebar dinamis per permission dan mapping department-module, plus navbar (user menu, notifikasi, dark mode, tombol export CSV).
- Stack: Laravel 13, Livewire 4 + Volt, Mary UI/DaisyUI, Tailwind 4. Flux UI sudah dicabut.
- Komponen reusable: `stat-card`, `currency-abbr`, `user-avatar`, `avatar-initial`, `department-code`, `position-badge`, `csv-export-button`, `password-strength-field`, `AccessibleSelect`.
- Halaman `dashboard` sebagai landing setelah login, dan `/` redirect ke login.

## 12. Placeholder (belum dibangun)
Route, permission, dan halaman sudah ada, tapi isinya masih deskripsi "Fitur Utama yang Akan Hadir":
- **Executive Dashboard** (`/executive-dashboard`, `view-executive-dashboard`), BI overview.
- **Leaderboard / Talent OKR** (`/leaderboard`, `manage-talent-okr`).
- **Nakama AI Center** (`/ai-center`, `manage-ai-center`).

Module Finance (`/finance/*`: intake, reconciliation, disbursements, clearance archive) juga masih berupa komponen Livewire kosong (±13 baris per halaman) dengan permission `view` saja.

## Catatan
- Disusun dari route, config, provider, enum, dan komponen di repo. Detail perilaku per halaman bisa lebih rinci di kodenya.
- Pada `routes/web.php` ada route test `/partnership/test/pitching` (perbandingan Partnership App vs Nakama) yang sifatnya sementara.
