# Legend

Format commit/entry ngikutin gaya [Conventional Commits](https://www.conventionalcommits.org/), disesuaikan buat dev log harian:

| Prefix      | Arti |
|-------------|------|
| `feat`      | fitur baru |
| `fix`       | perbaikan bug — format: `[masalah/gejala] -> penyebab & solusi` |
| `refactor`  | rapihin/susun ulang kode, gak ada perubahan behavior |
| `test`      | nambah/nyesuain test |
| `docs`      | dokumentasi |
| `chore`     | beres-beres, gak ngaruh ke behavior aplikasi |
| `infra`     | konfigurasi server/deployment/dependency |
| `style`     | perubahan tampilan/CSS doang, gak ngaruh ke logic |
| `verify`    | ngecek ulang behavior existing, ternyata udah benar/gak perlu diubah |
| `note`      | catatan tambahan, bukan perubahan kode |
| `pending`   | belum dikerjain / nunggu keputusan |

Checklist status: `-[x]` = selesai, `-[ ]` = belum/pending.

---

## September

### 01/09/2026 — Username & Secondary Email, Perbaikan UI Navbar
-[x] feat : kolom `username` & `secondary_email` di tabel `users` (migration baru) + `ProfileValidationRules` — user sekarang bisa login pakai username selain email
-[x] feat : `LoginRequest` & `FortifyServiceProvider` disesuaikan biar `authenticateUsing` nerima username atau email, bukan email doang
-[x] fix  : dropdown avatar navbar (mobile & desktop) — lebar dibatasi `max-w-xs` bikin nama panjang kepotong -> diganti `max-w-sm` + `wrap-break-word` biar nama panjang turun baris, bukan ke-truncate
-[x] fix  : `resources/js/echo.js` sempat coba join channel presence `online` walau user belum login (guest) -> ditambah guard cek meta tag `authenticated` sebelum `Echo.join('online')`

### 02/09/2026 — Root Cause: Reset Password "Gak Bisa Submit, Gak Ada Error"
-[x] fix : [`/reset-password` diklik Submit keliatan gak ngapa-ngapain, gak ada pesan error muncul sama sekali — padahal di lokal jalan normal] server sebenarnya udah proses & redirect balik (`302` ke halaman yang sama) tiap validasi gagal (token invalid/expired/udah pernah dipakai), tapi komponen `<x-input>` (Mary UI) cuma bisa nampilin error kalau field-nya punya `wire:model` atau prop `errorField` eksplisit — form ini plain HTML (bukan Livewire), input cuma pakai `name=` biasa, jadi `$errors->has(null)` selalu `false` -> pesan error dari Fortify (`This password reset token is invalid.`, dst) gak pernah nongol di UI -> ditambah `error-field="email"` & `error-field="password"` di kedua input, plus `old('email', request('email'))` biar email yang diketik user gak ilang pas validasi gagal
-[x] test : 2 test baru di `PasswordResetTest` — token invalid dapet session error yang bener (`assertSessionHasErrors`), & view beneran nampilin teks errornya (bukan cuma kecek di session, tapi di HTML)

### 02/09/2026 (Sesi 2) — Peek Button & Password Strength Guide di Reset Password
-[x] feat : field password di `/reset-password` diganti `<x-input>` -> `<x-password>` (Mary UI) — dapet tombol peek (icon mata) buat show/hide isi password, gak perlu JS custom karena udah bawaan komponen
-[x] feat : checklist kekuatan password realtime pas ngetik (Alpine.js, murni client-side tanpa round-trip Livewire) — 4 kriteria: minimal 12 karakter, huruf besar & kecil, ada angka, ada simbol; tiap kriteria ada ikon centang (aman)/silang (belum), plus bar & label **Weak / Medium / Strong** yang otomatis ganti warna (merah/kuning/hijau) ngikutin skor
-[x] note : kriteria checklist ngikutin aturan password production di `AppServiceProvider::configureDefaults()` (min 12 karakter, mixed case, angka, simbol) — checklist ini murni panduan visual di client, validasi asli tetap dipegang penuh sama server

### 02/09/2026 (Sesi 3) — Link Reset Password Sekali Pakai & Reusable Component
-[x] verify : [khawatir link reset password bisa dipakai berkali-kali] ternyata link otomatis invalid setelah sukses dipakai — bawaan Laravel/Fortify (`PasswordBroker::reset()` langsung hapus token dari tabel `password_reset_tokens` begitu password berhasil diganti), gak perlu kode tambahan -> dikunci pakai test regresi `reset password link cannot be reused after a successful reset` biar gak sengaja kebobol di masa depan
-[x] refactor : peek button + checklist kekuatan password ditarik jadi satu komponen reusable `<x-password-strength-field>` (`resources/views/components/password-strength-field.blade.php`) — semua attribute (`wire:model`, `name`, `label`, `error-field`, dst) tinggal diteruskan lewat `{{ $attributes }}`, gak ada logic Alpine yang ditulis ulang tiap dipakai di halaman lain
-[x] feat : `/profile/security` field "New password" sekarang pakai komponen yang sama (`<x-password-strength-field wire:model="password" .../>`) — checklist & peek button konsisten sama halaman reset password
-[x] feat : `/profile/security` field "Current password" & "Confirm password" juga dinaikin ke `<x-password>` (dari `<x-input type="password">` biasa) biar peek button ada di semua field password, gak cuma yang baru
-[x] test : full suite `PasswordResetTest` (7 test) + `ProfileSecurityTest` (6 test) tetap hijau setelah refactor, Pint bersih

### 06/09/2026 — Website Kadang Tampil "Kosong"/Belum Jalan Fungsinya Sebelum Di-refresh
-[x] note : [minggu lalu ada laporan halaman kadang muncul duluan tapi tombol/fitur di dalamnya belum berfungsi sampai di-refresh manual] penyebabnya ada dua sistem yang "rebutan" saat halaman dibuka: fitur percepatan dari Cloudflare (namanya Rocket Loader) dan fitur percepatan dari sistem website sendiri (Vite). Rocket Loader ngasih prioritas nampilin teks/tampilan HTML duluan, sedangkan Vite ngasih prioritas nyalain fungsi tombol & interaksi duluan — pas dua-duanya jalan bareng, yang menang duluan tampilannya, jadi fungsinya ketinggalan
-[x] fix  : solusinya minta Pak Ivan matiin fitur Rocket Loader di pengaturan Cloudflare, lalu source code website dikembalikan ke versi sebelum ada perubahan tambahan buat "menyiasati" masalah ini (commit `fix: no rocket loader cloudflare`) — karena setelah Rocket Loader dimatikan, penyesuaian tambahan di source code jadi gak perlu lagi
-[x] note : sempat dicoba dulu diakalin lewat source code pas ketemu Pak Ivan waktu training, tapi ternyata gak bisa diselesaikan cukup dari source code aja — akar masalahnya emang di pengaturan Cloudflare, jadi harus Pak Ivan yang matiin dari sana

### 09/09/2026 — Halaman Activity Log Investor Error 500
-[x] fix  : [`/investors/activity-log` server error 500] root cause `Array to string conversion` di helper `formatLogValue()` (`activity-log-index.blade.php`) — helper ini nge-cast tiap value perubahan ke string langsung, tapi sejak fitur upload dokumen akad funnel bisa multiple file (commit `feat: multiple upload funnel stage akad`), value yang dicatat di log buat field tsb sekarang berupa array (list nama file), bukan string lagi -> ditambah pengecekan `is_array($value)`: kalau array, di-join per item (item scalar langsung digabung, item non-scalar di-`json_encode` biar tetep aman ditampilkan)
-[x] test : nambah test baru `array change values (e.g. multi-file uploads) are rendered without error` di `ActivityLogPageTest` — simulasiin log dengan value array kayak kasus aslinya, mastiin halaman render `assertOk()` dan isi filenya kebaca, bukan crash lagi
-[x] note : di staging/dev halaman ini aman-aman aja (belum ke-trigger), errornya baru kejadian setelah fitur multiple upload dokumen akad funnel (commit `feat: multiple upload funnel stage akad`) udah naik ke production — baru ketauan pas dicoba akses `/investors/activity-log` di production beneran

### 09/09/2026 (Sesi 2) — Nominal di Form Edit Funnel Kegedean 100x (Akad, Won, Closing, Proyeksi)
-[x] fix  : [buka form Edit funnel yang udah ada nominalnya — misal kartu keliatan `Rp 100 Jt` di kanban — tapi pas dibuka form Edit-nya, field "Projected Amount" & "Investment Amount" nilainya jadi `10.000.000.000` (kegedean 100x)] kolom `proyeksi_amount`, `nominal_investasi`, `nominal_won` di model `FoFunnel` di-cast `decimal:2`, jadi Eloquent balikin nilainya sebagai string kayak `"100000000.00"` (bukan angka biasa). `openEditModal()` masukin string itu apa adanya ke field Livewire yang dipasangin mask `$money($input, ',', '.', 0)` di input-nya. Mask ini masang koma (`,`) sebagai penanda desimal, tapi titik di `"100000000.00"` itu titik desimal beneran dari PHP (selalu titik, gak ngikutin config) — pas mask coba bersihin input, titik itu dianggap karakter gak penting terus dibuang, sisa dua digit nol di belakangnya malah ketinggalan nempel jadi angka, hasilnya nilai kekali 100 -> di `openEditModal()`, ke-4 field ini (`editProyeksiAmount`, `editAkadNominal`, `editWonNominal`, `editClosingNominal`) di-`(int)` cast dulu sebelum dimasukin biar bentuknya angka bulat polos, bukan string desimal
-[x] test : nambah test baru `opening the edit modal seeds amount fields as plain integers, not decimal-cast strings` di `FoFunnelTest` — mastiin `editProyeksiAmount` & `editAkadNominal` ke-set persis sama kayak nominal aslinya (bukan versi yang udah "meleset"), plus benerin 1 assert lama yang keliru ngecek `'400000000.00'` (nilai versi bug) jadi `400_000_000` (nilai yang bener)
