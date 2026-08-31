## Agustus

### 03/08/2026 — Seeder FO Dinamis & Tombol Submit Target FO
-[x] feat : seeder FO — deteksi otomatis dari data HR (Departemen Finance + jabatan hasil import CSV), gak hardcode `user_id` lagi -> pattern sama kayak departemen Revenue and Growth, FO baru otomatis ke-assign role
-[x] feat : /managementFo input target tahunan (Leads/QL/Pitching/Closing/Nilai Closing) gak auto-save lagi -> wajib klik tombol "Simpan Target"; cuma metrik yang nilainya beneran berubah yang kecatat di riwayat
-[x] feat : /managementFo FO biasa sekarang bisa akses halaman (sebelumnya 403) tapi read-only — form/tombol edit disembunyikan; ubah target tetap cuma Admin FO & Super Admin
-[x] fix  : /managementFo proteksi ubah/reset target ditambah di backend (bukan cuma UI) -> FO biasa gak bisa akalin lewat console browser
-[x] docs : `rbac_implementation.md` — audit semua role/permission check di codebase; kesimpulan: assign role sudah dinamis penuh via Manage Users & Manage Roles
-[x] test : `ManagementFoTest` disesuaikan + test baru tombol submit & proteksi role FO biasa

### 05–08/08/2026 — Notifikasi In-App (Partnership & Funding)
-[x] feat : tabel `notifications` (Laravel built-in) — per user, status baca/belum baca
-[x] feat : icon lonceng navbar (mobile & desktop) — badge unread count, polling tiap 15s
-[x] feat : halaman `/notifications` — list semua, tab Semua/Belum Dibaca, tombol "Tandai semua dibaca"
-[x] feat : klik notifikasi -> auto mark read + redirect ke data terkait (card/prospek)
-[x] note : belum realtime (Reverb/websocket), masih polling ringan -> realtime nyusul bareng fitur Chat
-[x] feat : notif Partnership — PMO closing (status Won) -> notif ke Manager; Manager verifikasi di Realisasi -> notif balik ke PMO
-[x] feat : notif Funding — FO closing investasi -> notif ke Admin FO (Investor Admin), bukan Manager Partnership (alur approval beda: Partnership ada jenjang Manager, Funding enggak)
-[x] refactor : navbar avatar user disederhanakan jadi icon inisial doang; nama/email/jabatan dipindah ke dropdown
-[x] fix  : bug compiler Livewire Volt — `new ...` langsung di komponen Volt bikin page error total -> pindahin pembuatan notifikasi ke method khusus di class notifikasi
-[x] fix  : perubahan navbar/lonceng sempat salah taruh di file layout lama starter-kit (dead code) -> dipindah ke layout yang aktif
-[x] test : notif Partnership & Funding + test isolasi lintas departemen, semua pass. Full suite 193/195 (2 fail pre-existing, `ilike` gak didukung SQLite, unrelated)
-[ ] pending : realtime notif (Reverb) nyusul fitur Chat
-[ ] pending : 2 test `ilike` belum portable ke SQLite

### 10/08/2026 — Notifikasi Detail, Stage "Closing" Baru, FU Audit Trail, UI Funnel
-[x] feat : notif Funding trigger pas Closing beneran (uang masuk), bukan pas Won (komitmen doang)
-[x] feat : lead di-set Lost -> auto notif ke Admin FO
-[x] feat : notif perubahan target FO nampilin detail per metrik (misal "Leads 194 → 195, QL 126 → 100")
-[x] feat : stage baru "Closing" dipisah dari "Won" — funnel: Leads -> QL -> Pitching -> Follow Up -> Won -> Lost -> Closing -> Akad
-[x] feat : Won = komitmen verbal (tanggal + nominal, kolom baru `nominal_won`); Closing = uang beneran masuk (nominal & tanggal terpisah) -> badge kuning "Komit: Rp X" kalau selisih
-[x] feat : form Won & Closing wajib diisi (tanggal+nominal) & gak bisa ditutup sebelum disimpan -> gak ada lagi kartu nyangkut
-[x] fix  : laporan (Dashboard Harian, Capaian Bulanan, Konversi Bulanan, Management FO) yang nganggep Won=Closing -> dipisah, kolom Won baru ditambah di tiap tabel/grafik
-[x] note : data lama di stage Won dibiarkan apa adanya, gak dipaksa migrasi
-[x] feat : Follow Up bisa diedit (tanggal/catatan) + auto-log riwayat perubahan (siapa/kapan/dari-ke), popup terurut terbaru
-[x] feat : Follow Up bisa dihapus (konfirmasi dulu) & dipulihkan via tombol "Lihat Terhapus"
-[x] fix  : lead dengan kontak terhapus -> hilang dari kolom Leads; lead yang udah lewat Leads (Won/Closing/dst) tetap tampil walau kontak terhapus
-[x] feat : /kontak input No HP dibatasi angka only (9–15 digit), validasi server juga
-[x] feat : /kontak tombol Simpan auto-disable + spinner saat proses -> cegah double-submit
-[x] style : kanban — warna badge nominal ngikutin warna stage; badge "Nx Follow Up" dipindah tengah (antara tanggal & sumber), cursor pointer; background kolom kanban ngikutin tinggi kartu di kolom sendiri (sebelumnya ke-stretch ikut kolom lain)
-[x] fix  : bug compiler Livewire Volt (lagi) — literal `new` (termasuk di attribute HTML `new Date()`) bikin komponen gagal pas cache refresh (500) -> semua tanggal hari ini pakai method Alpine (`setWonToday()`, dst), bukan `new Date()` langsung di HTML
-[x] fix  : modal Won/Closing nutup sendiri pas ngetik nominal -> kurang atribut `wire:ignore.self` (disamain pattern modal Akad)
-[x] test : puluhan test baru (`FoFunnelTest`, `ManagementFoTest`, `InvestorsDashboardTest`, `MonthlyAchievementTest`, `MonthlyConversionTest`). Full suite 210/212 (2 fail pre-existing ilike/SQLite, unrelated)
-[ ] pending : enforcement read-only Direksi di tombol Add/Edit/Delete
-[ ] pending : realtime notif (Reverb) nyusul fitur Chat
-[ ] pending : belum ada cegah hapus kontak yang masih punya funnel aktif di stage lain

### 10/08/2026 (Sesi 2) — Keamanan Login, Konsolidasi Settings → Profile, Foto Profil
-[x] fix  : validasi email diperketat (`email:rfc,strict`) di form Login & Registrasi/Edit Profil -> email tanpa domain lengkap (`user@domain`) ditolak
-[x] verify : pesan gagal login (Laravel Fortify) sudah generik, gak bocorin email/password mana yang salah -> dikunci pakai test
-[x] feat : /login tombol "Log in" auto-disable + spinner saat diklik -> cegah request numpuk
-[x] feat : sidebar — badge role user (emas) di brand header (bawah logo) & di dropdown profil
-[x] style : tagline "Enterprise Resource Planner" dirapiin satu baris penuh ngikutin lebar sidebar
-[x] fix  : dropdown user navbar (mobile & desktop) lebar auto ngikutin panjang nama
-[x] refactor : halaman Settings lama (`/settings/*` — Profile, Security, Appearance, 3 halaman terpisah) dihapus total
-[x] feat : konsolidasi ke `/profile` (info akun, foto, info kepegawaian, tema, hapus akun — satu halaman tab) & `/profile/security` (password, 2FA, passkeys — rute terpisah, tetap kena proteksi re-confirm password)
-[x] refactor : dropdown sidebar — gabung menu "My Profile" & "Settings" (dulu nyasar ke halaman sama) jadi satu menu "My Profile"
-[x] feat : `/profile` upload foto profil — klik icon pensil, auto-upload begitu file dipilih (no tombol Upload terpisah)
-[x] infra : foto profil disimpan di bucket MinIO yang sama dipakai dokumen Akad (bukan server app)
-[x] feat : foto ikut muncul di avatar navbar (fallback inisial kalau belum ada foto)
-[x] feat : tombol "Remove Photo"
-[x] feat : `/profile` info kepegawaian — "Joined since [tanggal] (x.x yrs)" auto-calc dari tanggal mulai kontrak
-[x] feat : `/profile` field baru: Tanggal Lahir, Mulai Kontrak, Selesai Kontrak
-[x] feat : `/profile` data sensitif (NIK, NPWP, Status Pernikahan, Tanggal Lahir) default disensor + tombol peek (icon mata)
-[x] feat : `/profile` tampilin User ID di bawah nama (buat lapor masalah ke admin)
-[x] style : card "Employee Information" digabung sama card foto profil (dipisah separator)
-[x] fix  : bug compiler Livewire Volt (ke-3 kalinya) — closure ditutup `};` polos di baris terakhir komponen bikin salah taro kode internal -> 500 pas cache refresh -> `/profile` ditulis pakai gaya class (`new class extends Component {...}`), bukan functional closure
-[ ] pending : full test suite belum di-run ulang setelah revisi terakhir
-[ ] pending : kolom `contract_type` diminta tapi belum ada di DB (baru `employment_status` & `is_cltp`) — sempat disiasati pakai `employment_status`+badge CLTP, dihapus lagi atas permintaan user

### 10/08/2026 (Sesi 3) — Perbaikan Foto Profil & Datepicker Dashboard Harian
-[x] fix : foto profil `AccessDenied` di production — bucket `nakama-dashboard` privat tapi kode generate URL publik -> ganti pakai presigned URL (berlaku 15 menit), pattern sama kayak dokumen Akad (ref sesi 15/07/2026)
-[x] fix : max upload foto profil diturunin dari 2MB -> 1MB
-[x] fix : Dashboard Harian (Funding) input "Tanggal Kerja" — input manual gak lengkap (misal "0") -> 500 error
-[x] fix : input tanggal dipaksa via kalender bawaan browser (no manual type/paste) + validasi backend fallback ke hari ini kalau invalid
-[x] fix : sempat pakai `readonly` buat blokir manual input tapi malah matiin kalender di Chrome/Edge (regresi) -> ganti: klik di mana pun pada field manggil `showPicker()`, manual type/paste tetap diblokir
-[x] audit : modul Investors (FO Contacts), belum ada perubahan kode —
  - No HP: sudah dibatasi angka only (mask + validasi server, 9–15 digit)
  - Profesi & No HP wajib: belum (masih `nullable`, nunggu konfirmasi business rule)
  - Kontak dihapus tapi masih dipakai di Funnel: sudah (soft delete, histori funnel tetap utuh)
-[ ] pending : full test suite belum di-run ulang setelah sesi ini
-[ ] pending : business rule wajib/tidaknya Profesi & No HP di form Contacts belum dikonfirmasi

### 13/08/2026 — Target FO Dua Arah (Tahunan/Bulanan/Harian) & Full English UI Modul Investors
-[x] feat : /managementFo form "Atur Target" ngikutin tab aktif (Tahunan/Bulanan/Harian) — sebelumnya selalu isi/simpan tahunan doang walau lagi buka tab lain
-[x] feat : edit dari tab Bulanan -> auto convert x12 sebelum simpan; edit dari tab Harian -> convert x12x25(hari kerja) — satu-satunya yang tersimpan di DB tetap `yearly_target`, dua periode lain auto-nyesuain
-[x] feat : ganti tab periode -> auto refresh angka form sesuai pecahan periode (misal tahunan 120 -> kebaca 10 di tab Bulanan)
-[x] fix  : bug laten — proses baca input pakai `Money::normalize` (masking uang) buat semua metrik, strip titik desimal -> target harian pecahan (0.4 leads/hari) kebaca jadi 4 -> dipisah: metrik nominal (Deal Value) tetap masking, metrik hitungan (Leads/QL/dst) pakai parsing angka biasa (aman buat desimal)
-[x] feat : 6 halaman modul Investors (Dashboard, Monthly Achievement, Monthly Conversion, Contacts, Funnel/Kanban, Management FO) full translate ke English — heading/label/tombol/tooltip/placeholder/empty-state/badge/JS confirm-alert
-[x] note : logika PHP, nama variabel/method, state internal (`tahunan`/`bulanan`/`harian`, stage `pitching`/`followup`/`won`/dst), route & kolom DB sengaja gak diubah
-[x] note : toast/notif server-side (`$this->success(...)` dkk) belum disentuh — di luar scope sesi ini
-[x] rename : "Nilai Closing" -> "Deal Value" konsisten di semua tabel/kartu/label
-[x] test : 4 test baru (edit dua arah Bulanan/Harian->Tahunan, currency masking, ganti tab reload buffer) — `ManagementFoTest`; 8 assertion test lama (`assertSee`/`assertDontSee`) di 5 file disesuaikan ke teks Inggris. Full suite modul Investors 124/126 (2 fail pre-existing modul Partnership, ilike/SQLite, unrelated)
-[ ] pending : toast/notif server-side & pesan validasi masih Bahasa Indonesia
-[ ] pending : full test suite project (semua modul) belum di-run ulang setelah sesi ini

### 19/08/2026 — Activity Log Filter, Bug Sinkron Stage Funnel, Audit Lupa Password
-[x] feat : `/investors/activity-log` filter Actor sekarang ikut nampilin Super Admin (sebelumnya kefilter keluar karena employee-nya gak punya `department_id` Finance)
-[x] feat : `/investors/activity-log` filter baru "From"–"To" (datetime-local, tanggal+jam) di samping filter Month/Year yang udah ada
-[x] fix  : gap filter From/To ketiban class Tailwind gak valid (`gap-52`, di luar skala default) -> ganti `gap-4` + lebar tetap biar gak nempel
-[x] fix  : bug — card yang udah dipindah balik dari stage lanjut (Pitching/Follow Up/Won) ke stage sebelumnya tetap kehitung di Monthly Achievement/Conversion/Management FO/Dashboard Harian, karena metrik ql/pitching/followup/won ngecek penanda historis (`FoFunnelActivity` row / kolom `tgl_won`) yang gak pernah dibersihin pas stage-nya berubah -> tambah `FoFunnel::STAGE_ORDER` + scope `stageAtLeast()`, dipasang di 4 file (monthly-index, dashboard-index, management-fo, conversion-index) biar ngikutin stage terkini kayak pattern Closing yang emang udah bener dari awal
-[x] note : metrik QL sengaja gak disamain — `tgl_qualified` ternyata emang gak pernah di-set sama alur pindah-stage manapun di Funnel (cuma keisi lewat seeder), jadi butuh perbaikan terpisah nentuin dulu kapan seharusnya ke-set
-[x] fix  : bug — baris bulan (misal Agustus) di Monthly Conversion tetap "belum ada data" walau card lama udah di-update (tanggal/stage masuk ke bulan itu), baru muncul kalau ada Lead baru beneran dibuat -> flag `hasData` sebelumnya cuma ngecek `lead > 0`, sekarang ngecek kalau salah satu metrik (lead/ql/pitching/won/closing) ada datanya
-[x] test : regression test baru di `MonthlyAchievementTest` (pindah Pitching -> QL) & `MonthlyConversionTest` (funnel lama pindah ke bulan baru tanpa lead baru); 2 test lama yang keitung pitching tanpa update `stage`-nya disesuaikan biar konsisten sama alur produksi. Full suite terkait (Monthly*, ManagementFo, Conversion, DashboardIndex, FoFunnel, ActivityLog) 112/112
-[x] audit : fitur Lupa Password — SMTP Gmail jalan (dites kirim langsung, sukses), notifikasi reset gak di-queue jadi gak butuh `queue:work`
-[x] audit : alur Lupa Password (Fortify default) emang ngecek dulu email terdaftar atau enggak sebelum kirim -> kalau gak ketemu, gak ada email kekirim & muncul error "We can't find a user with that email address"
-[ ] pending : behavior default Fortify di atas bocorin email enumeration (pesan beda antara "gak ketemu" vs "berhasil kirim") -> perlu diseragamin jadi generic response kalau mau ditutup, belum dieksekusi (nunggu keputusan user)

### 20/08/2026 — BUG-003: Data Won Lama Gak Kebaca di Monthly Conversion/Achievement/Management FO
-[x] fix : data lama stage='won' di Funnel (existing, bukan input baru) kebaca 0 di Monthly Conversion, Monthly Achievement, & Management FO -> ketiganya filter `whereBetween('tgl_won', ...)`, tapi kolom `tgl_won`/`nominal_won` baru ditambah setelah data lama itu ada (migration `2026_08_10_051746_add_tgl_won...` & `..._add_nominal_won...`) jadi `tgl_won` null di baris lama -> tambah command `php artisan app:sync-fo-funnel-stage-dates` (`SyncFoFunnelStageDates`), backfill `tgl_won`/`tgl_closing` pakai `updated_at` buat baris won/closing/akad yang tanggalnya masih null
-[x] fix : filter Bulan/Tahun di Funnel kanban bikin data ilang total pas diganti -> ternyata filter itu nge-filter SEMUA stage (leads/ql/pitching/dst) pakai satu kolom `proyeksi_close_date`, padahal kolom itu cuma keisi di segelintir baris (~8 dari ~400) -> tambah `FoFunnel::relevantDateExpression()` + scope `whereRelevantMonth`/`whereRelevantYear`, filter sekarang berbasis tanggal yang relevan sama stage-nya masing2 (leads pakai `tgl_leads`, won pakai `tgl_won`, closing/akad/lost pakai `tgl_closing`, dst) — bukan satu kolom proyeksi doang
-[x] fix : selisih hitungan Won Agustus antara Funnel (3) & Monthly Conversion (4) -> `relevantDateExpression()` versi awal masih salah prioritas (`COALESCE(tgl_closing, tgl_won, ...)` — pilih kolom mana yang keisi duluan, bukan berdasar stage sekarang), jadi 8 baris `stage='won'` yang punya sisa `tgl_closing` dari import CSV lama (beda bulan sama `tgl_won`-nya) kebaca salah bulan di Funnel -> diganti jadi `CASE WHEN stage = 'won' THEN tgl_won WHEN stage IN ('lost','closing','akad') THEN tgl_closing ...` biar Funnel & Monthly Conversion nge-grup baris yang sama ke bulan yang sama
-[x] note : selisih hitungan "won" tetap ada by design kalau ada deal yang lanjut stage dalam periode berjalan (misal Won -> Closing bulan yang sama) -> Monthly Conversion/Achievement/Management FO sengaja kumulatif (`scopeStageAtLeast('won')`, ikut hitung lost/closing/akad juga selama `tgl_won` masuk range) biar rasio konversi Pitching→Won & Won→Closing tetap konsisten; Funnel kanban tetap snapshot stage saat ini doang -> dikonfirmasi ke user, dibiarin gak disamain
-[x] test : `SyncFoFunnelStageDatesTest` (3 test baru) + `FoFunnelTest` filter Bulan/Tahun disesuaikan & ditambah kasus stage-aware (won dgn tgl_closing basi, closing dgn tgl_won basi)
-[ ] pending : belum di-commit & belum dijalanin ke staging/production (`app:sync-fo-funnel-stage-dates` nunggu konfirmasi user dulu)
### 20/08/2026 (Sesi 2) — Heatmap Dashboard Investors & Rework Sidebar
-[x] feat : `/investors/dashboard` penambahan heatmap + control pada heatmap yang influence data tabel di bawahnya
-[x] refactor : `sidebar.blade.php` dirombak besar (528 baris berubah)
-[x] test : `InvestorsDashboardTest` ditambah (~113 baris)
-[x] chore : `Report.md` lama di root dihapus

### 21/08/2026 — Maintenance Page & Reorganisasi Dokumen
-[x] feat : halaman maintenance `errors/503.blade.php` + aset gambar
-[x] chore : semua dokumen (`PRD.md`, `Investor.md`, `Report.md`, `agustus_report.md`, `user-registration.md`, checklist testing, dump SQL) dipindah ke folder `report/`
-[x] feat : `/investors/contacts` tambah timestamp di list

### 21/08/2026 — Seeder Pre-Production Funnel
-[x] feat : `FoFunnelDataSeeder` diperluas + CSV `funnel/funnelcontactpic.csv` (475 baris data PIC kontak)
-[x] fix : penyesuaian lanjutan seeder (commit #2)

### 26/08/2026 — Seeder Daily Report Follow Up (dibatalkan)
-[x] feat : `DailyReportFollowUpSeeder` + `funnel/dailyreport.csv` (481 baris)
-[x] revert : seeder di atas dibatalkan & dihapus lagi di commit berikutnya
-[ ] pending : `funnel/dailyreport.csv` masih tertinggal di repo padahal seeder pemakainya sudah dihapus — dipakai lagi nanti atau dibersihkan?

### 30/08/2026 — Modul Chat & Kode/Warna Departemen
-[x] feat : `app/Livewire/Chat/ChatIndex.php` — modul chat awal
-[x] feat : kolom `code` & `color` di tabel `departments` + `DepartmentCodeSeeder`
-[x] feat : komponen baru `department-code`, `department-badge`, `position-badge`, `dark-mode-toggle`; `user-avatar` disesuaikan
-[x] feat : `/master/departments` bisa atur kode & warna departemen

### 31/08/2026 — Realtime Chat (Reverb + Echo)
-[x] infra : tambah dependency `laravel/reverb` & `pusher/pusher-php-server`; `laravel-echo` + `pusher-js` di frontend
-[x] feat : tabel `conversations`, `conversation_participants`, `messages`, kolom `attachment_preview_path`
-[x] feat : event `MessageSent` (`ShouldBroadcastNow`), model `Conversation` & `Message`, `ConversationPolicy`
-[x] feat : `routes/channels.php` — otorisasi channel `conversation.{id}`, `chat.{userId}`, `App.Models.User.{id}`
-[x] feat : `resources/js/echo.js` + `ImagePreviewGenerator` buat preview lampiran
-[x] feat : notifikasi in-app ikut disiarkan — `via()` keenam notifikasi jadi `['database', 'broadcast']` + `toBroadcast()`
-[x] feat : listener Echo dipasang di lonceng notifikasi
-[x] test : `ChatFlowTest` (128 baris), factory `Conversation` & `Message`
-[x] docs : `REALTIME.md` dibuat

### 31/08/2026 (Sesi 2) — Deploy Reverb ke Shared Hosting & Notifikasi Realtime
-[x] infra : Reverb jalan di production `nakama.smartid.or.id` tanpa Docker & tanpa akses root — supervisord user-level (`~/etc/supervisord.conf`, program `laravel-reverb`) dijaga cron `@reboot`; terverifikasi handshake `101` lewat Cloudflare
-[x] fix  : `APP_ENV` di production sebelumnya masih `local`
-[x] note : `REVERB_SERVER_HOST` **wajib** `0.0.0.0` — sempat dipersempit ke `127.0.0.1` (alasan keamanan) dan bikin seluruh websocket `502`, karena Nginx di server ini menghubungi Reverb lewat IP privat, bukan loopback. Satu variabel ini dipakai dua peran sekaligus: alamat bind (`config/reverb.php`) & alamat tujuan publish (`config/broadcasting.php`)
-[x] fix  : notifikasi belum realtime walau kodenya sudah benar — `BroadcastNotificationCreated` bawaan Laravel `implements ShouldBroadcast` (masuk antrian), sementara server gak jalanin queue worker, jadi siarannya mengendap di tabel `jobs`. Gejalanya menipu: notif tetap tersimpan & muncul pas refresh (channel `database` sinkron), tapi lonceng gak gerak sendiri -> diperbaiki pakai `->onConnection('sync')` di `toBroadcast()` keenam notifikasi
-[x] test : 2 test baru di `NotificationTest` — termasuk test penjaga yang gagal kalau ada notifikasi baru pakai channel `broadcast` tapi lupa `onConnection('sync')`
-[x] docs : `REALTIME.md` ditulis ulang total ngikutin setup yang beneran terpasang (supervisord, bukan systemd/watchdog cron seperti draft awal)
-[x] done : pending "realtime notif (Reverb)" dari entry 05–08/08 & 10/08 sekarang selesai
-[ ] pending : dev & production masih berbagi `REVERB_APP_ID` di satu instance Reverb -> event bocor lintas environment; perlu app kedua khusus dev di `config/reverb.php` server
-[ ] pending : listener Echo di lonceng dipasang lewat `x-init`, numpuk tiap navigasi `wire:navigate` (satu notif memicu N kali refresh komponen)
