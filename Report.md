```
## dev server:
# sqldump : 
mysqldump -u partnership -pfmRFCNX9bblw68b -h 127.0.0.1 --port=3306 --single-transaction --no-create-info partnership | gzip > partnership_data_only.sql.gz

# hapus :
rm -f partnership_data_only.sql.gz partnership_table.sql

## local server: 
# download sqldump : 
scp partnership@ssh-hosting.smartid.co.id:/home/partnership/partnership-app/partnership_data_only.sql.gz .

ETL command :
php artisan migrate:fresh --seed
php artisan sync:master              # urusans, provinces, regencies, opds, products
php artisan sync:contacts            # kontak dari partnership DB
php artisan sync:cards --year=2026   # migrasi rencana_pitchings 2026 → cards
```

---

## 7:00 PM 07/07/2026 Checklist Migrasi Partnership → Smartboard 

### Infrastructure & Config
- [x] Force HTTPS di production (URL::forceScheme + trustProxies)
- [x] Konfigurasi koneksi `mysql_partnership` di database.php

### ETL Commands
- [x] `sync:master` — sinkron master data (urusans, provinces, regencies, opds, products)
- [x] `sync:contacts` — migrasi kontak dari Partnership DB
- [x] `sync:cards` — migrasi rencana_pitchings → cards (rencana_pitching-centric, enriched prediksis)
  - [x] Year-scoped delete (`--year=2026` hanya hapus & isi ulang 2026)
  - [x] Field mapping: PMO dari `created_by`, status dari prediksi flags, stage dari `status_pelaksanaan`
  - [x] Truncate string >255 char (title, drive_link, sumber_proyeksi)

### Pitching Index
- [x] Filter ownership — user biasa hanya lihat cards miliknya sendiri (`employee_id`)
- [x] Search mencakup nama PMO (`employee.user.name`) selain title/kontak/OPD
- [x] Search pakai `ILIKE` (case-insensitive PostgreSQL)

### Test & Comparison
- [x] Halaman `/partnership/test/pitching` — side-by-side Partnership App vs Smartboard
- [x] Filter PMO & search di comparison page juga pakai `ILIKE`

### Pending
- [ ] Perbaikan matching potensi produk (output_id) — title masih dari teks bebas, belum resolve ke output yang tepat

---

## 2:44 PM 11/07/2026 Checklist Sidebar, Auth Page, & Role-Department Access

### Sidebar & Navbar
- [x] Custom sidebar navy (`#0d2a4f`) pakai logo `smartid.png`, brand "SmartID ERP"
- [x] Background pattern SVG hexagon tile (opacity dinaikkan biar lebih terlihat)
- [x] Navbar desktop baru: judul halaman + dropdown profile (Profile/Settings/Logout) di kanan
- [x] Sidebar collapsible (icon-only), toggle di navbar, expand otomatis saat di-hover
- [x] Icon align kiri saat collapsed (caret submenu disembunyikan biar lurus)
- [x] Highlight menu aktif: gradasi kuning tipis kiri→transparan
- [x] Grup "Funding" (Investors) dipindah ke bawah Finance + submenu (Monthly Achievement, Monthly Conversion, Yearly Analysis, FO Pipeline, Daily Trends, System SOP — placeholder, belum ada route)
- [x] Footer sidebar: hapus profile dropdown (sudah pindah ke navbar), tambah versi & copyright
- [x] Route `/` redirect langsung ke `/login` (welcome page dimatikan)

### Login & Auth Layout
- [x] Toggle show/hide password (icon mata kanan) di halaman login
- [x] Logo `smartid.png` + versi/copyright di semua halaman auth (login, register, forgot/reset password)
- [x] Redesign layout auth jadi split-screen: panel kiri navy+pattern (branding), form kanan borderless & diperbesar (`max-w-md`)

### Role & Department Access (fondasi baru)
- [x] Migration `departments.module_codes` (many-to-many) + `users.department_id`
- [x] `config/modules.php` — registry 5 modul (finance, partnership, products, investors, internal-org)
- [x] `EmployeeObserver` — auto-sync department & permission modul saat Employee dibuat/pindah department
- [x] Command `app:sync-user-departments` — backfill data existing
- [x] Simplifikasi permission: 15 permission granular → 5 permission per-modul (`access-*`)
- [x] Role baru **Direksi** (akses semua modul bisnis, read-only — enforcement UI belum lengkap)
- [x] `routes/web.php` & sidebar disesuaikan ke permission baru
- [x] Redesign halaman `/admin/roles`: checklist permission jadi kartu per-modul (icon+deskripsi), badge "Full Access" (Super Admin) & "Read Only" (Direksi)

### Partnership Data Scoping (PMO / SPV / Admin)
- [x] PMO: hanya lihat data miliknya sendiri (`employee_id` / `pic_employee_id`)
- [x] SPV (role Partnership Admin): lihat data PMO seteam (`region_id` sama)
- [x] Admin (Super Admin): lihat semua data
- [x] Diterapkan konsisten di pitching-index, realisasi-index, dashboard-index (chart & top performer ikut di-scope), contact-index (konsolidasi jadi 1 `$viewerScope`)

### Test
- [x] Seluruh test disesuaikan ke permission baru (70/72 pass — 2 gagal pre-existing, `ilike` tidak didukung SQLite, tidak terkait perubahan)

### Pending
- [ ] Enforcement read-only Direksi di level tombol Add/Edit/Delete tiap halaman Volt (baru fondasi role & permission-nya)
- [ ] UI untuk admin isi mapping `department` → `module_codes` di halaman Departments
- [ ] Submenu Investors (Monthly Achievement dkk.) belum ada route/halaman, masih placeholder `#`

---

## 4:00 PM 11/07/2026 Checklist Error Tracker Panel & Sidebar Polish

### Error Tracker Panel (`/dev`)
- [x] Migration `error_logs` (error_code, type, status, severity, message, stack_trace, file/line/column, url, route_name, user info, context JSON, resolved)
- [x] Model `ErrorLog` + service `App\Services\ErrorTracker` (`capture`, `captureException`, `captureFromBrowser`) — redact `password/token/secret/api_key`, fail-silent
- [x] `App\Http\Controllers\Debug\ErrorLogController` — login/logout session-based (terpisah dari auth utama), guard di setiap aksi
- [x] Halaman index (`/dev/errors`): filter (type, status, severity, resolved, search), stats ringkas, housekeeping mingguan otomatis (cache lock, piggyback on page view)
- [x] Detail per error (`/dev/errors/{code}`), resolve toggle (AJAX), delete satu, purge semua
- [x] Intake publik `POST /api/error-track` (tanpa guard, untuk `window.onerror` di browser)
- [x] Hook otomatis di `bootstrap/app.php` — semua exception HTTP (kecuali validation) tercatat otomatis
- [x] Password via `DEBUG_PAGE_PASSWORD` di `.env` (fallback `debug123`)
- [x] Test `ErrorTrackerTest` (9 test, semua pass) — nemu & fix bug nyata: perbandingan password pakai `Stringable !== string` yang selalu `true` (password benar pun ditolak), diganti `hash_equals`

### Sidebar Polish
- [x] Footer version/copyright sidebar jadi tombol klik menuju `/dev/login` (Error Tracker), hanya tampil untuk role Super Admin
- [x] Navbar desktop: pojok kiri atas dikasih radius, warna background sedikit lebih gelap dari konten (`bg-base-200`) biar beda dari main content
- [x] Sidebar: gradasi warna vertikal (gelap di bawah → cerah di atas)

### Pending
- [ ] Halaman admin isi/edit mapping department → module_codes belum ada (masih manual lewat DB/tinker)

---

