# Checklist Perbaikan — Retest Login & Investors (Contacts/Funnel)

Dibuat dari hasil retest QA. Kerjakan satu per satu, centang setelah fix + test lulus.
Bagian **B** adalah item minor (PASS tapi ada saran) — prioritas rendah, boleh dikerjakan belakangan.

---

## A. Bug — Masih FAIL setelah retest (prioritas tinggi)

### [x] LGN-13 — Validasi format email tidak ketat — **FIXED**
- **Actual Result (Retest):** Form masih bisa di-submit menggunakan format email `user@domain` tanpa memunculkan pesan error.
- **Notes (Retest):** Validasi email perlu diperketat. Perbaikannya bisa disesuaikan dengan prioritas tim saat ini, mengingat bug ini tidak memblokir alur utama sistem.
- **Root cause sebenarnya:** Validasi backend (`email:rfc,strict` di `app/Http/Requests/Auth/LoginRequest.php`) sudah benar menolak `user@domain` — sudah diverifikasi via test, tidak diubah. Masalahnya ada di tampilan: komponen `<x-input>` (Mary UI, `vendor/robsontenorio/mary/src/View/Components/Input.php`) menentukan field error dari `wire:model`, dan form login ini bukan komponen Livewire (post biasa ke Fortify), jadi `errorField` tidak pernah ter-set → pesan error tidak pernah dirender meskipun validasi session sudah berisi error.
- **Fix:** `resources/views/pages/auth/login.blade.php` — tambahkan atribut `errorField="email"` pada `<x-input>` field email, supaya komponen tahu field mana yang harus dicek dari `$errors` bag.
- **Test:** `tests/Feature/Auth/AuthenticationTest.php` — ditambahkan test `invalid email format error message is displayed on the login page` (reproduce bug dulu → gagal, lalu pass setelah fix). Semua 8 test di file ini lulus (`php artisan test --compact --filter=AuthenticationTest`).

### [x] LGN-17 — "Remember me" tidak dicentang tapi sesi tetap aktif setelah browser ditutup — **FIXED**
- **Actual Result (Retest):** Setelah browser ditutup dan dibuka kembali, pengguna masih dalam keadaan login (sesi tidak berakhir) meskipun checkbox "Remember me" TIDAK dicentang.
- **Notes (Retest):** Untuk perbaikannya bisa disesuaikan dengan prioritas tim, mengingat bug ini tidak memblokir alur utama sistem.
- **Root cause sebenarnya:** Flag `remember` sudah diteruskan dengan benar oleh Fortify ke `Auth::attempt()` (`vendor/laravel/fortify/src/Actions/AttemptToAuthenticate.php:57,84`) — tidak diubah. Masalahnya di `config/session.php`: `expire_on_close` bernilai `false`, sehingga cookie sesi PHP disimpan permanen di disk (berlaku 120 menit sesuai `SESSION_LIFETIME`) dan tidak ikut terhapus saat browser ditutup — ini berlaku untuk SEMUA login, terlepas dari status checkbox remember.
- **Fix:** `.env` dan `.env.example` — tambahkan `SESSION_EXPIRE_ON_CLOSE=true`. Efeknya:
  - Tanpa remember: cookie sesi jadi *session cookie* murni (tanpa `Expires`), otomatis hilang saat browser benar-benar ditutup.
  - Dengan remember: cookie `remember_web_...` yang terpisah (umur ~5 tahun) tetap dibuat oleh Fortify, jadi user tetap bisa login otomatis lagi meski cookie sesi sudah hilang — perilaku "remember me" tidak berubah.
- **Test:** `tests/Feature/Auth/AuthenticationTest.php` — 2 test baru: `session cookie does not persist after the browser closes when remember me is unchecked` dan `remember me keeps the user authenticated beyond the session cookie lifetime`. Diverifikasi test pertama gagal jika `SESSION_EXPIRE_ON_CLOSE=false` (regression guard valid). Full suite dijalankan — tidak ada regresi baru (3 test yang gagal sebelumnya juga gagal di branch sebelum perubahan, tidak terkait).

### [x] LGN-18 — Tombol "Log in" double submit — **sudah PASS di retest**, tidak perlu aksi lanjutan.
- **Actual Result (Retest):** Tombol menjadi disabled/loading state setelah klik pertama, tidak ditemukan adanya request ganda.
- **File terkait (referensi):** `resources/views/pages/auth/login.blade.php` — Alpine `x-data="{ submitting: false }"`, tombol `data-test="login-button"` di-disable saat `submitting = true`.

### [x] INV-CON-15 — Hapus kontak yang dipakai di Funnel — **sudah PASS di retest**, tidak perlu aksi lanjutan.
- **Actual Result (Retest):** Sistem berhasil menghapus kontak. Card Funnel yang terkait dengan kontak tersebut juga ikut terhapus secara otomatis, sehingga tidak ada data yang kosong/broken pada menu Funnel.
- **File terkait (referensi):**
  - `resources/views/livewire/investors/contact-index.blade.php` — method `$deleteContact`.
  - `database/migrations/2026_07_14_135659_create_fo_funnels_table.php` — FK `fo_contact_id` pakai `cascadeOnDelete()`.
- **Catatan:** Perilaku cascade delete ini disengaja/sesuai desain — tidak ada dialog peringatan "kontak sedang dipakai di Funnel" sebelum hapus. Jika bisnis ingin ada warning, ini perlu ditambahkan sebagai fitur baru (bukan bug fix), diskusikan dulu dengan user.

---

## B. Minor — PASS tapi ada saran perbaikan (prioritas rendah)

### [x] LGN-23 — Sidebar tidak menampilkan info role yang sedang login
- **Actual Result (Retest):** *(kosong — status masih PASS, item ini catatan tambahan dari testing sebelumnya)*
- **Notes (Retest):** Fitur telah ditambahkan sesuai saran pada testing sebelumnya — hasil retesting: sidebar sudah ditinjau ulang.
- **Saran asli:** Tampilkan informasi role pengguna yang sedang login (misalnya pada header/profil pengguna, atau sidebar) untuk memudahkan identifikasi hak akses yang sedang digunakan.
- **File terkait:** `resources/views/layouts/app/sidebar.blade.php` — role check pakai `@hasanyrole(...)` (baris ~294) dan `auth()->user()->hasRole('Super Admin')` (baris ~395). Tambahkan badge/label role di area header/profil.

### [x] INV-FUN-25 — Panel History Follow Up belum bisa edit/hapus histori
- **Notes (Retest):** Fitur telah ditambahkan sesuai saran pada testing sebelumnya.
- **Saran asli:** Sediakan opsi edit atau hapus pada histori Follow Up agar pengguna dapat memperbaiki atau menghapus data apabila terjadi kesalahan input setelah Follow Up tersimpan.
- **File terkait:** `resources/views/livewire/investors/funnel-index.blade.php` — computed `$historyFunnel` (baris ~171), toggle `$openHistory`/close (baris ~563-574), markup panel (baris ~1176-1276).

### [x] INV-FUN-35 — Drag card ke "Won" dengan nominal dikosongkan — **FIXED (poin 2)**
- **Notes (Retest):** Fitur telah ditambahkan sesuai saran pada testing sebelumnya.
- **Saran asli:**
  1. ✅ Sudah ada sebelumnya — Field nominal sudah wajib diisi (`wonNominal` divalidasi `required|numeric|min:1|max:9999999999999`), tidak diubah.
  2. ✅ Diperbaiki sekarang — Tambahkan tombol Close/Cancel pada modal pemindahan card untuk mengantisipasi kesalahan pemindahan.
- **Root cause tombol Close hilang khusus di Won:** Modal Won (dan Closing) diberi atribut `persistent` pada `<x-modal>` (Mary UI, `vendor/robsontenorio/mary/src/View/Components/Modal.php`) agar tidak bisa ditutup lewat klik backdrop/Escape/tombol X di header — supaya data komitmen tidak hilang tanpa sengaja. Efek sampingnya: `persistent` juga menghilangkan SATU-SATUNYA cara menutup modal (tombol X header), sehingga modal Won/Closing benar-benar tidak bisa ditutup sama sekali, beda dari modal lain yang masih punya tombol X.
- **Fix (langkah 1):** `resources/views/livewire/investors/funnel-index.blade.php` — menambahkan tombol **Close** eksplisit (`x-on:click="$el.closest('dialog').close()"`) di footer (`modal-action`) pada SEMUA 9 modal funnel (Add Lead, Edit, Pitching, Follow Up, Edit Follow Up, Akad, **Won**, **Closing**, Lost), berdampingan dengan tombol **Save**, masing-masing `flex-1` supaya lebar merata dan konsisten di semua form — termasuk gaya `w-full`-look yang sebelumnya cuma ada di form Won/Closing.
- **Fix (langkah 2 — konsistensi lebih lanjut):** Karena semua form sekarang sudah punya tombol Close di footer, tombol X pojok kanan atas di header (bawaan Mary UI, tampil otomatis kalau modal tidak `persistent`) jadi duplikat. Ditambahkan atribut `persistent` ke 8 modal yang sebelumnya belum punya (Add Lead, Edit, Pitching, Follow Up, Edit Follow Up, Follow Up Change Log, Akad, Lost) untuk menyembunyikan tombol X tersebut serta menonaktifkan klik-backdrop-untuk-tutup — sekarang SEMUA 10 modal funnel (termasuk Won & Closing yang sudah `persistent` dari awal) konsisten: hanya bisa ditutup lewat tombol Close eksplisit atau tombol Escape (native browser `<dialog>`, tidak diblokir oleh `persistent` karena modal-modal ini pakai atribut `id`, bukan `wire:model` entangle — lihat `Modal.php` baris ~39-41).
- **Test:** Menjalankan ulang seluruh `tests/Feature/FoFunnelTest.php` (45 test, tidak menyentuh markup modal) — semua lulus, tidak ada regresi, di kedua langkah fix.

### [x] INV-CON-12 — Field No HP menerima input huruf
- **Notes (Retest):** Fitur telah ditambahkan sesuai saran pada testing sebelumnya.
- **Saran asli:** Disarankan field No HP hanya menerima input numerik untuk menjaga konsistensi format nomor telepon.
- **File terkait:** `resources/views/livewire/investors/contact-index.blade.php` — validasi `no_telepon` pada method `$saveContact` (regex `/^[0-9]{9,15}$/`, pesan "No HP harus berupa angka, 9-15 digit."). **Cek ulang:** regex backend sudah benar, kemungkinan masalah ada di sisi input HTML (belum pakai `type="tel"`/`inputmode="numeric"` atau mask di frontend) sehingga huruf masih bisa diketik meski gagal validasi saat submit.

### [x] INV-CON-13 — Submit Tambah Kontak hanya dengan Nama Lengkap (field lain kosong)
- **Notes (Retest):** Fitur telah ditambahkan sesuai saran pada testing sebelumnya.
- **Saran asli:** Disarankan Profesi dan No HP menjadi field wajib untuk memastikan kelengkapan data kontak. Mohon dikonfirmasi dengan business rule terlebih dahulu — apakah field ini memang harus wajib.
- **File terkait:** `resources/views/livewire/investors/contact-index.blade.php` — validasi pada `$saveContact`: `profesi` saat ini `nullable`, perlu diubah jadi `required` bila business rule mengonfirmasi.
- **⚠️ Perlu konfirmasi user/PM dulu** sebelum ubah field jadi wajib — ini keputusan bisnis, bukan sekadar bug.

---

## C. Bug Report Sheet

### [x] BUG-001 — Field tanggal bisa diketik manual (format tidak valid) — **sudah PASS di retest**, tidak perlu aksi lanjutan.
- **Actual Result (Retest):** Input manual pada field tanggal sudah dinonaktifkan (disabled). Pengguna tidak dapat lagi mengetikkan tanggal secara langsung pada field tersebut.
- **Notes (Retest):** Isu sudah diperbaiki. Sistem sudah mencegah input manual pada field tanggal sehingga dapat menghindari risiko error akibat format tanggal yang tidak valid.
- **File terkait:** Belum teridentifikasi secara spesifik (di luar cakupan Login/Investors yang sudah ditelusuri) — cek komponen date picker yang dipakai di modul terkait BUG-001, telusuri lewat `grep -r "date" resources/views/livewire` atau tanyakan sheet Bug Report untuk modul asalnya.

---

## Cara pakai file ini
1. Kerjakan **Section A** dulu (masih FAIL, prioritas tinggi).
2. Untuk tiap item: baca "File terkait", perbaiki, lalu tulis/jalankan test Pest terkait (`php artisan test --compact --filter=...`).
3. Setelah fix, centang `[x]` dan opsional catat commit/PR di baris "Notes (Retest)".
4. **Section B** boleh dikerjakan belakangan (minor) — sebagian butuh konfirmasi business rule dulu (lihat INV-CON-13).
5. Section C (BUG-001) tidak perlu aksi — sudah lulus retest, disertakan hanya untuk dokumentasi.
