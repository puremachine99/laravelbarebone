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

## 03/08/2026 Checklist Seeder FO Dinamis & Tombol Submit Target FO

### Seeder Funding Officer — gak hardcode nama orang lagi
- Sebelumnya daftar FO/Admin FO di-hardcode pakai `user_id` orang tertentu; sekarang otomatis dideteksi dari data HR asli (departemen "Departemen Finance" + jabatan hasil import CSV) — persis pola yang udah dipakai buat departemen Revenue and Growth
- Jadi kalau ada FO baru masuk lewat data HR berikutnya, otomatis ke-assign role & posisinya tanpa perlu edit kode seeder lagi

### Halaman Management FO — sekarang ada tombol Simpan
- Input target tahunan (Leads, QL, Pitching, Closing, Nilai Closing) gak lagi otomatis tersimpan begitu diketik — sekarang harus klik tombol **"Simpan Target"** dulu, biar gak kepencet/salah ketik langsung berubah
- Yang beneran tersimpan & kecatat di riwayat cuma metrik yang nilainya emang diubah — klik Simpan tanpa ubah apa-apa gak bikin riwayat baru yang gak perlu
- FO biasa sekarang **boleh buka** halaman Management FO (sebelumnya kena blokir 403 total) — tapi cuma bisa lihat data, gak ada form/tombol edit sama sekali; yang bisa ubah target tetap cuma Admin FO & Super Admin
- Proteksi ubah/reset target juga ditambahin di level backend (bukan cuma disembunyikan di tampilan), jadi FO biasa gak bisa akalin lewat console browser

### Audit Hak Akses (RBAC)
- Ditulis dokumen `rbac_implementation.md` — hasil audit semua tempat di kode yang ngecek role/permission, buat jawab pertanyaan "apakah hak akses di project ini hardcode atau dinamis?"
- Kesimpulan: assign role ke user itu sudah dinamis penuh lewat halaman **Manage Users** & **Manage Roles** yang udah ada — tinggal centang role di situ buat kasih/cabut akses, gak perlu ubah kode

### Test
- Test lama (`ManagementFoTest`) disesuaikan + ditambah test baru buat tombol submit & proteksi role FO biasa

---

## 05–08/08/2026 Checklist Sistem Notifikasi In-App (Partnership & Funding)

### Fondasi Notifikasi
- Tabel `notifications` baru (fitur bawaan Laravel) — notifikasi disimpan per user, ada status baca/belum baca
- Icon lonceng di navbar (mobile & desktop) — nunjukin badge jumlah notifikasi belum dibaca, update sendiri tiap 15 detik tanpa perlu refresh manual
- Halaman baru **"Semua Notifikasi"** (`/notifications`) — daftar lengkap, ada tab Semua/Belum Dibaca, tombol "Tandai semua dibaca"
- Klik satu notifikasi otomatis tandai sudah dibaca sekaligus langsung diarahkan ke data yang relevan (card/prospek yang dimaksud)
- Belum pakai realtime/websocket (Reverb) — masih model polling ringan, cukup buat kebutuhan sekarang; realtime baru akan digarap barengan kalau fitur Chat mulai dikerjakan

### Notifikasi Partnership
- PMO closing deal (status jadi **Won**) otomatis kirim notifikasi ke Manager terkait
- Manager verifikasi prospek di halaman Realisasi, notifikasinya balik ke PMO yang punya prospek itu

### Notifikasi Funding
- FO closing deal investasi otomatis kirim notifikasi — tapi ke **Admin FO** (Investor Admin), bukan ke Manager Partnership
- Sempat kepikiran nyamain logic "siapa yang dinotif" antara Partnership & Funding karena user-nya sama-sama internal, tapi ternyata dua departemen ini punya alur approval yang beda total (Partnership punya jenjang Manager, Funding enggak) — dipisah biar notifikasi gak nyasar ke orang yang gak ada hubungannya sama modul itu

### Perbaikan & Temuan selama pengerjaan
- Navbar avatar user disederhanakan — cuma ikon bulat inisial di navbar, nama lengkap/email/jabatan dipindah ke dalam dropdown-nya aja
- Ketemu bug aneh di compiler Livewire Volt: bikin objek baru (`new ...`) langsung di dalam komponen Volt tertentu bikin halaman error total — disiasati dengan mindahin pembuatan notifikasi ke method khusus di dalam class notifikasinya sendiri
- Sempat salah taruh perubahan navbar/lonceng di file layout lama sisa starter-kit yang ternyata gak kepake sama sekali (dead code) — makanya sempat gak muncul di localhost, sudah dipindah ke file layout yang beneran aktif

### Test
- Test baru buat notifikasi Partnership & Funding, termasuk test khusus mastiin notifikasi gak nyasar lintas departemen — semua pass
- Full test suite: 193/195 pass (2 gagal pre-existing, `ilike` gak didukung SQLite, gak berkaitan sama kerjaan ini)

### Pending
- Realtime notifikasi (Reverb/websocket) — nyusul bareng fitur Chat
- 2 test lama yang gagal karena `ilike` (khusus PostgreSQL) belum dibikin portable ke SQLite

---

## 10/08/2026 Checklist Notifikasi Detail, Stage "Closing" Baru, Follow Up Audit Trail, & Perbaikan UI Funnel

### Notifikasi Funding — lebih detail
- Notifikasi ke Admin FO sekarang dipicu pas lead-nya bener-bener **Closing** (uang masuk PT), bukan pas baru **Won** (komitmen doang) — biar gak ada notif "closing" padahal duitnya belum cair
- Notifikasi baru: lead yang di-set **Lost** juga otomatis ngirim notif ke Admin FO
- Notifikasi perubahan target FO sekarang nunjukin detail lengkap per metrik — misal "Leads 194 → 195, QL 126 → 100", bukan cuma "target kamu diubah" doang

### Stage Baru: "Closing" (dipisah dari "Won")
- Alur funnel Funding sekarang jadi: Leads → QL → Pitching → Follow Up → **Won** → Lost → **Closing** → Akad
- **Won** sekarang cuma nandain komitmen verbal calon investor (tanggal + nominal komitmen, kolom baru `nominal_won`) — belum tentu duitnya udah cair
- **Closing** (stage baru) baru nandain uang investor beneran udah masuk ke perusahaan (nominal & tanggal final, terpisah dari nominal Won) — biar kalau ada selisih antara nominal janji vs nominal beneran, ketauan (ada badge kuning kecil "Komit: Rp X" di kartu kalau angkanya beda)
- Form Won & Closing sekarang **wajib diisi** (tanggal + nominal) dan **gak bisa ditutup** sebelum disimpan — jadi gak ada lagi kartu yang "nyangkut" di kolom Won/Closing tanpa data lengkap
- Semua laporan yang sebelumnya nganggep "Won = Closing" (Dashboard Harian, Capaian Bulanan, Konversi Bulanan, Management FO) udah dirapiin — sekarang keduanya dihitung & ditampilin terpisah, termasuk kolom Won baru di tiap tabel/grafik
- Data lama yang udah kadung ada di stage Won (dari sebelum fitur ini ada) dibiarkan apa adanya, gak dipaksa dimigrasiin

### Follow Up — bisa Edit, ada Riwayat Perubahan, & bisa Dihapus/Dipulihkan
- Tiap catatan Follow Up di panel riwayat sekarang bisa **diedit** (ubah tanggal/catatan)
- Setiap perubahan otomatis kecatat ke riwayat perubahan tersendiri (siapa yang ubah, kapan, dari apa ke apa) — bisa dicek lewat tombol kecil di tiap catatan, muncul di popup terurut dari yang terbaru
- Follow Up juga sekarang bisa **dihapus** (ada konfirmasi dulu biar gak kepencet gak sengaja) dan **dipulihkan lagi** lewat tombol "Lihat Terhapus" — bukan hilang permanen

### Kontak yang Dihapus
- Lead yang kontaknya udah dihapus sekarang otomatis gak nongol lagi di kolom **Leads** (kartu tanpa identitas gak ada gunanya di tahap paling awal)
- Lead yang udah lewat tahap Leads (Won, Closing, dst.) tetap ditampilkan apa adanya walau kontaknya dihapus — datanya tetap kesimpen utuh

### Halaman Contacts — Masking & Anti Double-Klik
- Input No HP di form Tambah/Edit Kontak sekarang otomatis dibatesin cuma angka (9-15 digit), langsung divalidasi di server juga
- Tombol Simpan otomatis nge-disable diri sendiri (dikasih loading spinner) selama proses simpan berjalan — jaga-jaga biar gak numpuk data ganda kalau user keburu klik berkali-kali

### Rapiin Tampilan Kanban
- Warna badge nominal di tiap kartu sekarang ngikutin warna stage-nya sendiri (misal kartu di Leads warnanya abu-abu, di Won ijo, dst.) — sebelumnya cuma 3 warna tetap
- Badge "Nx Follow Up" dipindah ke tengah, di antara tanggal & sumber (Murni/Referral/dst.), warnanya dinetralin, dan kursornya jadi pointer biar jelas itu bisa diklik
- Background tiap kolom Kanban sekarang ngikutin tinggi kartu-kartu di kolom itu sendiri — sebelumnya ikut ke-stretch sama kolom lain yang lebih tinggi, jadi kolom kosong/pendek keliatan aneh (background kepanjangan gak jelas)

### Bug Serius yang Ketemu & Dibenerin
- Nemu lagi bug di compiler Livewire Volt (mirip temuan sesi sebelumnya): nulis kata "new" apa adanya (termasuk di dalam attribute HTML kayak `new Date()`) di file komponen Volt bisa bikin komponen itu gagal total pas cache-nya di-refresh (halaman 500) — udah dibenerin, dan aturannya sekarang: semua pemanggilan tanggal hari ini pakai method Alpine bernama (`setWonToday()`, dst.), bukan nulis `new Date()` langsung di HTML
- Modal Won/Closing sempet nutup sendiri pas user baru ngetik 1 digit nominal — ternyata modalnya kurang atribut `wire:ignore.self` (modal Akad yang lama udah bener duluan), jadi tiap kali ada respon server, modalnya ke-refresh paksa & keitung "ketutup". Udah disamain polanya kayak modal Akad

### Test
- Semua fitur di atas udah ada test otomatisnya (nambah puluhan test baru di `FoFunnelTest`, `ManagementFoTest`, `InvestorsDashboardTest`, `MonthlyAchievementTest`, `MonthlyConversionTest`)
- Full test suite: 210/212 pass (2 gagal pre-existing, `ilike` gak didukung SQLite, gak berkaitan sama kerjaan sesi ini)

### Pending
- Enforcement read-only Direksi di level tombol Add/Edit/Delete (masih pending dari sesi-sesi sebelumnya)
- Realtime notifikasi (Reverb/websocket) — masih nyusul bareng fitur Chat
- Belum ada opsi buat cegah hapus kontak yang masih punya funnel aktif di stage lain (Won/Closing/dst.) — untuk sekarang cuma disembunyikan dari Leads, stage lain tetap tampil walau kontaknya kehapus

---

## 10/08/2026 (Sesi 2) Checklist Keamanan Login, Konsolidasi Settings → Profile, & Foto Profil

### Keamanan Login (modul umum, bukan ERP)
- [x] Validasi format email diperketat (`email:rfc,strict`) — email tanpa domain lengkap kayak `user@domain` (tanpa `.com`/`.id`/dst.) sekarang ditolak, sebelumnya lolos begitu aja. Diterapkan di form Login & form Registrasi/Edit Profil
- [x] Pesan gagal login dicek — ternyata Laravel Fortify udah generik dari sononya ("These credentials do not match our records."), gak bocorin apakah yang salah itu email atau password-nya. Dikonfirmasi & dikunci pakai test biar gak berubah gak sengaja ke depannya
- [x] Tombol "Log in" sekarang otomatis nonaktif + munculin spinner pas diklik, biar gak numpuk request kalau user keburu klik berkali-kali

### Sidebar & Branding
- [x] Role user yang lagi login sekarang ditampilin — badge kecil emas di brand header sidebar (di bawah logo "SmartID ERP") dan di dropdown profil user
- [x] Tagline "Enterprise Resource Planner" dirapiin jadi satu baris penuh di bawah logo, ngikutin lebar sidebar
- [x] Dropdown user di navbar (mobile & desktop) sekarang lebarnya otomatis ngikutin panjang nama — nama pendek tetep rapi, nama panjang gak lagi kepotong aneh

### Konsolidasi Halaman Settings → Profile
- [x] Halaman Settings lama (Profile, Security, Appearance — tiga halaman terpisah di `/settings/*`) dihapus total
- [x] Digabung jadi satu grup halaman baru: `/profile` (info akun, foto, info kepegawaian, ganti tema, hapus akun — semua dalam satu halaman pakai tab) dan `/profile/security` (ganti password, 2FA, passkeys — sengaja dipisah rute sendiri biar tetap kena proteksi "konfirmasi ulang password" yang emang udah ada dari sononya, gak ikut dilonggarin)
- [x] Menu dropdown di sidebar dirapiin — sebelumnya ada 2 menu ("My Profile" & "Settings") yang nyasar ke halaman yang sama, sekarang cuma nyisain satu menu "My Profile" yang jelas ke halaman baru ini

### Foto Profil
- [x] User bisa upload foto profil sendiri — klik icon pensil kecil di pojok foto, langsung kebuka pemilih file, dan otomatis keupload begitu file dipilih (gak perlu klik tombol "Upload" terpisah lagi)
- [x] Foto disimpan di cloud storage yang sama dipakai buat dokumen Akad (bucket MinIO), bukan di server aplikasi
- [x] Foto yang udah diupload langsung ikut muncul di avatar navbar (sebelumnya cuma inisial huruf doang, sekarang fallback ke inisial cuma kalau belum ada foto)
- [x] Tombol "Remove Photo" buat hapus foto & balik ke tampilan inisial huruf

### Info Kepegawaian di Halaman Profile
- [x] Info "Joined since [tanggal] (x.x yrs)" — dihitung otomatis dari tanggal mulai kontrak karyawan
- [x] Nambah field yang sebelumnya belum ditampilin sama sekali: Tanggal Lahir, Mulai Kontrak, Selesai Kontrak
- [x] Data sensitif (NIK, NPWP, Status Pernikahan, Tanggal Lahir) disensor default (tampil titik-titik) — ada tombol "peek" (icon mata, mirip toggle show/hide password) buat nampilin sementara kalau memang perlu dicek
- [x] User ID ditampilin di bawah nama — biar gampang kalau user perlu sebutin ID-nya pas lapor masalah ke admin
- [x] Card "Employee Information" digabung jadi satu sama card foto profil (dipisah pakai separator), bukan card terpisah lagi

### Bug Ketemu & Dibenerin
- [x] Ketemu lagi bug compiler Livewire Volt (kejadian ketiga di project ini, mirip temuan-temuan sesi sebelumnya soal kata `new`) — kalau baris kode terakhir di komponen Volt itu nutup pakai `};` polos (closure biasa, bukan dibungkus pemanggilan fungsi lain), compiler-nya bisa salah taro kode internal di tempat yang salah & bikin halaman 500 pas cache-nya di-refresh. Dibenerin dengan nulis komponen `/profile` pakai gaya class biasa (`new class extends Component {...}`), bukan gaya functional closure — polanya disamain kayak komponen lain yang udah terbukti aman

### Pending
- Full test suite belum di-run ulang setelah revisi terakhir (Employee Information & foto profil) — perlu diverifikasi sebelum dianggap kelar total
- Kolom `contract_type` yang sempet diminta buat ditampilin ternyata belum ada di database (baru ada `employment_status` & `is_cltp`) — sempat disiasati pakai `employment_status` + badge "CLTP", tapi baris ini akhirnya dihapus lagi dari tampilan atas permintaan user

---

## 10/08/2026 (Sesi 3) Checklist Perbaikan Foto Profil & Datepicker Dashboard Harian

### Foto Profil — Bug Akses Ditolak
- [x] Ketemu di production: foto profil yang udah keupload gak bisa dibuka, MinIO nunjukin `AccessDenied` — ternyata bucket `nakama-dashboard` emang privat, sedangkan kode-nya masih generate URL publik biasa
- [x] Dibenerin dengan pakai "kunci sementara" (presigned URL, berlaku 15 menit) buat foto profil — polanya disamain persis kayak link dokumen Akad yang udah lebih dulu kena masalah sama & udah dibenerin (lihat sesi 15/07/2026)
- [x] Ukuran maksimal upload foto profil diturunin dari 2MB jadi **1MB**, biar filenya gak kegedean

### Dashboard Harian (Funding) — Input Tanggal Kerja
- [x] Ketemu bug: kalau kolom "Tanggal Kerja" diketik manual & belum lengkap (misal tanggal/bulan/tahunnya sempat "0"), halaman langsung error 500
- [x] Diperbaiki dua lapis: (1) input tanggal dipaksa gak bisa diketik/paste manual (harus lewat kalender bawaan browser), (2) validasi cadangan di backend — kalau somehow nilainya tetap gak valid, otomatis balik ke tanggal hari ini, bukan nge-crash halaman
- [x] Sempat kepakai atribut `readonly` buat blokir ketikan manual, tapi ternyata di Chrome/Edge itu malah mematikan kalender-nya juga (regresi) — dibenerin, sekarang klik di mana pun pada field-nya (bukan cuma icon kalender) otomatis manggil kalender lewat `showPicker()`, sementara ketik/paste manual tetap diblokir

### Cross-check Modul Investors (FO Contacts) — belum ada perubahan kode, sebatas jawaban audit
- Input No HP: **sudah** dibatasi angka saja (mask + validasi server, 9–15 digit)
- Profesi & No HP wajib diisi: **belum** — keduanya masih opsional (`nullable`), nunggu konfirmasi business rule dulu
- Penanganan kontak yang dihapus tapi masih dipakai di Funnel: **sudah** — soft delete, histori Funnel tetap utuh

### Pending
- Full test suite belum di-run ulang setelah semua perbaikan sesi ini (foto profil & datepicker) — perlu diverifikasi
- Business rule buat wajib/tidaknya field Profesi & No HP di form Contacts belum dikonfirmasi

---

