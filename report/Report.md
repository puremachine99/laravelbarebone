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

## Ringkasan Modul Partnership & Data Master

**Status:** Modul Partnership di Nakama Dashboard sudah bisa dipakai — data pitching, kontak, dan data master (wilayah, urusan, produk, OPD) sudah bisa ditarik dari sistem Partnership lama ke Nakama Dashboard, lengkap dengan pembagian akses sesuai role (PMO hanya lihat data sendiri, SPV lihat data timnya, Admin lihat semua).

**Bottleneck:** Kendala utama ada di proses ETL (proses tarik & bersih-bersih data dari sistem Partnership lama ke Nakama Dashboard). Data lama formatnya tidak selalu rapi, jadi setiap migrasi butuh proses cek & penyesuaian manual, bukan sekadar copy-paste otomatis.

**Rencana migrasi:** Karena proses ETL sudah teruji, data pitching partnership bisa mulai dimigrasikan penuh dari **awal Juni 2026** ke Nakama Dashboard.

---

## 7:00 PM 07/07/2026 Checklist Migrasi Partnership → Nakama Dashboard 

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
- [x] Halaman `/partnership/test/pitching` — side-by-side Partnership App vs Nakama Dashboard
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

## 10:47 PM 14/07/2026 Checklist Modul Funding (FO Kontak & Funnel)

### FO Kontak
- [x] Halaman baru "Contacts" buat Funding Officer nyatetin data kontak calon investor (nama, profesi, no HP, catatan)
- [x] FO biasa cuma bisa lihat & pegang kontak miliknya sendiri; Admin FO bisa lihat & atur punya semua orang
- [x] 5 orang FO (dari data existing) langsung dikasih role & akses yang pas ke menu Funding ini, sisanya menu lain otomatis kesembunyi buat mereka

### Menu Funnel (papan Kanban)
- [x] Menu "Funnel" — papan visual drag-and-drop buat mindahin kontak antar tahap: Leads → QL → Pitching → Follow Up → Won/Lost
- [x] Bisa gonta-ganti tampilan Kanban (kartu) atau Table (daftar biasa)
- [x] Tiap kolom nunjukin total nilai proyeksi & jumlah kartu di situ
- [x] Nambah lead baru: tinggal cari kontak yang udah ada, isi target closing & sumbernya (Murni/EXPFO/Referral/SIDS)
- [x] Pas kartu digeser ke kolom Pitching atau Follow Up, otomatis muncul form buat nyatet tanggal & catatan kegiatannya (ada tombol "Hari Ini" biar gak perlu ketik manual)
- [x] Follow Up bisa dicatat berkali-kali — ada badge angka di kartu, diklik langsung muncul panel riwayat semua follow up yang pernah dicatat
- [x] Kolom pencarian di atas — bisa cari berdasarkan nama, profesi, no HP, nama FO, atau sumbernya
- [x] Filter by FO (khusus Admin), Bulan, dan Tahun

### Data Beneran
- [x] Data funnel asli dari 3 Funding Officer (397 data kontak & prospek) berhasil dipindahin ke sistem, lengkap sama riwayat pitching & follow up-nya
- [x] Sekali `migrate:fresh --seed`, semua ini (role, kontak, data funnel) otomatis kebentuk ulang — gak perlu setup manual lagi

### Pending
- [ ] Data "Won" (closing) belum ada form khusus buat nyatet nominal investasi final, jangka waktu, & bagi hasil — masih ikutan data proyeksi
- [ ] Belum ada laporan/rekap performa bulanan per FO (baru sebatas papan kerja harian)

---

## 15/07/2026 Checklist Kolom Akad & Upload Dokumen

### Papan Funnel
- [x] Kartu di tiap kolom sekarang urut dari yang paling baru ditambah/diubah
- [x] Kolom baru **"Akad"** di paling ujung — nandain lead yang udah bener-bener tanda tangan kontrak, bukan cuma "Won" doang
- [x] Cuma Admin FO / Super Admin yang bisa mindahin kartu ke Akad (FO biasa tetep bisa lihat, cuma gak bisa geser ke situ)

### Proses Akad
- [x] Pas kartu digeser ke kolom Akad, muncul form: tanggal akad, nominal investasi final, dan upload dokumen akad
- [x] Dokumen yang diupload (PDF/DOC/DOCX, maks 5MB) langsung tersimpan ke cloud storage (bucket MinIO kantor), bukan di server aplikasi
- [x] Dokumen yang udah keupload muncul sebagai link "Lihat" di kartu & tabel, bisa langsung dibuka

### Perbaikan Bug
- [x] Modal Akad sempat nutup sendiri pas lagi diisi — dibenerin
- [x] Pilih file sempat nunjukin semua jenis file (harusnya cuma PDF/DOC/DOCX) — dibenerin, sekarang sudah difilter
- [x] Pesan "wajib diisi" yang nyangkut padahal file udah dipilih — dibenerin, sekarang otomatis ke-refresh
- [x] Link lihat dokumen sempat 404 (nyasar ke domain aplikasi, bukan ke bucket) — dibenerin, konfigurasi cloud storage-nya dirapikan

---

## 11:00 PM 15/07/2026 Checklist Kolom Won/Lost, Performa Halaman, & Keamanan Dokumen

### Kolom Won & Lost — sekarang ada form-nya juga
- [x] Kartu digeser ke kolom **Won**: muncul form tanggal closing + nominal closing beneran (bukan cuma angka proyeksi lagi)
- [x] Kartu digeser ke kolom **Lost**: muncul form tanggal + alasan gagalnya (Lost Reason), langsung kelihatan di kartu & tabel
- [x] Kartu yang udah Won/Akad sekarang nampilin angka closing yang sebenarnya (bukan proyeksi awal) — badge-nya dikasih warna hijau biar beda
- [x] Tanggal closing di form Won otomatis keisi hari ini, gak perlu klik "Hari Ini" manual lagi

### Halaman Funnel dibikin lebih ringan & cepat
Halaman ini dirasa lemot pas dibuka, jadi dibedah & dibenerin 5 titik yang paling berat:
- [x] Filter (bulan/tahun/cari) sekarang disaring langsung di database, bukan ditarik semua dulu baru dibuang — jauh lebih cepat kalau datanya makin banyak
- [x] Hitungan jumlah "follow up" di tiap kartu sekarang dihitung database, bukan narik semua data histori dulu ke aplikasi
- [x] Dropdown pilihan "Tahun" di filter sekarang juga dihitung langsung di database
- [x] Tampilan Kanban & Table gak lagi dirender dua-duanya sekaligus di belakang layar (yang keliatan doang yang beneran diproses) — dampaknya klik ganti tampilan jadi ada jeda singkat, tapi halamannya jauh lebih ringan
- [x] Beberapa data yang jarang berubah (daftar FO, daftar tahun) sekarang disimpan sementara (cache 1 menit) biar gak dihitung ulang tiap kali klak-klik di halaman

### Keamanan Dokumen Akad (bucket MinIO)
- [x] Link "Lihat" dokumen akad sempat error Access Denied — ternyata bucket penyimpanannya emang sengaja diset privat (supaya dokumen kontrak gak sembarangan bisa diakses orang)
- [x] Solusinya: link dokumen sekarang pakai "kunci sementara" (presigned URL, berlaku 15 menit) — jadi tetap aman (bucket tetap privat, gak diubah), tapi tetap bisa dibuka pas dibutuhkan

### Perbaikan Tampilan
- [x] Scroll ke samping di papan Kanban sempat gak jalan di beberapa laptop layar kecil — ternyata ada bug tata letak halaman (bukan cuma di halaman Funnel, ini bug bawaan layout situs), sudah dibenerin
- [x] Papan Kanban dikasih tinggi maksimal biar scroll-nya selalu kejangkau tanpa harus scroll halaman ke bawah dulu

### Pending
- [ ] Belum ada laporan/rekap performa bulanan per FO (baru sebatas papan kerja harian)

---

## 20/07/2026 Checklist Kelola User, Target FO, & Rapiin Tampilan Angka Uang

### Kelola User (halaman Admin > Users)
- Sekarang admin bisa Tambah, Edit, dan Hapus akun user langsung dari satu halaman — sebelumnya cuma bisa lihat data & atur peran doang
- Satu form aja buat isi nama, email, password, departemen, sampai centang peran/akses — gak perlu bolak-balik halaman
- Ada konfirmasi dulu sebelum hapus user biar gak kepencet gak sengaja, dan user gak bisa hapus akunnya sendiri

### Atur Target Funding Officer (halaman Management FO)
- Target tahunan tiap FO sekarang beneran tersimpan — sebelumnya cuma angka contoh doang yang ilang kalau halaman di-refresh
- Kalau admin belum pernah isi target buat FO tertentu, sistem otomatis kasih usulan angka berdasarkan performa asli FO itu sendiri (bukan angka ngasal lagi)
- Ada tombol buat balikin ke usulan otomatis kalau target manual mau dihapus
- Tambah rekap performa 6 bulan terakhir tiap FO, dan ranking semua FO tahun ini berdasarkan pencapaian target
- Tiap kali target diubah, sekarang otomatis kecatat: siapa yang ubah, target siapa, dari angka berapa ke berapa — bisa dicek riwayatnya di halaman yang sama

### Rapiin Tampilan Angka Uang
- Input nominal (di halaman Funnel & Atur Target) sekarang otomatis dikasih titik pemisah ribuan pas diketik, jadi gampang dibaca — misal ketik "250000000" langsung kebaca "250.000.000"
- Angka yang disingkat (misal "3,5 M") sekarang bisa di-hover buat lihat angka pastinya (misal "Rp 3.500.000.000")
- Ketemu & benerin bug kecil: sistem sempat salah baca angka negatif jadi positif di belakang layar — sudah dibenerin sebelum sempat kepakai

---

## 23/07/2026 Checklist API OPD Lookup (buat Dropdown Provinsi/Kabupaten/OPD)

### API Baru
- 3 endpoint baru buat aplikasi lain yang butuh dropdown berjenjang Provinsi → Kabupaten/Kota → OPD, tanpa perlu akses database Nakama Dashboard langsung
- `GET /api/provinces` — ambil daftar provinsi, bisa dicari pakai kata kunci
- `GET /api/regencies?provinsi=...` — ambil daftar kabupaten/kota dalam satu provinsi (nama provinsi wajib diisi)
- `GET /api/opds?provinsi=...` — ambil daftar OPD dalam satu provinsi, bisa dipersempit lagi pakai kabupaten & kata kunci nama OPD
- Pencarian nama gak perlu persis sama (cukup sebagian kata), dan khusus pencarian provinsi udah dipastikan gak peduli huruf besar/kecil
- Hasil pencarian langsung dikasih semua sekaligus (gak dipotong per halaman) biar gampang buat isi dropdown

### Dokumentasi buat Developer
- Halaman baru `/documentation` — panduan teknis lengkap buat developer aplikasi lain yang mau pakai API ini
- Isinya: penjelasan tiap endpoint, contoh permintaan & hasilnya, sampai contoh kode JavaScript siap pakai buat alur dropdown berjenjang

### Test
- Semua endpoint & halaman dokumentasi sudah ada test otomatisnya (11 test, semua pass)

---

## 28/07/2026 Checklist Dashboard Harian, Capaian Bulanan, & Konversi Bulanan (Modul Funding)

### Dashboard Harian
- Halaman utama Funding (sebelumnya cuma placeholder kosong) sekarang jadi dashboard beneran — realisasi hari ini dihitung otomatis dari data Funnel, gak ada input manual sama sekali
- 5 kartu ringkasan: Lead Baru, Qualified Lead, Pitching, Closing, dan Nilai Closing — tiap kartu nunjukin capaian hari ini vs target, plus status warna (Hijau/Kuning/Merah)
- Tabel "Realisasi per FO" — rincian capaian tiap Funding Officer hari ini
- Tren 14 hari terakhir (grafik Lead Baru vs Closing) dan Papan Peringkat FO (ranking nilai closing sepanjang waktu)
- Fitur "Masuk sebagai" — Admin bisa lihat data gabungan semua FO atau pilih satu FO tertentu buat dicek sendirian; FO biasa otomatis cuma lihat datanya sendiri

### Capaian Bulanan
- Halaman baru buat rekap performa per bulan per FO (Lead Baru, QL, Pitching, Follow Up, Closing, Nilai Closing) — bisa pilih bulan & tahun mana yang mau dilihat
- Ada baris "Total Tim" di bawah tabel, dan grafik tren 12 bulan biar kelihatan naik-turunnya performa sepanjang tahun

### Konversi Bulanan
- Halaman baru buat lihat funnel konversi tim — dari Lead sampai Closing, per bulan berjalan (Januari s.d. bulan ini)
- Tiap bulan dikasih status (≥ rata-rata / mendekati / di bawah) dibandingkan rata-rata tahun berjalan, bukan angka tetap
- Baris Total di bawah tabel dihitung kumulatif (bukan rata-rata dari persen tiap bulan) biar gak salah baca
- Ada ringkasan: Konversi Kumulatif, Bulan Terbaik, Bulan Terlemah, dan Bottleneck (tahap funnel yang paling sering macet)
- Grafik Funnel Kumulatif (Lead Baru → QL → Pitching → Closing) tahun berjalan

### Rapiin Sidebar & Komponen
- Menu placeholder yang belum ada halamannya (Yearly Analysis, Daily Trends, System SOP) dihapus dari sidebar Funding
- Icon inisial nama FO di kolom "PIC (FO)" pada halaman Contacts sempat gak center & keliatan miring — dibenerin, sekaligus dijadiin komponen biar bisa dipakai ulang di tempat lain

### Test
- Ketiga halaman baru sudah ada test otomatisnya masing-masing (30 test baru, semua pass)

---


